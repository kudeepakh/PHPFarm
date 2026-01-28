Set-Location "C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm"

$specPath = "C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\docs\architecture\openapi.json"
$outPath = "C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\docs\architecture\API_DETAILS.md"

$spec = Get-Content $specPath -Raw | ConvertFrom-Json
$globalSecurity = $spec.security

$md = @()
$md += "# API Details"
$md += "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$md += ""
$md += "## Required Headers"
$md += "- X-Correlation-Id (ULID, required)"
$md += "- X-Transaction-Id (ULID, required)"
$md += "- X-Request-Id (ULID, required)"
$md += "- Accept: application/json"
$md += "- Authorization: Bearer <token> (required for protected endpoints)"
$md += ""
$md += "## Standard Response Envelope"
$md += "- success: boolean"
$md += "- message: string"
$md += "- data: object|array|null"
$md += "- meta: object (timestamp, api_version, locale, pagination if applicable)"
$md += "- trace: object (correlation_id, transaction_id, request_id)"
$md += ""

$paths = $spec.paths.PSObject.Properties | Sort-Object Name
foreach ($p in $paths) {
  $path = $p.Name
  $methods = $p.Value.PSObject.Properties | Where-Object { $_.Name -in @('get','post','put','patch','delete') } | Sort-Object Name
  foreach ($m in $methods) {
    $op = $m.Value
    $method = $m.Name.ToUpper()

    $md += "## $method $path"
    if ($op.summary) { $md += "**Summary:** $($op.summary)" }
    if ($op.description) { $md += "**Description:** $($op.description)" }
    if ($op.tags) { $md += "**Tags:** $([string]::Join(', ', $op.tags))" }

    $security = $op.security
    if (-not $security -and $globalSecurity) { $security = $globalSecurity }
    $md += "**Auth:** " + ($(if ($security) { 'Required' } else { 'None' }))
    $md += ""

    if ($op.parameters) {
      $md += "**Parameters:**"
      foreach ($param in $op.parameters) {
        $required = if ($param.required) { 'required' } else { 'optional' }
        $schemaType = if ($param.schema.type) { $param.schema.type } else { 'object' }
        $md += "- $($param.`in`) $($param.name) ($schemaType, $required)"
      }
      $md += ""
    }

    if ($op.requestBody) {
      $md += "**Request Body:**"
      $content = $op.requestBody.content.PSObject.Properties
      foreach ($c in $content) {
        $schemaRef = $c.Value.schema.`$ref
        $schemaType = $c.Value.schema.type
        if ($schemaRef) { $md += "- $($c.Name): $schemaRef" }
        elseif ($schemaType) { $md += "- $($c.Name): $schemaType" }
        else { $md += "- $($c.Name): schema" }
      }
      $md += ""
    }

    if ($op.responses) {
      $md += "**Responses:**"
      foreach ($r in ($op.responses.PSObject.Properties | Sort-Object Name)) {
        $desc = $r.Value.description
        $md += "- $($r.Name): $desc"
        $content = $r.Value.content
        if ($content) {
          foreach ($ct in $content.PSObject.Properties) {
            $schemaRef = $ct.Value.schema.`$ref
            $schemaType = $ct.Value.schema.type
            if ($schemaRef) { $md += "  - $($ct.Name): $schemaRef" }
            elseif ($schemaType) { $md += "  - $($ct.Name): $schemaType" }
            else { $md += "  - $($ct.Name): schema" }
          }
        }
      }
      $md += ""
    }

    $md += "---"
    $md += ""
  }
}

$md | Set-Content -Path $outPath -Encoding UTF8
Write-Output "API details written to $outPath"
