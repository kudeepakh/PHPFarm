#!/bin/bash

###############################################################################
# PHPFrarm Zero-Downtime Deployment Script
#
# Features:
# - Blue-Green deployment strategy
# - Health check validation
# - Automatic rollback on failure
# - Database migration support
# - Asset deployment
#
# Usage:
#   ./deploy.sh <environment> [options]
#
# Environments: staging, production
# Options:
#   --skip-tests      Skip health checks
#   --force           Force deployment without confirmation
#   --rollback        Rollback to previous version
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-staging}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DEPLOYMENT_LOG="/var/log/phpfrarm/deployment_${TIMESTAMP}.log"

# Parse options
SKIP_TESTS=false
FORCE=false
ROLLBACK=false

for arg in "$@"; do
    case $arg in
        --skip-tests)
            SKIP_TESTS=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --rollback)
            ROLLBACK=true
            shift
            ;;
    esac
done

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$DEPLOYMENT_LOG"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$DEPLOYMENT_LOG"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$DEPLOYMENT_LOG"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$DEPLOYMENT_LOG"
}

# Cleanup on exit
cleanup() {
    if [ $? -ne 0 ]; then
        log_error "Deployment failed. Check logs at: $DEPLOYMENT_LOG"
    fi
}
trap cleanup EXIT

# Validation
validate_environment() {
    log "Validating environment: $ENVIRONMENT"
    
    if [[ "$ENVIRONMENT" != "staging" && "$ENVIRONMENT" != "production" ]]; then
        log_error "Invalid environment. Use 'staging' or 'production'"
        exit 1
    fi
    
    # Check required tools
    command -v docker >/dev/null 2>&1 || { log_error "docker is required but not installed"; exit 1; }
    command -v docker-compose >/dev/null 2>&1 || { log_error "docker-compose is required"; exit 1; }
    
    log_success "Environment validated"
}

# Load environment configuration
load_config() {
    log "Loading configuration for $ENVIRONMENT"
    
    case $ENVIRONMENT in
        staging)
            export CLUSTER_NAME="phpfrarm-staging"
            export SERVICE_NAME="phpfrarm-api-staging"
            export DOMAIN="staging.phpfrarm.com"
            export DOCKER_COMPOSE_FILE="docker-compose.staging.yml"
            ;;
        production)
            export CLUSTER_NAME="phpfrarm-production"
            export SERVICE_NAME="phpfrarm-api-production"
            export DOMAIN="api.phpfrarm.com"
            export DOCKER_COMPOSE_FILE="docker-compose.prod.yml"
            ;;
    esac
    
    export IMAGE_TAG=${IMAGE_TAG:-$(git rev-parse --short HEAD)}
    
    log_success "Configuration loaded"
}

# Backup current deployment
backup_deployment() {
    log "Creating backup of current deployment"
    
    BACKUP_DIR="/var/backups/phpfrarm/${ENVIRONMENT}_${TIMESTAMP}"
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T mysql \
        mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" phpfrarm > "$BACKUP_DIR/database.sql"
    
    # Backup current docker images
    echo "$IMAGE_TAG" > "$BACKUP_DIR/version.txt"
    
    # Backup environment file
    cp ".env.${ENVIRONMENT}" "$BACKUP_DIR/.env"
    
    log_success "Backup created at: $BACKUP_DIR"
    echo "$BACKUP_DIR" > /tmp/phpfrarm_last_backup
}

# Pull latest images
pull_images() {
    log "Pulling Docker images for tag: $IMAGE_TAG"
    
    docker pull "${REGISTRY}/${IMAGE_NAME}/backend:${IMAGE_TAG}" || {
        log_error "Failed to pull backend image"
        exit 1
    }
    
    docker pull "${REGISTRY}/${IMAGE_NAME}/frontend:${IMAGE_TAG}" || {
        log_error "Failed to pull frontend image"
        exit 1
    }
    
    log_success "Images pulled successfully"
}

# Run database migrations
run_migrations() {
    log "Running database migrations"
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" run --rm backend \
        php artisan migrate --force || {
        log_error "Migration failed"
        return 1
    }
    
    log_success "Migrations completed"
}

