# 🚀 PHPFrarm - Enterprise API Development Framework

> **Production-ready, modular API framework with Docker, PHP, React, MySQL, and MongoDB**

## 📋 Overview

PHPFrarm is an enterprise-grade API development framework that enforces security, observability, and scalability standards automatically. It enables teams to build production-ready REST APIs rapidly with 100% compliance to enterprise best practices.

## ✨ Key Features

### 🔐 Security-First Architecture
- ✅ Multi-flow authentication (Email/Password, Phone OTP, Email OTP)
- ✅ JWT token management with refresh tokens
- ✅ RBAC & scope-based authorization
- ✅ Rate limiting & throttling
- ✅ DDoS & abuse protection

### 📊 Observability by Default
- ✅ Auto-generated correlation, transaction, and request IDs
- ✅ Structured JSON logging to MongoDB
- ✅ Audit trails & security logs
- ✅ Distributed tracing support

### 🗄️ Database Architecture
- ✅ **MySQL**: Transactional data (accessed ONLY via stored procedures)
- ✅ **MongoDB**: Logs, audit trails, metrics
- ✅ **Redis**: Caching & session storage
- ✅ Framework enforces stored procedure usage (raw SQL blocked)

### 🧩 Modular Framework
- ✅ Pluggable feature modules
- ✅ Auto-registration of tables, stored procedures, and routes
- ✅ **🆕 Zero-config module loading** (no index.php changes needed!)
- ✅ **🆕 Configurable middleware per route/group**
- ✅ Standard response envelopes
- ✅ Contract-first API design

## 🏗️ Architecture

```
/farm
 ├── docker-compose.yml          # Multi-container orchestration
 ├── .env                         # Environment configuration
 ├── README.md                    # This file
 ├── start.ps1                    # Windows startup script
 ├── backend/                     # PHP Backend
 │    ├── app/Core/               # Framework core
 │    │    ├── Database.php       # Stored procedure enforcer
 │    │    ├── Logger.php         # MongoDB logging
 │    │    ├── Response.php       # Standard envelopes
 │    │    ├── TraceContext.php   # Trace ID manager
 │    │    ├── Router.php         # Route & middleware manager
 │    │    └── ModuleLoader.php   # Auto-discover modules
 │    ├── modules/                # Feature modules (auto-loaded!)
 │    │    ├── Auth/              # Authentication module
 │    │    ├── User/              # User identity module
 │    │    └── ...                # Other feature modules
 │    ├── database/
 │    │    ├── mysql/
 │    │    │    ├── tables/       # Table DDL per module
 │    │    │    └── stored_procedures/
 │    │    └── mongo/indexes/     # MongoDB index definitions
 │    └── composer.json           # PHP dependencies
 ├── frontend/                    # React Frontend
 │    └── src/                    # Source code
 ├── docs/                        # 📚 Documentation Hub
 │    ├── README.md               # Documentation index
 │    ├── api/                    # API specifications
 │    ├── architecture/           # System design docs
 │    ├── guides/                 # Development guides
 │    ├── security/               # Security docs
 │    ├── devops/                 # DevOps guides
 │    ├── modules/                # Module completion reports
 │    └── reference/              # Framework reports
 └── infra/                       # Infrastructure configs
      ├── ci-cd/                  # GitLab CI & GitHub Actions
      ├── k8s/                    # Kubernetes manifests
      ├── nginx/                  # Nginx configs
      ├── monitoring/             # Prometheus config
      └── docker-compose.prod.yml # Production compose
```

## 🚀 Quick Start

### Prerequisites
- Docker Desktop (Windows/Mac) or Docker Engine (Linux)
- Docker Compose v2.0+
- Git

### 1. Clone & Navigate

```powershell
cd C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\farm
```

### 2. Configure Environment

Copy and customize the `.env` file with your settings:

```powershell
# Review .env file and update passwords
notepad .env
```

**Important**: Change all default passwords before deploying to production!

### API Versioning (Config-Driven)

API versioning is configured in [backend/config/api.php](backend/config/api.php) and can be overridden via `.env`:

```
API_SUPPORTED_VERSIONS=v1,v2
API_DEPRECATED_VERSIONS=v1:2026-12-31
```

**Notes:**
- Order matters: the first supported version is the default.
- Deprecated versions add headers: `X-API-Deprecated`, `X-API-Sunset-Date`.

