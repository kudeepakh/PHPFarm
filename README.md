# 🚀 PHPFrarm - Enterprise API Development Framework

<div align="center">

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Docker](https://img.shields.io/badge/Docker-Required-blue.svg)

**Production-ready, modular API framework with security, observability, and scalability built-in**

[Documentation](./Farm/docs/README.md) • [Quick Start](#-quick-start) • [Contributing](./CONTRIBUTING.md) • [API Reference](./Farm/docs/API_COMPLETE_REFERENCE.md)

</div>

---

## 📋 Overview

PHPFrarm is an **enterprise-grade API development framework** that enforces security, observability, and scalability standards automatically. It enables teams to build **production-ready REST APIs rapidly** with **100% compliance** to enterprise best practices.

### 🎯 Built for

- **Rapid Development**: Build APIs 60% faster with zero-config modules
- **Enterprise Standards**: 250+ best practices enforced at framework level
- **Security-First**: Authentication, authorization, and abuse protection built-in
- **Observability**: Automatic correlation IDs, structured logging, audit trails
- **Scalability**: Docker-based, horizontally scalable architecture

---

## ✨ Key Features

### 🔐 Security-First Architecture
- ✅ Multi-flow authentication (Email/Password, Phone OTP, Email OTP)
- ✅ JWT token management with refresh tokens
- ✅ RBAC & scope-based authorization
- ✅ Rate limiting & throttling
- ✅ DDoS & abuse protection
- ✅ Input validation & sanitization

### 📊 Observability by Default
- ✅ Auto-generated correlation, transaction, and request IDs
- ✅ Structured JSON logging to MongoDB
- ✅ Audit trails & security logs
- ✅ Distributed tracing support
- ✅ Real-time log viewer

### 🗄️ Database Architecture
- ✅ **MySQL**: Transactional data (accessed ONLY via stored procedures)
- ✅ **MongoDB**: Logs, audit trails, metrics
- ✅ **Redis**: Caching & session storage
- ✅ Framework enforces stored procedure usage (raw SQL blocked)

### 🧩 Modular Framework
- ✅ Pluggable feature modules
- ✅ Auto-registration of tables, stored procedures, and routes
- ✅ Zero-config module loading (no index.php changes needed!)
- ✅ Configurable middleware per route/group
- ✅ Standard response envelopes
- ✅ Contract-first API design (OpenAPI)

---

## 🏗️ Architecture

```
PHPFrarm/
 ├── Farm/                        # Main framework directory
 │    ├── backend/                # PHP Backend
 │    │    ├── app/Core/          # Framework core
 │    │    ├── modules/           # Feature modules
 │    │    ├── database/          # DB schemas & stored procedures
 │    │    └── tests/             # Test suite
 │    ├── frontend/               # React Frontend
 │    │    ├── src/               # Source code
 │    │    └── modules/           # Feature modules
 │    ├── docs/                   # Comprehensive documentation
 │    ├── infra/                  # Infrastructure configs
 │    └── docker-compose.yml      # Multi-container orchestration
 └── .github/                     # GitHub templates & workflows
```

---

## 🚀 Quick Start

### Prerequisites

- **Docker** & **Docker Compose** (latest version)
- **Git** (for cloning the repository)
- **PowerShell** (Windows) or **Bash** (Linux/Mac)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/kudeepakh/PHPFarm.git
   cd PHPFarm
   ```

2. **Navigate to Farm directory**
   ```bash
   cd Farm
   ```

3. **Copy environment file**
   ```bash
   # Windows PowerShell
   Copy-Item .env.example .env
   
   # Linux/Mac
   cp .env.example .env
   ```

4. **Start the framework**
   ```bash
   # Windows PowerShell
   .\start.ps1
   
   # Linux/Mac
   docker-compose up -d
   ```

5. **Verify installation**
   
   The default ports are:
   - **Backend API**: http://localhost:8080 (configurable via `BACKEND_PORT` in .env)
   - **Frontend**: http://localhost:3000
   - **API Documentation (Swagger UI)**: http://localhost:8080/docs
   - **OpenAPI Spec**: http://localhost:8080/docs/openapi.json
   - **Error Catalog**: http://localhost:8080/docs/errors
   - **Postman Collection**: http://localhost:8080/docs/postman
   
   > **Note**: If you're using a custom port (e.g., 8787), adjust the URLs accordingly:
   > - API Docs: http://localhost:8787/docs

### First API Test

```bash
# Test the health endpoint (adjust port if needed)
curl http://localhost:8080/api/v1/health

# Access API Documentation
# Open in browser: http://localhost:8080/docs

# Register a new user
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!",
    "phone": "+1234567890"
  }'
```

---

## 📚 Documentation

### 📖 Getting Started
- [Developer Onboarding Guide](./Farm/docs/DEVELOPER_ONBOARDING_GUIDE.md) - Complete setup and first module
- [Quick Start Guide](./Farm/docs/QUICK_START.md) - Get up and running in 5 minutes
- [Architecture Overview](./Farm/docs/architecture/ARCHITECTURE.md) - Understand the design

### 🔧 Development
- [Contributing Guide](./CONTRIBUTING.md) - How to contribute
- [Code Standards](./Farm/docs/CODE_STANDARDS_FINAL_SUMMARY.md) - Coding guidelines
- [Module Development](./Farm/docs/modules/MODULE_DEVELOPMENT.md) - Creating modules
- [Testing Guide](./Farm/docs/guides/TESTING_GUIDE.md) - Writing tests

### 📖 API Documentation
- [API Complete Reference](./Farm/docs/API_COMPLETE_REFERENCE.md) - Full API documentation
- [API Features Checklist](./Farm/docs/api/API-Features.md) - 250+ best practices
- [Authentication Guide](./Farm/docs/security/AUTHENTICATION.md) - Auth implementation

### 🚀 Deployment
- [DevOps Guide](./Farm/docs/devops/DEVOPS_GUIDE.md) - Production deployment
- [Docker Setup](./Farm/docs/devops/PROJECT_SETUP.md) - Container configuration
- [CI/CD Setup](./Farm/docs/devops/CI_CD.md) - Automated pipelines

---

## 🧩 Core Modules

| Module | Description | Status |
|--------|-------------|--------|
| **Authentication** | Email, Phone OTP, JWT tokens | ✅ Complete |
| **Authorization** | RBAC, scopes, resource-level access | ✅ Complete |
| **User Management** | Registration, profile, verification | ✅ Complete |
| **Logging** | MongoDB structured logging, audit trails | ✅ Complete |
| **Rate Limiting** | Request throttling, abuse protection | ✅ Complete |
| **Observability** | Correlation IDs, distributed tracing | ✅ Complete |
| **Validation** | Input validation, security hardening | ✅ Complete |

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](./CONTRIBUTING.md) for details.

### Quick Contribution Steps

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Code of Conduct

Please read our [Code of Conduct](./.github/CODE_OF_CONDUCT.md) before contributing.

---

## 🧪 Testing

```bash
# Run backend tests
cd Farm/backend
composer test

# Run frontend tests
cd Farm/frontend
npm test

# Run integration tests
cd Farm
./test_all_apis.ps1
```

---

## 📊 Project Status

| Feature | Status |
|---------|--------|
| Core Framework | ✅ Production Ready |
| Authentication | ✅ Production Ready |
| Authorization | ✅ Production Ready |
| Database Layer | ✅ Production Ready |
| Logging/Observability | ✅ Production Ready |
| Frontend UI | ✅ Production Ready |
| Documentation | ✅ Complete |
| CI/CD Pipelines | ✅ Complete |

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.2+, Slim Framework
- **Frontend**: React 18, TypeScript, Tailwind CSS
- **Databases**: MySQL 8.0, MongoDB 7.0
- **Cache**: Redis 7.0
- **Infrastructure**: Docker, Nginx
- **Testing**: PHPUnit, Jest
- **CI/CD**: GitHub Actions, GitLab CI

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](./LICENSE) file for details.

---

## 🙏 Acknowledgments

- Built with enterprise best practices from Fortune 500 companies
- Inspired by modern API frameworks and microservices architecture
- Community-driven development

---

## 📞 Support

- **Documentation**: [./Farm/docs/](./Farm/docs/)
- **Issues**: [GitHub Issues](https://github.com/kudeepakh/PHPFarm/issues)
- **Discussions**: [GitHub Discussions](https://github.com/kudeepakh/PHPFarm/discussions)

---

## 🗺️ Roadmap

- [ ] GraphQL support
- [ ] WebSocket support
- [ ] Event-driven architecture
- [ ] Kubernetes deployment templates
- [ ] CLI code generator
- [ ] Performance monitoring dashboard

---

<div align="center">

**Built with ❤️ for developers who care about quality**

[⭐ Star us on GitHub](https://github.com/kudeepakh/PHPFarm) • [📖 Read the Docs](./Farm/docs/README.md) • [🐛 Report Bug](https://github.com/kudeepakh/PHPFarm/issues)

</div>