# Deploy with blue-green strategy
deploy_blue_green() {
    log "Starting blue-green deployment"
    
    # Get current active color
    CURRENT_COLOR=$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q backend | \
        xargs docker inspect --format='{{.Config.Labels.color}}' | head -1 || echo "blue")
    
    if [ "$CURRENT_COLOR" == "blue" ]; then
        NEW_COLOR="green"
    else
        NEW_COLOR="blue"
    fi
    
    log "Current: $CURRENT_COLOR, Deploying: $NEW_COLOR"
    
    # Start new containers
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d \
        --scale backend_${NEW_COLOR}=3 \
        --no-deps backend_${NEW_COLOR} || {
        log_error "Failed to start new containers"
        return 1
    }
    
    # Wait for containers to be healthy
    log "Waiting for containers to be healthy..."
    sleep 30
    
    # Health check
    if [ "$SKIP_TESTS" = false ]; then
        for i in {1..30}; do
            if curl -sf "http://localhost:8080/health" > /dev/null; then
                log_success "Health check passed"
                break
            fi
            
            if [ $i -eq 30 ]; then
                log_error "Health check failed after 30 attempts"
                return 1
            fi
            
            sleep 2
        done
    fi
    
    # Switch traffic
    log "Switching traffic to $NEW_COLOR"
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec nginx \
        sed -i "s/backend_${CURRENT_COLOR}/backend_${NEW_COLOR}/g" /etc/nginx/conf.d/default.conf
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec nginx nginx -s reload
    
    # Drain old containers
    log "Draining old containers"
    sleep 15
    
    # Stop old containers
    docker-compose -f "$DOCKER_COMPOSE_FILE" stop backend_${CURRENT_COLOR}
    
    log_success "Blue-green deployment completed"
}

# Run smoke tests
run_smoke_tests() {
    if [ "$SKIP_TESTS" = true ]; then
        log_warning "Skipping smoke tests"
        return 0
    fi
    
    log "Running smoke tests"
    
    HEALTH_URL="https://${DOMAIN}/health"
    API_URL="https://${DOMAIN}/api/status"
    
    # Health endpoint
    if curl -sf "$HEALTH_URL" > /dev/null; then
        log_success "Health check passed"
    else
        log_error "Health check failed"
        return 1
    fi
    
    # Ready endpoint
    if curl -sf "${HEALTH_URL}/ready" > /dev/null; then
        log_success "Ready check passed"
    else
        log_error "Ready check failed"
        return 1
    fi
    
    # API status
    if curl -sf "$API_URL" > /dev/null; then
        log_success "API check passed"
    else
        log_error "API check failed"
        return 1
    fi
    
    log_success "All smoke tests passed"
}

# Rollback to previous version
rollback() {
    log "Rolling back deployment"
    
    LAST_BACKUP=$(cat /tmp/phpfrarm_last_backup 2>/dev/null)
    
    if [ -z "$LAST_BACKUP" ] || [ ! -d "$LAST_BACKUP" ]; then
        log_error "No backup found for rollback"
        exit 1
    fi
    
    # Get previous version
    PREVIOUS_VERSION=$(cat "$LAST_BACKUP/version.txt")
    
    log "Rolling back to version: $PREVIOUS_VERSION"
    
    # Restore database
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T mysql \
        mysql -u root -p"${MYSQL_ROOT_PASSWORD}" phpfrarm < "$LAST_BACKUP/database.sql"
    
    # Deploy previous version
    export IMAGE_TAG="$PREVIOUS_VERSION"
    pull_images
    deploy_blue_green
    
    log_success "Rollback completed"
}

# Send notification
send_notification() {
    local STATUS=$1
    local MESSAGE=$2
    
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"[$ENVIRONMENT] $STATUS: $MESSAGE\"}" \
            "$SLACK_WEBHOOK"
    fi
}

# Main deployment flow
main() {
    log "=========================================="
    log "PHPFrarm Deployment Script"
    log "Environment: $ENVIRONMENT"
    log "Timestamp: $TIMESTAMP"
    log "=========================================="
    
    # Handle rollback
    if [ "$ROLLBACK" = true ]; then
        rollback
        exit 0
    fi
    
    # Validate
    validate_environment
    load_config
    
    # Confirmation for production
    if [[ "$ENVIRONMENT" == "production" && "$FORCE" == false ]]; then
        read -p "Deploy to PRODUCTION? (yes/no): " -n 3 -r
        echo
        if [[ ! $REPLY =~ ^yes$ ]]; then
            log "Deployment cancelled"
            exit 0
        fi
    fi
    
    # Execute deployment
    backup_deployment
    pull_images
    run_migrations || {
        log_error "Migration failed, rolling back"
        rollback
        send_notification "FAILED" "Deployment failed during migration"
        exit 1
    }
    
    deploy_blue_green || {
        log_error "Deployment failed, rolling back"
        rollback
        send_notification "FAILED" "Deployment failed"
        exit 1
    }
    
    run_smoke_tests || {
        log_error "Smoke tests failed, rolling back"
        rollback
        send_notification "FAILED" "Smoke tests failed"
        exit 1
    }
    
    log_success "=========================================="
    log_success "Deployment completed successfully!"
    log_success "Environment: $ENVIRONMENT"
    log_success "Version: $IMAGE_TAG"
    log_success "=========================================="
    
    send_notification "SUCCESS" "Deployment completed successfully (v$IMAGE_TAG)"
}

# Run main
main
