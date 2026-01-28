# PHPFrarm - GitHub Preparation Cleanup Script
# This script helps you clean up unnecessary files before pushing to GitHub

Write-Host "🚀 PHPFrarm - GitHub Preparation Cleanup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$rootPath = $PSScriptRoot

# Function to safely remove item
function Remove-ItemSafely {
    param(
        [string]$Path,
        [string]$Description
    )
    
    if (Test-Path $Path) {
        Write-Host "🗑️  Removing: $Description" -ForegroundColor Yellow
        try {
            Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop
            Write-Host "   ✅ Removed successfully" -ForegroundColor Green
        } catch {
            Write-Host "   ❌ Failed to remove: $_" -ForegroundColor Red
        }
    } else {
        Write-Host "⏭️  Skipping: $Description (not found)" -ForegroundColor Gray
    }
}

# 1. Remove external project folder
Write-Host ""
Write-Host "Step 1: Removing external projects..." -ForegroundColor Cyan
Remove-ItemSafely -Path "$rootPath\argon-dashboard-tailwind-1.0.1" -Description "External dashboard project"

# 2. Remove PHP debug scripts
Write-Host ""
Write-Host "Step 2: Removing debug scripts..." -ForegroundColor Cyan
Remove-ItemSafely -Path "$rootPath\Farm\backend\check_routes.php" -Description "check_routes.php"
Remove-ItemSafely -Path "$rootPath\Farm\backend\debug_routes.php" -Description "debug_routes.php"

# 3. Check for .env files that shouldn't be tracked
Write-Host ""
Write-Host "Step 3: Checking for .env files..." -ForegroundColor Cyan
$envFiles = @(
    "$rootPath\Farm\.env",
    "$rootPath\Farm\backend\.env",
    "$rootPath\Farm\backend\.env.production",
    "$rootPath\Farm\frontend\.env"
)

$foundEnvFiles = $false
foreach ($envFile in $envFiles) {
    if (Test-Path $envFile) {
        $foundEnvFiles = $true
        Write-Host "   ⚠️  Found: $envFile" -ForegroundColor Yellow
        Write-Host "      Make sure this is not tracked in git!" -ForegroundColor Yellow
    }
}

if (-not $foundEnvFiles) {
    Write-Host "   ✅ No .env files found (good!)" -ForegroundColor Green
}

# 4. Check for vendor and node_modules
Write-Host ""
Write-Host "Step 4: Checking for dependency directories..." -ForegroundColor Cyan
$depDirs = @(
    "$rootPath\Farm\backend\vendor",
    "$rootPath\Farm\frontend\node_modules"
)

$foundDepDirs = $false
foreach ($dir in $depDirs) {
    if (Test-Path $dir) {
        $foundDepDirs = $true
        $size = (Get-ChildItem $dir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
        Write-Host "   ⚠️  Found: $dir ($([math]::Round($size, 2)) MB)" -ForegroundColor Yellow
        Write-Host "      This will be excluded by .gitignore" -ForegroundColor Gray
    }
}

if (-not $foundDepDirs) {
    Write-Host "   ✅ No dependency directories found" -ForegroundColor Green
}

# 5. Check git status
Write-Host ""
Write-Host "Step 5: Checking git status..." -ForegroundColor Cyan
Push-Location $rootPath
if (Test-Path ".git") {
    Write-Host "   Running git status..." -ForegroundColor Gray
    git status --short
    
    Write-Host ""
    Write-Host "   Checking for large files..." -ForegroundColor Gray
    $largeFiles = git ls-files | Where-Object { 
        Test-Path $_ 
    } | Where-Object {
        (Get-Item $_).Length -gt 1MB
    } | ForEach-Object {
        $size = (Get-Item $_).Length / 1MB
        [PSCustomObject]@{
            File = $_
            Size = [math]::Round($size, 2)
        }
    }
    
    if ($largeFiles) {
        Write-Host ""
        Write-Host "   ⚠️  Large files found:" -ForegroundColor Yellow
        $largeFiles | Format-Table -AutoSize
    } else {
        Write-Host "   ✅ No large files in git" -ForegroundColor Green
    }
} else {
    Write-Host "   ℹ️  Git repository not initialized yet" -ForegroundColor Blue
}
Pop-Location

# 6. Verify .env.example files exist
Write-Host ""
Write-Host "Step 6: Verifying .env.example files..." -ForegroundColor Cyan
$exampleFiles = @(
    "$rootPath\Farm\.env.example",
    "$rootPath\Farm\frontend\.env.example"
)

foreach ($file in $exampleFiles) {
    if (Test-Path $file) {
        Write-Host "   ✅ Found: $(Split-Path $file -Leaf)" -ForegroundColor Green
    } else {
        Write-Host "   ❌ Missing: $(Split-Path $file -Leaf)" -ForegroundColor Red
    }
}

# 7. Check for reference docs that can be deleted
Write-Host ""
Write-Host "Step 7: Optional reference docs..." -ForegroundColor Cyan
Write-Host "   These files are for reference and can be deleted after cleanup:" -ForegroundColor Gray
Write-Host "   - CLEANUP_CHECKLIST.md" -ForegroundColor Gray
Write-Host "   - PUBLISH_TO_GITHUB.md" -ForegroundColor Gray
Write-Host "   - READY_FOR_GITHUB.md" -ForegroundColor Gray
Write-Host "   - cleanup_for_github.ps1 (this script)" -ForegroundColor Gray

# Summary
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "✅ Cleanup Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "1. Review READY_FOR_GITHUB.md for summary" -ForegroundColor White
Write-Host "2. Follow PUBLISH_TO_GITHUB.md for publishing steps" -ForegroundColor White
Write-Host "3. Update placeholders in README.md and SECURITY.md" -ForegroundColor White
Write-Host "4. Test with: cd Farm; docker-compose up -d" -ForegroundColor White
Write-Host "5. Create GitHub repository and push!" -ForegroundColor White
Write-Host ""
Write-Host "🎉 Your repository is ready for GitHub!" -ForegroundColor Green
Write-Host ""

# Wait for user
Read-Host "Press Enter to exit"
