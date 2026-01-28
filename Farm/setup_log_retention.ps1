#!/usr/bin/env pwsh
# MongoDB Log Retention Setup Script
# Implements TTL (Time-To-Live) indexes for automatic log expiration

param(
    [switch]$DryRun = $false,
    [switch]$Remove = $false
)

Write-Host "🗄️  MongoDB Log Retention Management" -ForegroundColor Cyan
Write-Host "====================================`n" -ForegroundColor Cyan

$mongoContainer = "phpfrarm_mongodb"
$mongoUser = "admin"
$mongoPass = "mongo_password_change_me"
$mongoDb = "phpfrarm_logs"

# Check MongoDB status
Write-Host "🔍 Checking MongoDB status..." -ForegroundColor Yellow
$containerStatus = docker ps --filter "name=$mongoContainer" --format "{{.Status}}"

if (-not $containerStatus -or $containerStatus -notmatch "Up") {
    Write-Host "❌ MongoDB container is not running!" -ForegroundColor Red
    Write-Host "Start it with: docker-compose up -d mongodb`n" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ MongoDB is running`n" -ForegroundColor Green

# Define retention policies (in seconds)
$retentionPolicies = @{
    'application_logs' = @{
        'days' = 30
        'seconds' = 2592000  # 30 days
        'description' = 'Application logs (DEBUG, INFO, WARNING, ERROR)'
    }
    'access_logs' = @{
        'days' = 90
        'seconds' = 7776000  # 90 days
        'description' = 'HTTP access logs'
    }
    'security_logs' = @{
        'days' = 180
        'seconds' = 15552000  # 180 days
        'description' = 'Security events and threats'
    }
    'audit_logs' = @{
        'days' = 365
        'seconds' = 31536000  # 1 year
        'description' = 'Audit trail for compliance'
    }
}

if ($Remove) {
    Write-Host "🗑️  REMOVING TTL Indexes..." -ForegroundColor Red
    Write-Host "==========================`n" -ForegroundColor Red
    
    if (-not $DryRun) {
        Write-Host "⚠️  WARNING: This will remove automatic log expiration!" -ForegroundColor Yellow
        Write-Host "Logs will NOT be deleted automatically anymore.`n" -ForegroundColor Yellow
        
        $confirm = Read-Host "Are you sure you want to remove TTL indexes? (yes/no)"
        if ($confirm -ne "yes") {
            Write-Host "❌ Operation cancelled`n" -ForegroundColor Yellow
            exit 0
        }
    }
    
    foreach ($collection in $retentionPolicies.Keys) {
        $policy = $retentionPolicies[$collection]
        Write-Host "Removing TTL index from: $collection" -ForegroundColor Yellow
        
        if (-not $DryRun) {
            $dropCmd = "db.$collection.dropIndex('timestamp_1')"
            $result = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $dropCmd 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "  ✅ Index removed successfully" -ForegroundColor Green
            } else {
                Write-Host "  ⚠️  No TTL index found (may not exist)" -ForegroundColor Yellow
            }
        } else {
            Write-Host "  [DRY RUN] Would drop index: timestamp_1" -ForegroundColor Cyan
        }
    }
    
    Write-Host "`n✅ TTL index removal complete!`n" -ForegroundColor Green
    exit 0
}

# Create TTL indexes
Write-Host "📋 Retention Policy Configuration:" -ForegroundColor Cyan
Write-Host "==================================`n" -ForegroundColor Cyan

foreach ($collection in $retentionPolicies.Keys) {
    $policy = $retentionPolicies[$collection]
    Write-Host ("  {0,-20} : {1,3} days - {2}" -f $collection, $policy.days, $policy.description) -ForegroundColor White
}

Write-Host "`n"

if ($DryRun) {
    Write-Host "🔍 DRY RUN MODE - No changes will be made`n" -ForegroundColor Yellow
} else {
    Write-Host "⚠️  WARNING: This will enable automatic log deletion!" -ForegroundColor Yellow
    Write-Host "Logs older than the retention period will be deleted automatically.`n" -ForegroundColor Yellow
    
    $confirm = Read-Host "Do you want to proceed? (yes/no)"
    if ($confirm -ne "yes") {
        Write-Host "❌ Operation cancelled`n" -ForegroundColor Yellow
        exit 0
    }
    Write-Host ""
}