### 3. Build & Start Services

```powershell
# Build and start all services
docker-compose up -d --build

# Check service status
docker-compose ps

# View logs
docker-compose logs -f
```

### 4. Access Services

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8080
- **Health Check**: http://localhost:8080/health

### 5. Initialize Database

Database tables and stored procedures are automatically created on first MySQL startup from:
- `/backend/database/mysql/tables/`
- `/backend/database/mysql/stored_procedures/`

## 🆕 Creating a New Module (No index.php Changes!)

1. **Create module folder**:
```
/backend/modules/YourModule/
├── module.php    # Module config (required)
└── routes.php    # Route definitions (optional)
```

2. **Define module config** (`module.php`):
```php
<?php
return [
    'name' => 'YourModule',
    'version' => '1.0.0',
    'enabled' => true,
    'bootstrap' => function() {
        // Initialization code
    },
];
```

3. **Define routes** (`routes.php`):
```php
<?php
use PHPFrarm\Core\Router;
use PHPFrarm\Core\Response;

// Simple route
Router::get('/api/yourmodule/hello', function($request) {
    Response::success(['message' => 'Hello!']);
});

// Protected route with middleware
Router::post('/api/yourmodule/data', function($request) {
    $user = $request['user']; // Added by 'auth' middleware
    Response::success(['user_id' => $user['user_id']]);
}, ['auth', 'rateLimit']);

// Route group with shared middleware
Router::group('/api/yourmodule/admin', ['auth', 'adminOnly'], function() {
    Router::get('/stats', function($request) {
        Response::success(['stats' => 'data']);
    });
});
```

4. **That's it!** Module auto-loads on next request.

### Built-in Middleware

- `auth` - JWT authentication
- `rateLimit` - Rate limiting per IP
- `cors` - CORS headers
- `jsonParser` - JSON body validation
- `adminOnly` - Admin role check
- `logRequest` - Request/response logging

See [backend/MODULES_GUIDE.md](backend/MODULES_GUIDE.md) for complete guide.

### Database Operations (Stored Procedures Only)

❌ **FORBIDDEN** - Raw SQL queries:
```php
// This will THROW EXCEPTION
Database::query("SELECT * FROM users");
```

✅ **REQUIRED** - Stored procedures only:
```php
// Call stored procedure
$users = Database::callProcedure('sp_get_user_by_email', ['user@example.com']);
```

### Creating Database Schema for Module

1. **Define tables**:
```sql
-- /backend/database/mysql/tables/02_your_module.sql
CREATE TABLE IF NOT EXISTS your_table (...);
```

2. **Create stored procedures**:
```sql
-- /backend/database/mysql/stored_procedures/02_your_module.sql
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS sp_your_operation() BEGIN ... END$$
DELIMITER ;
```

3. Restart MySQL container to apply changes.

> **Auto-init:** In non-production, ModuleLoader auto-initializes module tables and stored procedures on first load.
> Control with `MODULE_AUTO_DB` and `MODULE_AUTO_MONGO` in `.env`.

Call stored procedure:
```php
$users = Database::callProcedure('sp_get_user_by_email', ['user@example.com']);
```

### Observability

All APIs automatically include trace IDs:

```json
{
  "success": true,
  "data": {...},
  "trace": {
    "correlation_id": "01HMXXX...",
    "transaction_id": "01HMYYY...",
    "request_id": "01HMZZZ..."
  }
}
```

Trace IDs are:
- Auto-generated if not provided
- Propagated across all logs
- Returned in response headers
- Logged to MongoDB

## 🔧 Docker Commands

### Start/Stop Services

```powershell
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart specific service
docker-compose restart backend

# View logs
docker-compose logs -f backend
docker-compose logs -f mysql
```

### Rebuilding After Code Changes

```powershell
# Rebuild backend
docker-compose up -d --build backend

# Rebuild frontend
docker-compose up -d --build frontend
```

### Database Management

```powershell
# Connect to MySQL
docker-compose exec mysql mysql -u root -p

# Connect to MongoDB
docker-compose exec mongodb mongosh -u admin -p

# View Redis data
docker-compose exec redis redis-cli -a your_redis_password
```

### Clean Reset

