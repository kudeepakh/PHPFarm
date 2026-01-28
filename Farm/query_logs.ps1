# System Logs Query Helper
# Quick script to query MongoDB logs from command line

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet('application', 'access', 'security', 'audit', 'all')]
    [string]$Collection = 'application',
    
    [Parameter(Mandatory=$false)]
    [ValidateSet('ERROR', 'WARNING', 'INFO', 'DEBUG', 'ALL')]
    [string]$Level = 'ALL',
    
    [Parameter(Mandatory=$false)]
    [int]$Limit = 10,
    
    [Parameter(Mandatory=$false)]
    [string]$Search = '',
    
    [Parameter(Mandatory=$false)]
    [int]$LastHours = 0
)

Write-Host "🔍 PHPFrarm Logs Query Tool" -ForegroundColor Cyan
Write-Host "================================`n" -ForegroundColor Cyan

# MongoDB connection details
$mongoContainer = "phpfrarm_mongodb"
$mongoUser = "admin"
$mongoPass = "mongo_password_change_me"
$mongoDb = "phpfrarm_logs"

# Build collection list
$collections = @()
if ($Collection -eq 'all') {
    $collections = @('application_logs', 'access_logs', 'security_logs', 'audit_logs')
} else {
    $collections = @("${Collection}_logs")
}

# Build query filter
$filter = @{}

# Add level filter
if ($Level -ne 'ALL') {
    $filter['level'] = $Level
}

# Add time filter
if ($LastHours -gt 0) {
    $timestamp = (Get-Date).AddHours(-$LastHours)
    $isoDate = "ISODate(`"$($timestamp.ToString('yyyy-MM-ddTHH:mm:ss'))Z`")"
    $filter['timestamp'] = @{ '$gte' = $isoDate }
}

# Add search filter
if ($Search -ne '') {
    $filter['message'] = @{ '$regex' = $Search; '$options' = 'i' }
}

# Convert filter to JSON
$filterJson = if ($filter.Count -gt 0) { 
    $filter | ConvertTo-Json -Compress -Depth 10
} else { 
    '{}' 
}

# Query each collection
foreach ($coll in $collections) {
    Write-Host "`n📊 Collection: $coll" -ForegroundColor Yellow
    Write-Host "=====================================`n" -ForegroundColor Yellow
    
    # Get count
    $countCmd = "db.$coll.countDocuments($filterJson)"
    $count = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $countCmd
    
    Write-Host "Total matching logs: $count" -ForegroundColor Green
    
    if ([int]$count -gt 0) {
        # Get logs
        $findCmd = "db.$coll.find($filterJson).sort({timestamp: -1}).limit($Limit).forEach(function(doc) { printjson(doc); })"
        
        Write-Host "`nShowing last $Limit logs:`n" -ForegroundColor Cyan
        
        docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $findCmd
    } else {
        Write-Host "No logs found matching the criteria.`n" -ForegroundColor Yellow
    }
}

Write-Host "`n✅ Query complete!" -ForegroundColor Green
Write-Host "`nTip: Use -LastHours to filter recent logs (e.g., -LastHours 1)" -ForegroundColor Cyan
Write-Host "Example: .\query_logs.ps1 -Collection application -Level ERROR -LastHours 24 -Limit 5`n" -ForegroundColor Cyan
