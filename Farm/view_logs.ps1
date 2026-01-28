#!/usr/bin/env pwsh
# PHPFrarm Log Viewer - Interactive MongoDB Log Query Tool
# Usage: .\view_logs.ps1

param(
    [string]$Level = "",
    [string]$Collection = "application_logs",
    [string]$StartDate = "",
    [string]$EndDate = "",
    [int]$Limit = 20,
    [string]$Search = "",
    [string]$CorrelationId = "",
    [switch]$Interactive = $false
)

$MONGO_HOST = "localhost"
$MONGO_PORT = "27019"
$MONGO_USER = "admin"
$MONGO_PASS = "mongo_password_change_me"
$MONGO_DB = "phpfrarm_logs"

function Show-Menu {
    Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║         PHPFrarm Log Viewer - MongoDB Query Tool          ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  [1] View Application Logs" -ForegroundColor Green
    Write-Host "  [2] View Access Logs" -ForegroundColor Green
    Write-Host "  [3] View Security Logs" -ForegroundColor Green
    Write-Host "  [4] View Audit Logs" -ForegroundColor Green
    Write-Host "  [5] View ERROR logs only" -ForegroundColor Red
    Write-Host "  [6] View WARNING logs only" -ForegroundColor Yellow
    Write-Host "  [7] View INFO logs only" -ForegroundColor Blue
    Write-Host "  [8] View DEBUG logs only" -ForegroundColor Gray
    Write-Host "  [9] Filter by Date Range" -ForegroundColor Magenta
    Write-Host "  [10] Search by Keyword" -ForegroundColor Magenta
    Write-Host "  [11] Search by Correlation ID" -ForegroundColor Magenta
    Write-Host "  [12] Log Statistics" -ForegroundColor Cyan
    Write-Host "  [0] Exit" -ForegroundColor Red
    Write-Host ""
}

function Get-LogCount {
    param([string]$CollectionName)
    
    $query = "db.$CollectionName.countDocuments()"
    $result = docker exec phpfrarm_mongodb mongosh -u $MONGO_USER -p $MONGO_PASS --authenticationDatabase admin $MONGO_DB --quiet --eval $query 2>$null
    
    return $result
}

