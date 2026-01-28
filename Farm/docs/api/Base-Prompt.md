# 🧠 **MASTER PROMPT – Modular API Framework with Dockerized PHP, MySQL, MongoDB & React**

---

## 🎭 **ROLE**

You are a **Principal Platform Architect & Framework Engineer** responsible for designing and implementing a **production-ready, modular API development framework** with **Docker-based local & cloud environments**.

---

## 🎯 **OBJECTIVE**

Design a **framework-level project setup** under a **`/farm` folder**, that provides:

* Dockerized **PHP backend**
* Dockerized **ReactJS frontend**
* **MySQL** for transactional data (ONLY via stored procedures)
* **MongoDB** for logs, audit trails, metrics, and common logging
* Modular architecture enforcing **enterprise API standards by default**
* Automatic **DB schema + stored procedure setup via modules**
* Security, observability, and governance enforced at framework level

---

## 🗂️ **ROOT FOLDER STRUCTURE (MANDATORY)**

```
/farm
 ├── docker-compose.yml
 ├── .env
 ├── backend/
 │    ├── app/
 │    ├── modules/
 │    ├── database/
 │    │    ├── mysql/
 │    │    │    ├── tables/
 │    │    │    ├── stored_procedures/
 │    │    │    └── migrations/
 │    │    └── mongo/
 │    │         └── indexes/
 │    ├── logs/
 │    └── bootstrap/
 ├── frontend/
 │    ├── src/
 │    ├── modules/
 │    └── docker/
 └── infra/
      ├── nginx/
      ├── redis/
      └── scripts/
```

---

## 🐳 **DOCKER & ENVIRONMENT REQUIREMENTS**

### docker-compose.yml MUST include:

* PHP (FPM or Apache)
* MySQL
* MongoDB
* ReactJS (Node build + Nginx serve)
* Optional Redis
* Shared network
* Volume mapping
* Health checks

### Rules:

* No service runs outside Docker
* All configs via `.env`
* Containers must be restart-safe
* Dev & Prod profiles supported

---

## 🧱 **BACKEND FRAMEWORK REQUIREMENTS (PHP)**

### 1️⃣ **Modular Architecture**

* Each feature is a self-contained module
* Modules can:

  * Register routes
  * Register DB tables
  * Register stored procedures
  * Register Mongo collections
* Modules must be pluggable / removable

---

### 2️⃣ **STRICT DATABASE RULE (NON-NEGOTIABLE)**

🚫 **NO DIRECT SQL QUERIES ALLOWED FROM API**

✅ **ALL transactional operations MUST be done via MySQL STORED PROCEDURES ONLY**

#### Enforcement Rules:

* PHP DB layer must:

  * Block raw `SELECT / INSERT / UPDATE / DELETE`
  * Allow only `CALL stored_procedure(...)`
* Read-only queries may be allowed via views (optional)
* All writes = stored procedures

---

### 3️⃣ **MySQL DATABASE STRUCTURE**

* `/database/mysql/tables/`
  → Table creation scripts per module

* `/database/mysql/stored_procedures/`
  → Stored procedures per module & operation

* `/database/mysql/migrations/`
  → Versioned schema changes

Each module must:

* Auto-register its tables
* Auto-register its stored procedures
* Support idempotent DB setup

---

### 4️⃣ **MONGODB USAGE (MANDATORY)**

MongoDB must be used for:

* Application logs
* Access logs
* Audit logs
* Security events
* Correlation & Transaction tracing
* Performance metrics

#### Mongo Rules:

* One collection per concern
* Indexed on:

  * correlation_id
  * transaction_id
  * timestamp
* No business transactional data in Mongo

---

## 🔍 **OBSERVABILITY (FRAMEWORK-LEVEL ENFORCED)**

Framework must automatically:

* Generate `X-Correlation-Id`
* Generate `X-Transaction-Id`
* Generate `X-Request-Id`
* Propagate IDs across services
* Log everything into MongoDB
* Attach IDs to API responses

---

## 🔐 **SECURITY & AUTH MODULES (PREBUILT)**

### Authentication (Mandatory Modules)

* Email + Password
* Phone + OTP
* Email OTP
* Forgot password (Email + Phone)
* Token-based auth (JWT)

### Authorization

* Role-based access (RBAC)
* Permission mapping
* Resource-level access

---

## 🚦 **TRAFFIC & DDOS PROTECTION**

* Rate limiting
* Throttling
* Burst control
* IP filtering
* Bot protection hooks
* API Gateway readiness

---

## 🧪 **TESTING & QUALITY**

* Unit testing support
* Stored procedure test scripts
* Contract testing support
* API schema validation
* Load testing hooks

---

## 📄 **DOCUMENTATION & DX**

* Auto-generated OpenAPI spec
* Postman collection generation
* Error code catalog
* Module README auto-generation

---

## 🚀 **FRONTEND (REACTJS) REQUIREMENTS**

* Dockerized React app
* Modular feature folders
* API client auto-integration
* Token handling
* Role-based UI rendering
* Environment-based configs

---

## 🛑 **NON-NEGOTIABLE FRAMEWORK RULES**

* ❌ No API without authentication
* ❌ No API without logging to MongoDB
* ❌ No DB write without stored procedure
* ❌ No API without Correlation & Transaction ID
* ❌ No hardcoded config values

---

## 📦 **DELIVERABLES EXPECTED**

1. `docker-compose.yml`
2. Backend framework structure
3. Sample module:

   * Tables
   * Stored procedures
   * APIs
4. Mongo logging schema
5. Auth module implementation
6. ReactJS sample feature
7. Setup & run documentation

---

## ✅ **SUCCESS CRITERIA**

The framework should:

* Be usable by **junior developers safely**
* Enforce **100% API checklist compliance**
* Support **enterprise audit & scale**
* Be production-ready out of the box