```powershell
# Stop and remove all containers, volumes, and networks
docker-compose down -v

# Rebuild from scratch
docker-compose up -d --build
```

## 📝 API Standards

### Request Headers (Recommended)

```
X-Correlation-Id: <ULID>  # Optional, auto-generated if missing
X-Transaction-Id: <ULID>  # Optional, auto-generated if missing
Authorization: Bearer <JWT_TOKEN>  # Required for protected routes
Content-Type: application/json
```

### Response Envelope (Success)

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {...},
  "meta": {
    "timestamp": "2026-01-18T10:30:00Z"
  },
  "trace": {
    "correlation_id": "01HMXXX...",
    "transaction_id": "01HMYYY...",
    "request_id": "01HMZZZ..."
  }
}
```

### Response Envelope (Error)

```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "ERR_VALIDATION",
  "errors": ["Email is required"],
  "trace": {...},
  "timestamp": "2026-01-18T10:30:00Z"
}
```

## 🧪 Testing

```powershell
# Backend tests
docker-compose exec backend composer test

# Frontend tests
dockSetup & Usage**: This README
- **🆕 Module Development**: [backend/MODULES_GUIDE.md](backend/MODULES_GUIDE.md)
- **🆕 Architecture Overview**: [backend/ARCHITECTURE.md](backend/ARCHITECTURE.md)
- **Module Specifications**: [../Prompt.md](../Prompt.md) (16 modules)
- **API Checklist**: [../API-Features.md](../API-Features.md) (250+ items)
- **Implementation Requirements**: [../Base-Prompt.md](../Base-Prompt.md)
- **AI Agent Guidance**: [../.github/copilot-instructions.md](../.github/copilot-instructions.md)
## 📊 Monitoring

### MongoDB Logs

```javascript
// Connect to MongoDB
docker-compose exec mongodb mongosh -u admin -p

use phpfrarm_logs

// View application logs
db.application_logs.find().sort({timestamp: -1}).limit(10)

// View audit logs by user
db.audit_logs.find({"context.user_id": "USER_ID"})

// View security events
db.security_logs.find({level: "SECURITY"})
```

### Health Check

```powershell
curl http://localhost:8080/health
```

## 🔒 Security Checklist

- [ ] Change all default passwords in `.env`
- [ ] Update JWT secret key
- [ ] Configure SMTP/SMS providers for OTP
- [ ] Enable HTTPS in production
- [ ] Configure rate limiting thresholds
- [ ] Set up firewall rules
- [ ] Enable MongoDB authentication
- [ ] Regularly update Docker images

## 📚 Documentation

- **Architecture**: See [Prompt.md](../Prompt.md) for 16 module specifications
- **API Checklist**: See [API-Features.md](../API-Features.md) for 250+ compliance items
- **Setup Details**: See [Base-Prompt.md](../Base-Prompt.md) for implementation requirements

## 🤝 Contributing

### Module Development Guidelines

1. All modules must be self-contained
2. Register tables, stored procedures, and routes in module
3. Follow naming conventions: `sp_module_operation` for stored procedures
4. Include MongoDB collections if logging is needed
5. Document all API endpoints with examples

### Code Standards

- PHP: PSR-12 coding style
- SQL: Uppercase keywords, lowercase identifiers
- JavaScript: ESLint with React config
- All endpoints must have trace ID support

## 🐛 Troubleshooting

### Backend won't start
```powershell
# Check logs
docker-compose logs backend

# Verify MySQL is healthy
docker-compose ps mysql

# Restart backend
docker-compose restart backend
```

### Database connection failed
```powershell
# Check MySQL container
docker-compose exec mysql mysqladmin ping

# Verify credentials in .env
cat .env | findstr MYSQL
```

### Frontend build errors
```powershell
# Clear node_modules and rebuild
docker-compose exec frontend rm -rf node_modules
docker-compose restart frontend
```

## 📄 License

This framework is provided as-is for enterprise API development.

## 📞 Support

For issues or questions, refer to:
- Architecture documentation in [Prompt.md](../Prompt.md)
- Developer checklist in [API-Features.md](../API-Features.md)
- GitHub issues for bug reports

---

**Built with**: PHP 8.2, React 18, MySQL 8.0, MongoDB 7.0, Redis 7, Docker Compose 3.8

**Framework Version**: 1.0.0
**Last Updated**: January 2026
