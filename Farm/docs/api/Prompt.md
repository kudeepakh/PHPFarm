# 🧠 **MASTER PROMPT – Modular API Development Framework**

> **Role:**
> You are a **Principal Software Architect** designing an **enterprise-grade, modular API development framework** focused on **REST APIs**, **security**, **observability**, and **scalability**.

> **Objective:**
> Design and implement a **reusable, extensible, framework-level API platform** that allows teams to **build production-ready APIs rapidly** while **automatically enforcing enterprise standards**.

---

## 🎯 **CORE GOALS**

1. **Modular architecture** – each concern is a pluggable module
2. **Security-first** – auth, roles, abuse protection prebuilt
3. **Observability-by-default** – correlation & transaction tracing built-in
4. **Checklist compliance** – all enterprise API best practices enforced
5. **Framework-level enforcement** – developers cannot bypass standards

---

## 🧩 **REQUIRED MODULES (MANDATORY)**

### 1️⃣ **Core Framework Module**

* REST request/response abstraction
* Standard response & error envelope
* Central exception handling
* API versioning support
* Configuration management
* Environment awareness

---

### 2️⃣ **Authentication Module (Prebuilt & Extensible)**

Support **multiple login & registration flows**:

#### Login / Registration

* Email + password
* Phone number + OTP
* Email OTP login
* Social login (extensible)

#### Password Management

* Forgot password via email OTP
* Forgot password via phone OTP
* Password reset tokens
* Password history & policy

#### Token Management

* JWT access & refresh tokens
* Token expiry & rotation
* Token revocation
* Device/session tracking

---

### 3️⃣ **Authorization & Access Control Module**

* Role-based access control (RBAC)
* Scope-based permissions
* Resource-level authorization
* Ownership validation
* Policy-driven access (extensible)
* Admin APIs for role & permission management

---

### 4️⃣ **User & Identity Module**

* User lifecycle management
* Multi-identifier support (email, phone)
* Verification flows
* Account status (active, locked, suspended)
* Audit history

---

### 5️⃣ **OTP & Verification Module**

* Email OTP
* SMS OTP
* Configurable expiry
* Retry limits
* Rate limiting per OTP
* Replay attack prevention

---

### 6️⃣ **Observability & Traceability Module (MANDATORY BY DEFAULT)**

* Auto-generate **X-Correlation-Id**
* Auto-generate **X-Transaction-Id**
* Auto-generate **X-Request-Id**
* Propagate IDs across services
* Inject IDs into logs, metrics, errors
* Distributed tracing hooks

---

### 7️⃣ **Logging & Audit Module**

* Structured JSON logging
* Access logs
* Security logs
* Audit logs per transaction
* PII masking
* Log level control

---

### 8️⃣ **Traffic Management Module**

* Rate limiting
* Throttling
* Burst control
* Client-level quotas
* Dynamic limits

---

### 9️⃣ **DDoS & Abuse Protection Module**

* API Gateway integration
* WAF hooks
* Bot detection
* IP reputation filtering
* Geo-blocking support
* Anomaly detection

---

### 🔟 **Validation & Security Module**

* Input validation (headers, params, body)
* Payload size limits
* SQL injection prevention
* XSS protection
* CSRF protection
* Secure HTTP headers

---

### 1️⃣1️⃣ **Performance & Caching Module**

* Redis integration
* Response caching
* Cache invalidation
* Query optimization helpers
* Async job support

---

### 1️⃣2️⃣ **Resilience Module**

* Timeout management
* Retry policies
* Circuit breakers
* Graceful degradation
* Backpressure handling

---

### 1️⃣3️⃣ **Data Standards Module**

* UUID / ULID generator
* UTC timestamp enforcement
* Soft delete support
* Optimistic locking helpers

---

### 1️⃣4️⃣ **Testing & Quality Module**

* Contract testing support
* Mock server
* Test data factories
* Security test hooks
* Load testing support

---

### 1️⃣5️⃣ **Documentation & DX Module**

* OpenAPI auto-generation
* Example payloads
* Error catalog generation
* Postman collection export
* Developer onboarding docs

---

### 1️⃣6️⃣ **DevOps & Deployment Module**

* CI/CD hooks
* Environment configuration
* Secrets integration
* Zero-downtime deployment support
* Health & readiness probes

---

## 🏗️ **ARCHITECTURAL REQUIREMENTS**

* Clean modular boundaries
* Dependency inversion
* Framework enforces standards automatically
* Feature toggles per module
* Language & framework agnostic design

---

## 📐 **OUTPUT EXPECTATIONS**

Deliver:

1. **High-level architecture diagram**
2. **Module dependency diagram**
3. **Folder / package structure**
4. **Public APIs exposed by framework**
5. **Extension points for teams**
6. **Security & compliance guarantees**
7. **Sample API built using framework**
8. **Developer onboarding guide**

---

## 🛑 **NON-NEGOTIABLE RULES**

* No API without authentication
* No API without Correlation & Transaction IDs
* No API without rate limiting
* No API without audit logs
* No API without documentation

---

## 🟢 **SUCCESS CRITERIA**

The framework must:

* Reduce API development time by **≥60%**
* Enforce **100% checklist compliance**
* Be usable by **junior developers safely**
* Scale to **enterprise & platform APIs**

---

### ✅ **Instruction to Implementer**

Build this framework as if it will be used by **multiple teams, multiple products, and public APIs**.

