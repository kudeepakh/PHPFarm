# System Logs Statistics
# Quick overview of all log collections

Write-Host "📊 PHPFrarm Logs Statistics Dashboard" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$mongoContainer = "phpfrarm_mongodb"
$mongoUser = "admin"
$mongoPass = "mongo_password_change_me"
$mongoDb = "phpfrarm_logs"

# Check if MongoDB is running
Write-Host "🔍 Checking MongoDB status..." -ForegroundColor Yellow
$containerStatus = docker ps --filter "name=$mongoContainer" --format "{{.Status}}"

if ($containerStatus -match "Up") {
    Write-Host "✅ MongoDB is running ($containerStatus)`n" -ForegroundColor Green
} else {
    Write-Host "❌ MongoDB container is not running!" -ForegroundColor Red
    Write-Host "Start it with: docker-compose up -d mongodb`n" -ForegroundColor Yellow
    exit
}

# Get collection counts
Write-Host "📦 Collection Statistics:" -ForegroundColor Cyan
Write-Host "========================`n" -ForegroundColor Cyan

$collections = @('application_logs', 'access_logs', 'security_logs', 'audit_logs')
$totalLogs = 0

foreach ($coll in $collections) {
    $count = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval "db.$coll.countDocuments({})"
    $totalLogs += [int]$count
    
    $collName = $coll -replace '_logs', ''
    $collName = $collName.Substring(0,1).ToUpper() + $collName.Substring(1)
    
    Write-Host ("{0,-15} : {1,10:N0} logs" -f $collName, [int]$count) -ForegroundColor White
}

Write-Host ("{0,-15} : {1,10:N0} logs" -f "TOTAL", $totalLogs) -ForegroundColor Green

# Get log level breakdown for application logs
Write-Host "`n📊 Application Logs by Level:" -ForegroundColor Cyan
Write-Host "=============================`n" -ForegroundColor Cyan

$aggregateCmd = 'db.application_logs.aggregate([{\"$group\": {\"_id\": \"$level\", \"count\": {\"$sum\": 1}}}, {\"$sort\": {\"count\": -1}}]).forEach(function(doc) { print(doc._id + \": \" + doc.count); })'

$levels = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $aggregateCmd

foreach ($line in $levels -split "`n") {
    if ($line -match '(\w+):\s*(\d+)') {
        $level = $matches[1]
        $count = [int]$matches[2]
        $percentage = ($count / [int](docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval "db.application_logs.countDocuments({})")) * 100
        
        $color = switch ($level) {
            'ERROR'   { 'Red' }
            'WARNING' { 'Yellow' }
            'INFO'    { 'Cyan' }
            'DEBUG'   { 'Gray' }
            default   { 'White' }
        }
        
        Write-Host ("{0,-10} : {1,10:N0} ({2,5:N1}%)" -f $level, $count, $percentage) -ForegroundColor $color
    }
}

# Get recent activity (last 24 hours)
Write-Host "`n⏰ Recent Activity (Last 24 Hours):" -ForegroundColor Cyan
Write-Host "===================================`n" -ForegroundColor Cyan

$oneDayAgo = (Get-Date).AddDays(-1)
$isoDate = "ISODate(`"$($oneDayAgo.ToString('yyyy-MM-ddTHH:mm:ss'))Z`")"

foreach ($coll in $collections) {
    $count = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval "db.$coll.countDocuments({timestamp: {`$gte: $isoDate}})"
    
    $collName = $coll -replace '_logs', ''
    $collName = $collName.Substring(0,1).ToUpper() + $collName.Substring(1)
    
    Write-Host ("{0,-15} : {1,10:N0} logs" -f $collName, [int]$count) -ForegroundColor White
}

# Get top 5 recent errors
Write-Host "`n🚨 Most Recent Errors (Last 5):" -ForegroundColor Red
Write-Host "================================`n" -ForegroundColor Red

$errorCmd = 'db.application_logs.find({level: \"ERROR\"}).sort({timestamp: -1}).limit(5).forEach(function(doc) { print(doc.timestamp.toString() + \" | \" + doc.message.substring(0, 80)); })'

$errors = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval $errorCmd

if ($errors) {
    foreach ($line in $errors -split "`n") {
        if ($line.Trim() -ne '') {
            Write-Host $line -ForegroundColor Yellow
        }
    }
} else {
    Write-Host "✅ No recent errors found!" -ForegroundColor Green
}

# Database size
Write-Host "`n💾 Database Storage:" -ForegroundColor Cyan
Write-Host "===================`n" -ForegroundColor Cyan

$dbStats = docker exec $mongoContainer mongosh -u $mongoUser -p $mongoPass --authenticationDatabase admin $mongoDb --quiet --eval "db.stats()" | ConvertFrom-Json

if ($dbStats) {
    $sizeGB = [math]::Round($dbStats.dataSize / 1GB, 2)
    $storageSizeGB = [math]::Round($dbStats.storageSize / 1GB, 2)
    
    Write-Host ("Data Size      : {0,8:N2} GB" -f $sizeGB) -ForegroundColor White
    Write-Host ("Storage Size   : {0,8:N2} GB" -f $storageSizeGB) -ForegroundColor White
    Write-Host ("Avg Object Size: {0,8:N0} bytes" -f $dbStats.avgObjSize) -ForegroundColor White
}

Write-Host "`n✅ Statistics complete!`n" -ForegroundColor Green
Write-Host "💡 To view logs in frontend: http://localhost:3900/logs" -ForegroundColor Cyan
Write-Host "💡 To query specific logs: .\query_logs.ps1 -Collection application -Level ERROR`n" -ForegroundColor Cyan
