# 🚀 PHPFrarm DevOps & Deployment Guide

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Local Development Setup](#local-development-setup)
4. [CI/CD Pipeline](#cicd-pipeline)
5. [Deployment Strategies](#deployment-strategies)
6. [Docker Deployment](#docker-deployment)
7. [Kubernetes Deployment](#kubernetes-deployment)
8. [Zero-Downtime Deployment](#zero-downtime-deployment)
9. [Secrets Management](#secrets-management)
10. [Health Checks](#health-checks)
11. [Monitoring & Observability](#monitoring--observability)
12. [Rollback Procedures](#rollback-procedures)
13. [Troubleshooting](#troubleshooting)
14. [Best Practices](#best-practices)

---

## 📖 Overview

PHPFrarm provides enterprise-grade DevOps tooling with:
- ✅ Automated CI/CD pipelines (GitHub Actions + GitLab CI)
- ✅ Zero-downtime blue-green deployments
- ✅ Comprehensive health checks
- ✅ Secrets management (Vault, AWS, Azure)
- ✅ Docker & Kubernetes support
- ✅ Monitoring with Prometheus & Grafana

---

## 🔧 Prerequisites

### Required Tools
- **Docker** (>= 20.10)
- **Docker Compose** (>= 2.0)
- **kubectl** (>= 1.25) - for Kubernetes
- **Git** (>= 2.30)
- **Bash** (>= 4.0)

### Cloud Accounts (Optional)
- AWS Account (for ECS/Secrets Manager)
- Azure Account (for AKS/Key Vault)
- HashiCorp Vault instance

### Access Requirements
- Container registry access (GitHub Container Registry, Docker Hub, etc.)
- SSH access to deployment servers
- Kubernetes cluster admin access (for K8s deployments)

---

## 💻 Local Development Setup

### 1. Clone Repository
```bash
git clone https://github.com/yourorg/phpfrarm.git
cd phpfrarm
```

### 2. Environment Configuration
```bash
# Copy environment templates
cp .env.example .env
cp Farm/backend/.env.example Farm/backend/.env

# Edit environment files
nano .env
```

### 3. Start Development Environment
```bash
docker-compose up -d

# Check container status
docker-compose ps

# View logs
docker-compose logs -f backend
```

### 4. Initialize Database
```bash
# Run migrations
docker-compose exec backend php artisan migrate

# Seed database
docker-compose exec backend php artisan db:seed
```

### 5. Access Application
- **Frontend**: http://localhost:3000
- **API**: http://localhost:8080
- **Swagger Docs**: http://localhost:8080/docs
- **Health Check**: http://localhost:8080/health

---

## 🔄 CI/CD Pipeline

### GitHub Actions Workflow

The CI/CD pipeline automatically runs on push/PR:

**Stages:**
1. **Lint** - Code quality checks (PHPCS, PHPStan, ESLint)
2. **Security** - Vulnerability scanning (Composer Audit, OWASP)
3. **Test** - Unit, Integration, API, Security, Contract tests
4. **Build** - Docker image creation
5. **Deploy** - Environment-specific deployment
6. **Verify** - Smoke tests & health checks

**Configuration:** `.github/workflows/ci-cd.yml`

### GitLab CI Workflow

Alternative CI/CD for GitLab users.

**Configuration:** `.gitlab-ci.yml`

### Environment Variables (Secrets)

**Required Secrets:**
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `REGISTRY_USERNAME`
- `REGISTRY_PASSWORD`
- `SLACK_WEBHOOK` (optional)
- `DB_PASSWORD`
- `MONGO_PASSWORD`
- `REDIS_PASSWORD`
- `VAULT_TOKEN`

**Setting Secrets:**

**GitHub:**
```
Settings → Secrets and variables → Actions → New repository secret
```

**GitLab:**
```
Settings → CI/CD → Variables → Add variable
```

---

## 🎯 Deployment Strategies

### 1. Blue-Green Deployment (Recommended)
- Zero downtime
- Instant rollback capability
- Full traffic cutover
- **Use for:** Production deployments

### 2. Rolling Update
- Gradual replacement
- Minimal resource overhead
- No downtime
- **Use for:** Kubernetes deployments

### 3. Canary Deployment
- Progressive rollout
- Risk mitigation
- Traffic splitting (10% → 50% → 100%)
- **Use for:** High-risk changes

---

## 🐳 Docker Deployment

### Production Docker Compose

**File:** `docker-compose.prod.yml`

**Features:**
- Multi-replica backend (3 instances)
- Resource limits (CPU, memory)
- Health checks
- Prometheus + Grafana monitoring
- Persistent volumes

### Deploy to Production

```bash
# Load environment
export $(cat .env.production | xargs)

# Pull latest images
docker-compose -f docker-compose.prod.yml pull

# Deploy
docker-compose -f docker-compose.prod.yml up -d

# Verify
docker-compose -f docker-compose.prod.yml ps
curl -f http://localhost/health
```

### Automated Deployment

```bash
# Using deployment script
chmod +x ./infra/scripts/deploy.sh
./infra/scripts/deploy.sh production

# With options
./infra/scripts/deploy.sh production --skip-tests --force
```

---

## ☸️ Kubernetes Deployment

### Prerequisites

```bash
# Verify kubectl access
kubectl cluster-info

# Create namespace
kubectl create namespace phpfrarm
```

### Deploy to Kubernetes

```bash
# Apply secrets (edit first!)
kubectl apply -f infra/k8s/secrets.yaml

# Deploy backend
kubectl apply -f infra/k8s/backend-deployment.yaml

# Deploy ingress
kubectl apply -f infra/k8s/ingress.yaml

# Verify deployment
kubectl get pods -n phpfrarm
kubectl get svc -n phpfrarm
kubectl get ingress -n phpfrarm
```

### Auto-Scaling

**Horizontal Pod Autoscaler (HPA):**
- Min replicas: 3
- Max replicas: 10
- Target CPU: 70%
- Target Memory: 80%

```bash
# Check HPA status
kubectl get hpa -n phpfrarm

# Describe HPA
kubectl describe hpa backend-hpa -n phpfrarm
```

### Rolling Updates

```bash
# Update image
kubectl set image deployment/phpfrarm-backend \
  backend=ghcr.io/phpfrarm/backend:v1.2.0 \
  -n phpfrarm

# Check rollout status
kubectl rollout status deployment/phpfrarm-backend -n phpfrarm

# View rollout history
kubectl rollout history deployment/phpfrarm-backend -n phpfrarm
```

---

## 🔄 Zero-Downtime Deployment

### Blue-Green Strategy

**Script:** `infra/scripts/deploy.sh`

**Process:**
1. **Backup** - Current database & version
2. **Pull** - New Docker images
3. **Migrate** - Database migrations
4. **Deploy** - Start green containers
5. **Health Check** - Verify new containers
6. **Switch** - Update load balancer
7. **Drain** - Stop blue containers
8. **Verify** - Run smoke tests

```bash
# Production deployment
./infra/scripts/deploy.sh production

# With confirmation prompt
./infra/scripts/deploy.sh production --force

# Skip health checks (not recommended)
./infra/scripts/deploy.sh production --skip-tests
```

### Traffic Switching

**Nginx Configuration:**
```nginx
upstream backend_blue {
    server backend_blue_1:8080;
    server backend_blue_2:8080;
    server backend_blue_3:8080;
}

upstream backend_green {
    server backend_green_1:8080;
    server backend_green_2:8080;
    server backend_green_3:8080;
}

# Active upstream (switch between blue/green)
upstream backend {
    server backend_green_1:8080;
    server backend_green_2:8080;
    server backend_green_3:8080;
}
```

---

## 🔐 Secrets Management

### SecretsManager Class

**File:** `app/Core/Security/SecretsManager.php`

**Supported Backends:**
- Environment variables (.env)
- HashiCorp Vault
- AWS Secrets Manager (requires AWS SDK)
- Azure Key Vault (requires Azure SDK)

### Usage

```php
use Farm\Backend\App\Core\Security\SecretsManager;

// Initialize with Vault
$secrets = new SecretsManager('vault', [
    'vault_url' => 'https://vault.example.com:8200',
    'vault_token' => 'hvs.xxxxx',
    'vault_path' => 'secret/phpfrarm'
]);

// Get secret
$apiKey = $secrets->get('stripe_api_key');

// Set secret
$secrets->set('new_api_key', 'sk_live_xxxxx');

// Rotate secret
$secrets->rotate('database_password', 'new_secure_password');

// Delete secret
$secrets->delete('old_api_key');
```

### HashiCorp Vault Setup

```bash
# Enable KV secrets engine
vault secrets enable -path=secret kv-v2

# Create policy
vault policy write phpfrarm - <<EOF
path "secret/data/phpfrarm/*" {
  capabilities = ["create", "read", "update", "delete", "list"]
}
EOF

# Create token
vault token create -policy=phpfrarm

# Store secret
vault kv put secret/phpfrarm/stripe_api_key value=sk_live_xxxxx
```

### Environment Variables

```bash
# .env.production
VAULT_URL=https://vault.example.com:8200
VAULT_TOKEN=hvs.xxxxx
VAULT_PATH=secret/phpfrarm
```

---

## 🏥 Health Checks

### Endpoints

**1. Basic Health Check**
```bash
GET /health

Response:
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2026-01-18T10:30:00+00:00",
    "version": "1.0.0",
    "uptime": "15d 6h 23m",
    "response_time_ms": 2.45
  }
}
```

**2. Readiness Check**
```bash
GET /health/ready

Response:
{
  "success": true,
  "data": {
    "status": "ready",
    "checks": {
      "database": { "healthy": true, "latency_ms": 5.2 },
      "redis": { "healthy": true, "latency_ms": 1.8 },
      "mongodb": { "healthy": true, "latency_ms": 3.4 },
      "disk": { "healthy": true, "used_percent": 65.2 }
    }
  }
}
```

**3. Liveness Check**
```bash
GET /health/live

Response (comprehensive):
{
  "success": true,
  "data": {
    "status": "live",
    "checks": {
      "database": {...},
      "redis": {...},
      "mongodb": {...},
      "disk": {...},
      "memory": { "healthy": true, "used_percent": 72.1 },
      "php": { "healthy": true, "version": "8.2.0" },
      "filesystem": { "healthy": true, "writable": true }
    },
    "load_average": [1.25, 1.42, 1.38]
  }
}
```

### Load Balancer Configuration

**Nginx:**
```nginx
upstream backend {
    server backend1:8080 max_fails=3 fail_timeout=30s;
    server backend2:8080 max_fails=3 fail_timeout=30s;
    server backend3:8080 max_fails=3 fail_timeout=30s;
}

server {
    location / {
        proxy_pass http://backend;
        proxy_next_upstream error timeout http_502 http_503;
        
        health_check uri=/health interval=10s fails=3 passes=2;
    }
}
```

**Kubernetes:**
```yaml
livenessProbe:
  httpGet:
    path: /health/live
    port: 8080
  initialDelaySeconds: 30
  periodSeconds: 10
  timeoutSeconds: 5
  failureThreshold: 3

readinessProbe:
  httpGet:
    path: /health/ready
    port: 8080
  initialDelaySeconds: 10
  periodSeconds: 5
  timeoutSeconds: 3
  failureThreshold: 3
```

---

## 📊 Monitoring & Observability

### Prometheus Metrics

**Configuration:** `infra/monitoring/prometheus.yml`

**Scraped Targets:**
- PHP Backend (application metrics)
- MySQL Exporter (database metrics)
- Redis Exporter (cache metrics)
- MongoDB Exporter (logs metrics)
- Node Exporter (system metrics)
- Nginx Exporter (traffic metrics)

**Access:** http://prometheus:9090

### Grafana Dashboards

**Pre-configured Dashboards:**
1. **Application Overview** - Request rates, latency, errors
2. **Database Performance** - Query times, connections, slow queries
3. **Cache Performance** - Hit rates, memory usage
4. **System Resources** - CPU, memory, disk, network
5. **API Endpoints** - Per-endpoint metrics

**Access:** http://grafana:3000
**Default Login:** admin / ${GRAFANA_PASSWORD}

### Custom Metrics

```php
// In your application code
$prometheus = new PrometheusClient();

// Counter
$prometheus->counter('api_requests_total', 'Total API requests')
    ->labels(['method' => 'GET', 'endpoint' => '/api/users'])
    ->inc();

// Histogram
$prometheus->histogram('api_request_duration_seconds', 'API request duration')
    ->labels(['method' => 'POST', 'endpoint' => '/api/orders'])
    ->observe(0.245);

// Gauge
$prometheus->gauge('active_users', 'Currently active users')
    ->set(1523);
```

### Log Aggregation

**MongoDB Collections:**
- `application_logs` - Application logs
- `access_logs` - HTTP access logs
- `audit_logs` - User action audit trail
- `security_events` - Security-related events

**Query Logs:**
```javascript
// MongoDB query examples
db.application_logs.find({
  correlation_id: "abc123-def456-ghi789",
  level: "error"
}).sort({ timestamp: -1 }).limit(100);

db.audit_logs.find({
  user_id: "user_12345",
  timestamp: { $gte: ISODate("2026-01-18T00:00:00Z") }
});
```

---

## ⏪ Rollback Procedures

### Automatic Rollback

The deployment script automatically rolls back on:
- Migration failures
- Health check failures
- Smoke test failures

### Manual Rollback

```bash
# Using deployment script
./infra/scripts/deploy.sh production --rollback

# Docker Compose
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d --force-recreate

# Kubernetes
kubectl rollout undo deployment/phpfrarm-backend -n phpfrarm

# To specific revision
kubectl rollout undo deployment/phpfrarm-backend --to-revision=3 -n phpfrarm
```

### Database Rollback

```bash
# Restore from backup
BACKUP_DIR=$(cat /tmp/phpfrarm_last_backup)
docker-compose exec mysql mysql -u root -p < $BACKUP_DIR/database.sql

# Or use migration rollback
docker-compose exec backend php artisan migrate:rollback --step=1
```

---

## 🔧 Troubleshooting

### Common Issues

**1. Container Won't Start**
```bash
# Check logs
docker-compose logs backend

# Check resource usage
docker stats

# Restart specific service
docker-compose restart backend
```

**2. Database Connection Failed**
```bash
# Verify MySQL is running
docker-compose ps mysql

# Test connection
docker-compose exec mysql mysql -u root -p -e "SELECT 1;"

# Check environment variables
docker-compose exec backend env | grep DB_
```

**3. High Memory Usage**
```bash
# Check container memory
docker stats --no-stream

# Identify memory leaks
docker-compose exec backend php artisan cache:clear
docker-compose restart backend
```

**4. Deployment Fails**
```bash
# Check deployment logs
cat /var/log/phpfrarm/deployment_*.log | tail -100

# Verify images pulled correctly
docker images | grep phpfrarm

# Manual health check
curl -v http://localhost:8080/health
```

### Debug Mode

```bash
# Enable debug logging
export APP_DEBUG=true
export LOG_LEVEL=debug

# Restart containers
docker-compose restart backend

# Tail logs
docker-compose logs -f --tail=100 backend
```

---

## ✅ Best Practices

### 1. Pre-Deployment Checklist
- [ ] All tests passing in CI/CD
- [ ] Code reviewed and approved
- [ ] Database migrations tested
- [ ] Environment variables updated
- [ ] Secrets rotated (if needed)
- [ ] Backup created
- [ ] Team notified

### 2. Security Best Practices
- ✅ Use secrets manager (Vault/AWS/Azure)
- ✅ Rotate credentials regularly
- ✅ Enable TLS/HTTPS everywhere
- ✅ Restrict network access (firewalls)
- ✅ Use latest stable images
- ✅ Scan images for vulnerabilities
- ✅ Enable audit logging

### 3. Performance Best Practices
- ✅ Use caching (Redis) aggressively
- ✅ Enable HTTP/2
- ✅ Optimize Docker images (multi-stage builds)
- ✅ Set appropriate resource limits
- ✅ Monitor application metrics
- ✅ Use CDN for static assets

### 4. Reliability Best Practices
- ✅ Auto-scaling configured (HPA)
- ✅ Health checks enabled
- ✅ Circuit breakers active
- ✅ Rate limiting enforced
- ✅ Backups automated
- ✅ Disaster recovery plan documented

### 5. Monitoring Best Practices
- ✅ Monitor golden signals (latency, traffic, errors, saturation)
- ✅ Set up alerting (PagerDuty, Slack, email)
- ✅ Track business metrics
- ✅ Review logs daily
- ✅ Conduct post-mortems for incidents

---

## 📚 Additional Resources

### Documentation
- [Docker Documentation](https://docs.docker.com/)
- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [Prometheus Documentation](https://prometheus.io/docs/)
- [HashiCorp Vault Documentation](https://www.vaultproject.io/docs)

### Internal Guides
- [Architecture Guide](../ARCHITECTURE.md)
- [API Documentation](http://localhost:8080/docs)
- [Testing Guide](../TESTING_GUIDE.md)
- [Security Guide](../SECURITY_GUIDE.md)

### Support
- **Slack**: #phpfrarm-devops
- **Email**: devops@phpfrarm.com
- **Wiki**: https://wiki.phpfrarm.com/devops

---

## 🎯 Summary

PHPFrarm provides production-ready DevOps tooling out of the box:

✅ **Automated CI/CD** - GitHub Actions + GitLab CI
✅ **Zero-Downtime Deployments** - Blue-green strategy
✅ **Health Checks** - 3 levels (health, ready, live)
✅ **Secrets Management** - Vault, AWS, Azure support
✅ **Container Orchestration** - Docker Compose + Kubernetes
✅ **Monitoring** - Prometheus + Grafana
✅ **Auto-Scaling** - HPA with CPU/memory targets
✅ **Automated Rollback** - On failure detection

**Next Steps:**
1. Review deployment scripts
2. Configure secrets backend
3. Set up monitoring alerts
4. Test deployment in staging
5. Deploy to production

**Questions?** Contact the DevOps team or consult the wiki.
