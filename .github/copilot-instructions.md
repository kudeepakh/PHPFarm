# PHPFrarm – Enterprise API Development Framework

## Project Overview
This is a **framework specification and architecture planning** project for an enterprise-grade, modular API development platform. The goal is to design a reusable framework that enforces security, observability, and scalability standards automatically, enabling teams to build production-ready REST APIs rapidly.

## Architecture Philosophy
- **Modular & pluggable**: Each concern (auth, logging, rate-limiting) is an independent module
- **Security-first**: Authentication, authorization, and abuse protection are built-in, not optional
- **Observability-by-default**: All APIs must automatically include correlation IDs, transaction IDs, and request IDs
- **Framework-level enforcement**: Standards are enforced at the framework level—developers cannot bypass them
- **Contract-first design**: OpenAPI specifications drive development

## Key Design Principles

### Non-Negotiable Requirements
Every API built with this framework **MUST**:
- Have authentication (no public APIs without explicit approval)
- Generate and propagate `X-Correlation-Id`, `X-Transaction-Id`, `X-Request-Id` headers
- Implement rate limiting and throttling
- Log all operations with structured JSON including trace IDs
- Provide OpenAPI documentation

### Standard Response Envelopes
All APIs use consistent JSON response structures:
- **Success responses**: Standard envelope with data, metadata, and trace IDs
- **Error responses**: Standard envelope with domain error codes, HTTP status codes, and trace IDs
- Never expose stack traces or internal errors to clients

### Authentication Flows (Module 2)
The framework supports multiple authentication patterns:
- Email + password with JWT tokens (access + refresh)
- Phone + OTP verification
- Email OTP login
- Social login (extensible)
- Password reset via email/phone OTP
- Token rotation and revocation

### Authorization Model (Module 3)
- **RBAC (Role-Based Access Control)** as the primary model
- Scope-based permissions for fine-grained control
- Resource-level authorization (ownership validation)
- Policy-driven access for complex scenarios
- Admin APIs for managing roles/permissions

## Module Structure (16 Core Modules)

Refer to [Prompt.md](../farm/docs/api/Prompt.md) sections 1️⃣-1️⃣6️⃣ for detailed module specifications:
1. Core Framework – REST abstractions, versioning, configuration
2. Authentication – Multi-flow login/registration, token management
3. Authorization – RBAC, scopes, resource-level access
4. User & Identity – User lifecycle, multi-identifier support
5. OTP & Verification – Email/SMS OTP with rate limiting
6. Observability – Auto-generated trace IDs, distributed tracing
7. Logging & Audit – Structured logging, PII masking, audit trails
8. Traffic Management – Rate limiting, throttling, quotas
9. DDoS & Abuse Protection – WAF, bot detection, anomaly detection
10. Validation & Security – Input validation, XSS/SQL injection prevention
11. Performance & Caching – Redis integration, query optimization
12. Resilience – Timeouts, retries, circuit breakers
13. Data Standards – UUID/ULID generation, UTC timestamps, soft deletes
14. Testing & Quality – Contract testing, mocks, test factories
15. Documentation & DX – OpenAPI auto-generation, examples, error catalogs
16. DevOps & Deployment – CI/CD hooks, secrets management, health probes

## Developer Checklist Compliance
[API-Features.md](../farm/docs/api/API-Features.md) contains a 20-section developer checklist (250+ items) that all APIs must satisfy. The framework's purpose is to **automatically enforce** these requirements so developers cannot ship non-compliant APIs.

### Critical Checklist Categories
- Section 3: Headers & Traceability (MANDATORY) – All APIs must handle X-Correlation-Id, X-Transaction-Id, X-Request-Id
- Section 4-5: Auth & authz are mandatory, never optional
- Section 6-7: Input validation and security hardening enforced at framework level
- Section 13: Observability – Structured JSON logs with trace IDs in every log entry

## When Working on This Codebase