Write-Host "🔧 Creating TTL Indexes..." -ForegroundColor Cyan
Write-Host "=========================`n" -ForegroundColor Cyan

$successCount = 0
$errorCount = 0

foreach ($collection in $retentionPolicies.Keys) {
    $policy = $retentionPolicies[$collection]
    Write-Host "Processing: $collection ($($policy.days) days retention)" -ForegroundColor Yellow
    
    if ($DryRun) {
        Write-Host "  [DRY RUN] Would create TTL index with expireAfterSeconds: $($policy.seconds)" -ForegroundColor Cyan
        $successCount++
    } else {
        # First, check if index already exists
        $checkCmd = "db.$collection.getIndexes().filter(idx => idx.name === 'timestamp_1' || idx.expireAfterSeconds !== undefined)"
        $existingIndex = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $checkCmd 2>&1
        
        if ($existingIndex -match "expireAfterSeconds") {
            Write-Host "  ⚠️  TTL index already exists. Dropping and recreating..." -ForegroundColor Yellow
            $dropCmd = "db.$collection.dropIndex('timestamp_1')"
            docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $dropCmd 2>&1 | Out-Null
        }
        
        # Create the TTL index
        $indexCmd = "db.$collection.createIndex({ timestamp: 1 }, { expireAfterSeconds: $($policy.seconds), name: 'ttl_timestamp' })"
        $result = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $indexCmd 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  ✅ TTL index created successfully" -ForegroundColor Green
            $successCount++
            
            # Verify index
            $verifyCmd = "db.$collection.getIndexes().filter(idx => idx.name === 'ttl_timestamp')[0]"
            $indexInfo = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $verifyCmd 2>&1
            
            if ($indexInfo -match "expireAfterSeconds") {
                Write-Host "  ✅ Index verified and active" -ForegroundColor Green
            }
        } else {
            Write-Host "  ❌ Failed to create TTL index" -ForegroundColor Red
            Write-Host "  Error: $result" -ForegroundColor Red
            $errorCount++
        }
    }
    Write-Host ""
}

# Summary
Write-Host "📊 Summary:" -ForegroundColor Cyan
Write-Host "==========`n" -ForegroundColor Cyan
Write-Host ("  ✅ Successful: {0}" -f $successCount) -ForegroundColor Green
if ($errorCount -gt 0) {
    Write-Host ("  ❌ Failed: {0}" -f $errorCount) -ForegroundColor Red
}

if (-not $DryRun -and $successCount -gt 0) {
    Write-Host "`n📌 Important Notes:" -ForegroundColor Yellow
    Write-Host "==================`n" -ForegroundColor Yellow
    Write-Host "1. TTL indexes check for expired documents every 60 seconds" -ForegroundColor White
    Write-Host "2. Deletion happens in the background and may take time" -ForegroundColor White
    Write-Host "3. Documents are deleted based on their 'timestamp' field" -ForegroundColor White
    Write-Host "4. Current retention policies:" -ForegroundColor White
    Write-Host "   - Application logs: 30 days" -ForegroundColor Gray
    Write-Host "   - Access logs: 90 days" -ForegroundColor Gray
    Write-Host "   - Security logs: 180 days" -ForegroundColor Gray
    Write-Host "   - Audit logs: 365 days" -ForegroundColor Gray
    
    Write-Host "`n🔍 To check TTL index status:" -ForegroundColor Cyan
    Write-Host "   docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --eval 'db.application_logs.getIndexes()'" -ForegroundColor Gray
    
    Write-Host "`n🗑️  To remove TTL indexes:" -ForegroundColor Cyan
    Write-Host "   .\setup_log_retention.ps1 -Remove" -ForegroundColor Gray
}

if ($DryRun) {
    Write-Host "`n💡 Run without -DryRun to apply changes:`n   .\setup_log_retention.ps1`n" -ForegroundColor Cyan
} else {
    Write-Host "`n✅ Log retention setup complete!`n" -ForegroundColor Green
}