function Get-LogStatistics {
    Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║                    Log Statistics                          ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    
    # Collection counts
    $appCount = Get-LogCount "application_logs"
    $accessCount = Get-LogCount "access_logs"
    $securityCount = Get-LogCount "security_logs"
    $auditCount = Get-LogCount "audit_logs"
    
    Write-Host "  Collection Counts:" -ForegroundColor Yellow
    Write-Host "    • Application Logs: $appCount" -ForegroundColor White
    Write-Host "    • Access Logs:      $accessCount" -ForegroundColor White
    Write-Host "    • Security Logs:    $securityCount" -ForegroundColor White
    Write-Host "    • Audit Logs:       $auditCount" -ForegroundColor White
    Write-Host ""
    
    # Log level breakdown for application logs
    $aggregateQuery = @"
db.application_logs.aggregate([
    {`$group: {_id: `"`$level`", count: {`$sum: 1}}},
    {`$sort: {count: -1}}
])
"@
    
    Write-Host "  Application Log Levels:" -ForegroundColor Yellow
    $result = docker exec phpfrarm_mongodb mongosh -u $MONGO_USER -p $MONGO_PASS --authenticationDatabase admin $MONGO_DB --quiet --eval $aggregateQuery 2>$null
    
    if ($result) {
        Write-Host $result -ForegroundColor White
    }
    
    # Recent activity (last hour)
    $oneHourAgo = (Get-Date).AddHours(-1).ToString("yyyy-MM-ddTHH:mm:ss")
    $recentQuery = "db.application_logs.countDocuments({timestamp: {`$gte: new Date('$oneHourAgo')}})"
    $recentCount = docker exec phpfrarm_mongodb mongosh -u $MONGO_USER -p $MONGO_PASS --authenticationDatabase admin $MONGO_DB --quiet --eval $recentQuery 2>$null
    
    Write-Host ""
    Write-Host "  Activity (Last Hour): $recentCount logs" -ForegroundColor Green
    Write-Host ""
}

function Query-Logs {
    param(
        [string]$CollectionName,
        [hashtable]$Filter,
        [int]$LimitCount,
        [switch]$Detailed
    )
    
    # Build MongoDB query
    $filterJson = "{}"
    if ($Filter.Count -gt 0) {
        $filterParts = @()
        
        foreach ($key in $Filter.Keys) {
            $value = $Filter[$key]
            
            if ($key -eq "timestamp" -and $value -is [hashtable]) {
                $dateParts = @()
                if ($value.ContainsKey('$gte')) {
                    $dateParts += "`"`$gte`": new Date(`"$($value['$gte'])`")"
                }
                if ($value.ContainsKey('$lte')) {
                    $dateParts += "`"`$lte`": new Date(`"$($value['$lte'])`")"
                }
                $filterParts += "`"$key`": {$($dateParts -join ', ')}"
            }
            elseif ($key -eq "message" -and $value -match '^\$regex:') {
                $regex = $value -replace '^\$regex:', ''
                $filterParts += "`"$key`": {`"`$regex`": `"$regex`", `"`$options`": `"i`"}"
            }
            else {
                $filterParts += "`"$key`": `"$value`""
            }
        }
        
        $filterJson = "{$($filterParts -join ', ')}"
    }
    
    Write-Host "`nQuerying: $CollectionName" -ForegroundColor Cyan
    Write-Host "Filter: $filterJson" -ForegroundColor Gray
    Write-Host "Limit: $LimitCount" -ForegroundColor Gray
    Write-Host ("─" * 80) -ForegroundColor DarkGray
    
    $query = "db.$CollectionName.find($filterJson).sort({timestamp: -1}).limit($LimitCount).toArray()"
    
    $result = docker exec phpfrarm_mongodb mongosh -u $MONGO_USER -p $MONGO_PASS --authenticationDatabase admin $MONGO_DB --quiet --eval $query 2>$null
    
    if ($result) {
        # Parse and display results
        $logs = $result | ConvertFrom-Json
        
        if ($logs.Count -eq 0) {
            Write-Host "`nNo logs found matching the criteria.`n" -ForegroundColor Yellow
            return
        }
        
        Write-Host "`nFound $($logs.Count) logs:`n" -ForegroundColor Green
        
        foreach ($log in $logs) {
            $timestamp = $log.timestamp.'$date'
            if ($timestamp) {
                $dateObj = [DateTime]::Parse($timestamp)
                $formattedDate = $dateObj.ToString("yyyy-MM-dd HH:mm:ss")
            } else {
                $formattedDate = "N/A"
            }
            
            $levelColor = switch ($log.level) {
                "ERROR" { "Red" }
                "WARNING" { "Yellow" }
                "INFO" { "Green" }
                "DEBUG" { "Gray" }
                default { "White" }
            }
            
            Write-Host "[$formattedDate] " -NoNewline -ForegroundColor DarkGray
            Write-Host "[$($log.level)]" -NoNewline -ForegroundColor $levelColor
            Write-Host " $($log.message)" -ForegroundColor White
            
            if ($Detailed) {
                if ($log.correlation_id) {
                    Write-Host "  Correlation ID: $($log.correlation_id)" -ForegroundColor Cyan
                }
                if ($log.transaction_id) {
                    Write-Host "  Transaction ID: $($log.transaction_id)" -ForegroundColor Cyan
                }
                if ($log.server) {
                    Write-Host "  Endpoint: $($log.server.method) $($log.server.uri)" -ForegroundColor Magenta
                    Write-Host "  IP: $($log.server.ip)" -ForegroundColor DarkGray
                }
                if ($log.context -and $log.context -ne "{}") {
                    Write-Host "  Context:" -ForegroundColor Yellow
                    
                    # Handle error context specially
                    if ($log.context.error_message) {
                        Write-Host "    Error: $($log.context.error_message)" -ForegroundColor Red
                        if ($log.context.file) {
                            Write-Host "    File: $($log.context.file):$($log.context.line)" -ForegroundColor DarkGray
                        }
                    } else {
                        Write-Host "    $($log.context | ConvertTo-Json -Depth 2 -Compress)" -ForegroundColor DarkGray
                    }
                }
                Write-Host ("─" * 80) -ForegroundColor DarkGray
            }
            Write-Host ""
        }
    } else {
        Write-Host "`nError querying logs. Check Docker container status.`n" -ForegroundColor Red
    }
}

# Main script logic
if ($Interactive -or ($Level -eq "" -and $Collection -eq "application_logs" -and $StartDate -eq "" -and $Search -eq "" -and $CorrelationId -eq "")) {
    # Interactive mode
    do {
        Show-Menu
        $choice = Read-Host "Select option"
        
        switch ($choice) {
            "1" {
                Query-Logs -CollectionName "application_logs" -Filter @{} -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "2" {
                Query-Logs -CollectionName "access_logs" -Filter @{} -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "3" {
                Query-Logs -CollectionName "security_logs" -Filter @{} -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "4" {
                Query-Logs -CollectionName "audit_logs" -Filter @{} -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "5" {
                Query-Logs -CollectionName "application_logs" -Filter @{ level = "ERROR" } -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "6" {
                Query-Logs -CollectionName "application_logs" -Filter @{ level = "WARNING" } -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "7" {
                Query-Logs -CollectionName "application_logs" -Filter @{ level = "INFO" } -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "8" {
                Query-Logs -CollectionName "application_logs" -Filter @{ level = "DEBUG" } -LimitCount 20 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "9" {
                Write-Host "`nEnter date range (ISO format: YYYY-MM-DDTHH:MM:SS)" -ForegroundColor Cyan
                $start = Read-Host "Start date"
                $end = Read-Host "End date"
                
                $dateFilter = @{}
                if ($start) { $dateFilter['$gte'] = $start }
                if ($end) { $dateFilter['$lte'] = $end }
                
                Query-Logs -CollectionName "application_logs" -Filter @{ timestamp = $dateFilter } -LimitCount 50 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "10" {
                $keyword = Read-Host "`nEnter search keyword"
                Query-Logs -CollectionName "application_logs" -Filter @{ message = "`$regex:$keyword" } -LimitCount 50 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "11" {
                $corrId = Read-Host "`nEnter Correlation ID"
                Query-Logs -CollectionName "application_logs" -Filter @{ correlation_id = $corrId } -LimitCount 100 -Detailed
                Read-Host "`nPress Enter to continue"
            }
            "12" {
                Get-LogStatistics
                Read-Host "`nPress Enter to continue"
            }
            "0" {
                Write-Host "`nExiting...`n" -ForegroundColor Green
                break
            }
            default {
                Write-Host "`nInvalid option. Please try again.`n" -ForegroundColor Red
                Start-Sleep -Seconds 1
            }
        }
    } while ($choice -ne "0")
} else {
    # Command-line mode
    $filter = @{}
    
    if ($Level) {
        $filter['level'] = $Level.ToUpper()
    }
    
    if ($StartDate -or $EndDate) {
        $dateFilter = @{}
        if ($StartDate) { $dateFilter['$gte'] = $StartDate }
        if ($EndDate) { $dateFilter['$lte'] = $EndDate }
        $filter['timestamp'] = $dateFilter
    }
    
    if ($Search) {
        $filter['message'] = "`$regex:$Search"
    }
    
    if ($CorrelationId) {
        $filter['correlation_id'] = $CorrelationId
    }
    
    Query-Logs -CollectionName $Collection -Filter $filter -LimitCount $Limit -Detailed
}