### Adding New Modules
- Create modules with clean boundaries and dependency inversion
- Expose extension points for team customization
- Ensure framework can auto-enforce standards (no opt-in compliance)
- Include feature toggles to enable/disable modules

### Documentation
- Update [Prompt.md](../farm/docs/api/Prompt.md) when changing module specifications
- Update [API-Features.md](../farm/docs/api/API-Features.md) when adding new checklist requirements
- All architectural decisions should reference specific sections from these documents

### Success Metrics
The framework succeeds if it:
- Reduces API development time by ≥60%
- Enforces 100% checklist compliance automatically
- Is safe for junior developers to use
- Scales from MVP to enterprise/platform APIs

## Project Setup & Implementation

### Technology Stack
- **Backend**: PHP (FPM/Apache) in Docker
- **Frontend**: ReactJS (Node build + Nginx) in Docker
- **Databases**:
  - **MySQL**: Transactional data (accessed ONLY via stored procedures)
  - **MongoDB**: Logs, audit trails, metrics, observability data
- **Infrastructure**: Redis (optional), Nginx, Docker Compose

### Folder Structure (Under `/farm` folder)
```
/farm
 ├── docker-compose.yml
 ├── .env
 ├── backend/
 │    ├── app/              # Core application code
 │    ├── modules/          # Feature modules (pluggable)
 │    ├── database/
 │    │    ├── mysql/
 │    │    │    ├── tables/              # Table DDL per module
 │    │    │    ├── stored_procedures/   # All DB operations
 │    │    │    └── migrations/          # Versioned schema changes
 │    │    └── mongo/
 │    │         └── indexes/             # MongoDB index definitions
 │    ├── logs/
 │    └── bootstrap/
 ├── frontend/
 │    ├── src/
 │    ├── modules/          # Feature modules matching backend
 │    └── docker/
 └── infra/
      ├── nginx/
      ├── redis/
      └── scripts/
```

### Critical Implementation Rules

#### Database Access (NON-NEGOTIABLE)
🚫 **NO DIRECT SQL QUERIES FROM API CODE**
- All MySQL writes/reads MUST use stored procedures: `CALL stored_procedure(...)`
- Block raw `SELECT/INSERT/UPDATE/DELETE` at framework level
- Each module registers its own tables + stored procedures
- Read-only queries may use views (optional)

#### MongoDB Usage (MANDATORY)
MongoDB is exclusively for observability and audit:
- Application logs (structured JSON)
- Access logs with trace IDs
- Audit trails (who, what, when)
- Security events
- Performance metrics
- **Never** for business transactional data

**Required Indexes**: `correlation_id`, `transaction_id`, `timestamp`

#### Module Registration Pattern
Each module must be self-contained and register:
1. Database tables (MySQL DDL)
2. Stored procedures (all CRUD operations)
3. MongoDB collections (if needed)
4. API routes
5. Frontend components

### Development Workflow

#### Setting Up New Features
1. Create module under `/backend/modules/{feature_name}/`
2. Define MySQL tables in `/database/mysql/tables/{feature_name}.sql`
3. Write stored procedures in `/database/mysql/stored_procedures/{feature_name}/`
4. Create module registration file (auto-registers DB + routes)
5. Create corresponding frontend module in `/frontend/modules/{feature_name}/`

#### Docker Environment
- All services run in Docker (no local PHP/Node required)
- Configuration via `.env` file only
- Health checks for all containers
- Support both dev & prod profiles
- Restart-safe container design

### Testing Approach
- Unit tests for business logic
- Stored procedure test scripts (MySQL)
- Contract testing for API schemas
- Load testing hooks for performance validation
- Integration tests against Docker stack

### Deliverables Checklist
Refer to [Base-Prompt.md](../farm/docs/api/Base-Prompt.md) for complete setup requirements:
1. `docker-compose.yml` with all services
2. Backend framework structure with module system
3. Sample module (tables + stored procedures + APIs)
4. MongoDB logging schema
5. Auth module (email/password + OTP flows)
6. ReactJS sample feature
7. Setup & run documentation
