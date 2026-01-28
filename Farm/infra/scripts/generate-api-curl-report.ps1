Set-Location "C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm"

$port = 8080
if (Test-Path .\.env) {
  $envLine = Get-Content .\.env | Where-Object { $_ -match '^BACKEND_PORT=' } | Select-Object -First 1
  if ($envLine) {
    $value = $envLine -replace '^BACKEND_PORT=',''
    if ($value -match '^\d+$') { $port = [int]$value }
  }
}
$baseUrl = "http://localhost:$port"

$traceHeaders = @{
  'Accept' = 'application/json';
  'X-Correlation-Id' = '01HQZK' + ([Guid]::NewGuid().ToString('N')).Substring(0,20).ToUpper();
  'X-Transaction-Id' = '01HQZK' + ([Guid]::NewGuid().ToString('N')).Substring(0,20).ToUpper();
  'X-Request-Id' = '01HQZK' + ([Guid]::NewGuid().ToString('N')).Substring(0,20).ToUpper();
}

function Invoke-Curl {
  param(
    [string]$Method,
    [string]$Path,
    [string]$Body = $null,
    [hashtable]$Headers = @{}
  )
  $headerStr = ($Headers.GetEnumerator() | ForEach-Object {
    '--header "{0}: {1}"' -f $_.Key, $_.Value
  }) -join ' '

  $bodyStr = ''
  if ($Body -ne $null) {
    $bodyEsc = $Body.Replace('"','\"')
    $bodyStr = '--header "Content-Type: application/json" --data "{0}"' -f $bodyEsc
  }

  $url = "$baseUrl$Path"
  $cmd = 'curl.exe -s -i -X {0} {1} {2} "{3}"' -f $Method, $headerStr, $bodyStr, $url
  $result = cmd /c $cmd
  return $result
}

# Fetch OpenAPI spec
$specRaw = Invoke-Curl -Method 'GET' -Path '/docs/openapi.json' -Headers $traceHeaders
$specParts = $specRaw -split "\r?\n\r?\n", 2
$specBody = if ($specParts.Length -gt 1) { $specParts[1] } else { $specRaw }
try { $spec = $specBody | ConvertFrom-Json } catch { $spec = $null }

# Attempt auth register/login for token
$token = $null
$email = "user+$(Get-Date -Format 'yyyyMMddHHmmss')@example.com"
$registerBody = (@{ email = $email; password = 'StrongPass123!' } | ConvertTo-Json -Compress)
$loginBody = (@{ identifier = $email; password = 'StrongPass123!' } | ConvertTo-Json -Compress)

$registerRaw = Invoke-Curl -Method 'POST' -Path '/api/v1/auth/register' -Body $registerBody -Headers $traceHeaders
$registerParts = $registerRaw -split "\r?\n\r?\n", 2
$registerBodyText = if ($registerParts.Length -gt 1) { $registerParts[1] } else { '' }

$loginRaw = Invoke-Curl -Method 'POST' -Path '/api/v1/auth/login' -Body $loginBody -Headers $traceHeaders
$loginParts = $loginRaw -split "\r?\n\r?\n", 2
$loginBodyText = if ($loginParts.Length -gt 1) { $loginParts[1] } else { '' }
try {
  $loginJson = $loginBodyText | ConvertFrom-Json
  $token = $loginJson.data.access_token
  if (-not $token) { $token = $loginJson.data.token }
  if (-not $token -and $loginJson.data.tokens) { $token = $loginJson.data.tokens.access }
} catch { $token = $null }

$authHeaders = @{}
$traceHeaders.Keys | ForEach-Object { $authHeaders[$_] = $traceHeaders[$_] }
if ($token) { $authHeaders['Authorization'] = "Bearer $token" }

# Build endpoint list
$endpoints = @()
if ($spec -and $spec.paths) {
  foreach ($p in $spec.paths.PSObject.Properties) {
    $path = $p.Name
    foreach ($m in $p.Value.PSObject.Properties) {
      $method = $m.Name.ToUpper()
      if ($method -in @('GET','POST','PUT','PATCH','DELETE')) {
        $endpoints += [PSCustomObject]@{ Method=$method; Path=$path }
      }
    }
  }
} else {
  $endpoints += [PSCustomObject]@{ Method='GET'; Path='/health' }
  $endpoints += [PSCustomObject]@{ Method='GET'; Path='/api/status' }
  $endpoints += [PSCustomObject]@{ Method='GET'; Path='/docs' }
}

$endpoints = $endpoints | Sort-Object Method, Path -Unique

$results = @()
foreach ($ep in $endpoints) {
  $body = $null
  if ($ep.Method -in @('POST','PUT','PATCH')) { $body = '{}' }
  $raw = Invoke-Curl -Method $ep.Method -Path $ep.Path -Body $body -Headers $authHeaders
  $parts = $raw -split "\r?\n\r?\n", 2
  $headersText = $parts[0]
  $bodyText = if ($parts.Length -gt 1) { $parts[1] } else { '' }
  $statusLine = ($headersText -split "\r?\n")[0]
  $statusCode = if ($statusLine -match 'HTTP/\d\.\d\s+(\d+)') { [int]$Matches[1] } else { 0 }
  $hasCorrelation = $headersText -match 'X-Correlation-Id:'
  $hasTransaction = $headersText -match 'X-Transaction-Id:'
  $hasRequest = $headersText -match 'X-Request-Id:'
  $bodySnippet = $bodyText
  if ($bodySnippet.Length -gt 400) { $bodySnippet = $bodySnippet.Substring(0,400) + '...' }
  $results += [PSCustomObject]@{
    Method = $ep.Method; Path = $ep.Path; Status = $statusCode;
    TraceHeaders = ($hasCorrelation -and $hasTransaction -and $hasRequest);
    Body = $bodySnippet
  }
}

$reportPath = "C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\docs\architecture\API_CURL_REPORT.md"
$md = @()
$md += "# API Curl Report"
$md += "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$md += "Base URL: $baseUrl"
$md += ""
$md += "## Auth Attempt"
$registerStatus = 0
$loginStatus = 0
$regLine = ($registerRaw -split "\r?\n")[0]
$logLine = ($loginRaw -split "\r?\n")[0]
if ($regLine -match 'HTTP/\d\.\d\s+(\d+)') { $registerStatus = [int]$Matches[1] }
if ($logLine -match 'HTTP/\d\.\d\s+(\d+)') { $loginStatus = [int]$Matches[1] }
$md += "- Register: $registerStatus"
$md += "- Login: $loginStatus"
$md += "- Token Acquired: $([bool]$token)"
$md += ""
$md += "## Endpoints"
foreach ($r in $results) {
  $md += "### $($r.Method) $($r.Path)"
  $md += "- Status: $($r.Status)"
  $md += "- Trace Headers Present: $($r.TraceHeaders)"
  $md += "- Response (truncated):"
  $md += ""
  $md += "````"
  $md += $r.Body
  $md += "````"
  $md += ""
}

$md | Set-Content -Path $reportPath -Encoding UTF8
Write-Output "Report written to $reportPath"
