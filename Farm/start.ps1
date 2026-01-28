# PHPFrarm Quick Start Script
# Run this from the /farm directory

Write-Host "🚀 PHPFrarm - Enterprise API Framework Setup" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# Check if Docker is running
Write-Host "📋 Checking prerequisites..." -ForegroundColor Yellow
$dockerRunning = docker info 2>$null
if (-not $dockerRunning) {
    Write-Host "❌ Docker is not running. Please start Docker Desktop first." -ForegroundColor Red
    exit 1
}
Write-Host "✅ Docker is running" -ForegroundColor Green

# Check if .env exists
if (-not (Test-Path ".env")) {
    Write-Host "❌ .env file not found. Creating from template..." -ForegroundColor Red
    exit 1
}
Write-Host "✅ .env file found" -ForegroundColor Green
Write-Host ""

# Prompt for action
Write-Host "Select an action:" -ForegroundColor Cyan
Write-Host "1. First-time setup (build and start all services)"
Write-Host "2. Start existing services"
Write-Host "3. Stop all services"
Write-Host "4. Rebuild and restart"
Write-Host "5. View logs"
Write-Host "6. Clean reset (removes all data)"
Write-Host ""

$choice = Read-Host "Enter your choice (1-6)"

switch ($choice) {
    "1" {
        Write-Host ""
        Write-Host "🔨 Building and starting all services..." -ForegroundColor Yellow
        docker-compose up -d --build
        Write-Host ""
        Write-Host "✅ Setup complete!" -ForegroundColor Green
        Write-Host ""
        Write-Host "📍 Access your services:" -ForegroundColor Cyan
        Write-Host "   Frontend:   http://localhost:3000" -ForegroundColor White
        Write-Host "   Backend:    http://localhost:8080" -ForegroundColor White
        Write-Host "   Health:     http://localhost:8080/health" -ForegroundColor White
        Write-Host ""
        Write-Host "📊 Check status: docker-compose ps" -ForegroundColor Yellow
        Write-Host "📝 View logs:    docker-compose logs -f" -ForegroundColor Yellow
    }
    "2" {
        Write-Host ""
        Write-Host "▶️  Starting services..." -ForegroundColor Yellow
        docker-compose up -d
        docker-compose ps
    }
    "3" {
        Write-Host ""
        Write-Host "⏹️  Stopping services..." -ForegroundColor Yellow
        docker-compose down
        Write-Host "✅ All services stopped" -ForegroundColor Green
    }
    "4" {
        Write-Host ""
        Write-Host "🔄 Rebuilding and restarting..." -ForegroundColor Yellow
        docker-compose down
        docker-compose up -d --build
        Write-Host "✅ Rebuild complete" -ForegroundColor Green
    }
    "5" {
        Write-Host ""
        Write-Host "📝 Showing logs (Ctrl+C to exit)..." -ForegroundColor Yellow
        docker-compose logs -f
    }
    "6" {
        Write-Host ""
        $confirm = Read-Host "⚠️  This will delete ALL data. Are you sure? (yes/no)"
        if ($confirm -eq "yes") {
            Write-Host "🗑️  Cleaning up..." -ForegroundColor Yellow
            docker-compose down -v
            Write-Host "✅ Clean reset complete" -ForegroundColor Green
        } else {
            Write-Host "❌ Cancelled" -ForegroundColor Red
        }
    }
    default {
        Write-Host "❌ Invalid choice" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "📚 For more information, see README.md" -ForegroundColor Cyan
