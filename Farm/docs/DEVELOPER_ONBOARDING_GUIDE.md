# 🚀 Developer Onboarding Guide - PHPFrarm Framework

**Welcome to PHPFrarm!** This guide will take you from zero to productive in **under 2 hours**.

---

## 📋 **Prerequisites**

Before you begin, ensure you have:
- ✅ Docker Desktop installed
- ✅ Git installed
- ✅ VS Code or similar IDE
- ✅ Basic PHP knowledge
- ✅ REST API concepts understanding
- ✅ Command line comfort

---

## 🎯 **Onboarding Path (120 minutes)**

### Phase 1: Setup & First Run (30 min)
### Phase 2: Understanding Architecture (30 min)
### Phase 3: Building Your First API (40 min)
### Phase 4: Advanced Patterns (20 min)

---

## 📦 **Phase 1: Setup & First Run (30 min)**

### Step 1: Clone & Environment Setup (10 min)

```bash
# Clone repository
cd C:\Users\Deepak\OneDrive\Desktop\PHPFrarm
cd Farm

# Copy environment file
copy .env.example .env

# Review and update .env (MySQL, MongoDB, Redis credentials)
code .env
```

### Step 2: Start Docker Services (10 min)

```powershell
# Start all services
docker-compose up -d

# Verify services are running
docker ps

# Expected output:
# - farm-backend (PHP)
# - farm-mysql
# - farm-mongodb
# - farm-redis
# - farm-frontend (ReactJS)
```

### Step 3: Database Setup (10 min)

```powershell
# Access backend container
docker exec -it farm-backend bash

# Run database migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed

# Exit container
exit
```

### Step 4: Verify Installation

```powershell
# Test backend health
curl http://localhost:8000/api/v1/health

# Expected response:
# {
#   "success": true,
#   "data": {
#     "status": "healthy",
#     "services": { ... }
#   }
# }
```

✅ **Checkpoint:** If health check passes, you're ready for Phase 2!

---

## 🏗️ **Phase 2: Understanding Architecture (30 min)**

### Step 1: Read Core Architecture (10 min)

**Required Reading:**
1. [ARCHITECTURE.md](architecture/ARCHITECTURE.md) - System overview
2. [BACKEND_ARCHITECTURE.md](architecture/BACKEND_ARCHITECTURE.md) - Backend patterns

**Key Concepts:**
- ✅ Single entry point (`public/index.php`)
- ✅ Attribute-based routing
- ✅ Service layer pattern (MANDATORY)
- ✅ Stored procedures ONLY for database access
- ✅ MongoDB for logs/audit, MySQL for data

### Step 2: Explore Folder Structure (10 min)

```
/Farm
├── backend/
│   ├── app/
│   │   ├── Core/              # Framework core
│   │   ├── Middleware/        # Request/response middleware
│   │   └── Services/          # Shared services
│   ├── modules/               # Feature modules (your work here)
│   │   ├── Auth/              # Authentication module
│   │   ├── User/              # User management
│   │   ├── System/            # System APIs
│   │   └── [YourModule]/      # Your new modules
│   ├── config/                # Configuration files
│   ├── database/
│   │   ├── mysql/             # MySQL schemas & stored procedures
│   │   └── mongo/             # MongoDB indexes
│   └── public/                # Entry point
├── frontend/                  # ReactJS frontend
└── docs/                      # Documentation (you are here)
```

### Step 3: Study a Complete Module (10 min)

**Open these files:**
1. `backend/modules/Auth/Controllers/PhoneLoginController.php`
2. `backend/modules/Auth/Services/PhoneAuthService.php`
3. `backend/modules/Auth/DAO/UserDAO.php`

**Notice the pattern:**
```
Controller → Service → DAO → Stored Procedure → Database
```

**Key Observations:**
- ✅ Controller ONLY validates & delegates
- ✅ Service contains business logic
- ✅ DAO calls stored procedures ONLY
- ✅ No raw SQL queries anywhere

✅ **Checkpoint:** Can you trace a request from route to database?

---

## 🛠️ **Phase 3: Building Your First API (40 min)**

### Step 1: Create Module Structure (5 min)

```powershell
# Create your module folder
cd backend/modules
mkdir MyFeature

# Create standard structure
cd MyFeature
mkdir Controllers Services DAO
```

### Step 2: Create Database Schema (10 min)

**File:** `backend/database/mysql/tables/my_feature.sql`

```sql
-- Table: items
CREATE TABLE IF NOT EXISTS items (
    item_id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Step 3: Create Stored Procedures (10 min)

**File:** `backend/database/mysql/stored_procedures/my_feature/sp_create_item.sql`

```sql
DELIMITER $$

CREATE PROCEDURE sp_create_item(
    IN p_item_id VARCHAR(36),
    IN p_name VARCHAR(100),
    IN p_description TEXT,
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    INSERT INTO items (item_id, name, description)
    VALUES (p_item_id, p_name, p_description);
    
    SELECT JSON_OBJECT(
        'success', TRUE,
        'item_id', p_item_id,
        'message', 'Item created successfully'
    ) as result;
END$$

DELIMITER ;
```

**File:** `backend/database/mysql/stored_procedures/my_feature/sp_get_item.sql`

```sql
DELIMITER $$

CREATE PROCEDURE sp_get_item(
    IN p_item_id VARCHAR(36)
)
BEGIN
    SELECT 
        item_id,
        name,
        description,
        status,
        created_at
    FROM items
    WHERE item_id = p_item_id
    AND deleted_at IS NULL;
END$$

DELIMITER ;
```

### Step 4: Create DAO (5 min)

**File:** `backend/modules/MyFeature/DAO/ItemDAO.php`

```php
<?php

namespace PHPFrarm\Modules\MyFeature\DAO;

use PHPFrarm\Core\Database;

class ItemDAO
{
    public function createItem(string $itemId, string $name, string $description, string $correlationId): array
    {
        return Database::callProcedure('sp_create_item', [
            $itemId,
            $name,
            $description,
            $correlationId
        ]);
    }

    public function getItem(string $itemId): ?array
    {
        $result = Database::callProcedure('sp_get_item', [$itemId]);
        return $result[0] ?? null;
    }
}
```

### Step 5: Create Service (5 min)

**File:** `backend/modules/MyFeature/Services/ItemService.php`

```php
<?php

namespace PHPFrarm\Modules\MyFeature\Services;

use PHPFrarm\Modules\MyFeature\DAO\ItemDAO;
use PHPFrarm\Core\Utils\UuidGenerator;

class ItemService
{
    private ItemDAO $itemDAO;

    public function __construct()
    {
        $this->itemDAO = new ItemDAO();
    }

    public function createItem(string $name, string $description, string $correlationId): array
    {
        $itemId = UuidGenerator::v4();
        
        $result = $this->itemDAO->createItem($itemId, $name, $description, $correlationId);
        
        return [
            'item_id' => $itemId,
            'name' => $name,
            'description' => $description
        ];
    }

    public function getItem(string $itemId): ?array
    {
        return $this->itemDAO->getItem($itemId);
    }
}
```

### Step 6: Create Controller (5 min)

**File:** `backend/modules/MyFeature/Controllers/ItemController.php`

```php
<?php

namespace PHPFrarm\Modules\MyFeature\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Request;
use PHPFrarm\Core\Validation\InputValidator;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\MyFeature\Services\ItemService;
use PHPFrarm\Core\Exceptions\HttpExceptions\BadRequestHttpException;

#[RouteGroup('/api/v1/items', middleware: ['cors', 'auth', 'rateLimit'])]
class ItemController
{
    private ItemService $itemService;

    public function __construct()
    {
        $this->itemService = new ItemService();
    }

    /**
     * Create new item
     * POST /api/v1/items
     */
    #[Route('/', method: 'POST', middleware: ['jsonParser'])]
    public function create(): Response
    {
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            $data = Request::getBody();
            
            $validator = new InputValidator([
                'name' => 'required|string|min:3|max:100',
                'description' => 'string|max:500'
            ]);
            
            $validatedData = $validator->validate($data);
            
            Logger::info('Creating item', [
                'name' => $validatedData['name'],
                'correlation_id' => $correlationId
            ]);
            
            $item = $this->itemService->createItem(
                $validatedData['name'],
                $validatedData['description'] ?? '',
                $correlationId
            );
            
            return Response::success($item, 'Item created successfully', 201);
            
        } catch (\Exception $e) {
            Logger::error('Failed to create item', [
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            throw new BadRequestHttpException('Failed to create item');
        }
    }

    /**
     * Get item by ID
     * GET /api/v1/items/{id}
     */
    #[Route('/{id}', method: 'GET')]
    public function get(string $id): Response
    {
        $item = $this->itemService->getItem($id);
        
        if (!$item) {
            throw new BadRequestHttpException('Item not found');
        }
        
        return Response::success($item);
    }
}
```

### Step 7: Test Your API (5 min)

```powershell
# Create an item
curl -X POST http://localhost:8000/api/v1/items \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Test Item",
    "description": "My first API item"
  }'

# Get the item (use item_id from response)
curl http://localhost:8000/api/v1/items/{item_id} \
  -H "Authorization: Bearer YOUR_TOKEN"
```

✅ **Checkpoint:** Did you successfully create and retrieve an item?

---

## 🎓 **Phase 4: Advanced Patterns (20 min)**

### Pattern 1: Service Layer Usage (5 min)

**Rule:** ALWAYS extract business logic to services

**Bad (Don't do this):**
```php
class MyController {
    public function create() {
        // ❌ Business logic in controller
        $id = bin2hex(random_bytes(16));
        $hash = hash('sha256', $data);
        Database::callProcedure(...);
    }
}
```

**Good (Do this):**
```php
class MyController {
    public function create() {
        // ✅ Delegate to service
        return $this->myService->createItem($data);
    }
}

class MyService {
    public function createItem($data) {
        // Business logic here
        $id = UuidGenerator::v4();
        $hash = hash('sha256', $data);
        return $this->dao->create($id, $hash);
    }
}
```

### Pattern 2: Stored Procedures ONLY (5 min)

**Rule:** Never write raw SQL in PHP code

**Bad (Don't do this):**
```php
❌ $query = "SELECT * FROM users WHERE id = ?";
❌ $db->prepare($query)->execute([$id]);
```

**Good (Do this):**
```php
✅ Database::callProcedure('sp_get_user', [$id]);
```

### Pattern 3: Standard Response Format (5 min)

**Always use:**
```php
// Success
return Response::success($data, 'Message', 200);

// Created
return Response::success($data, 'Created', 201);

// Error (throw exceptions, framework handles it)
throw new BadRequestHttpException('Error message');
throw new UnauthorizedHttpException('Not authorized');
throw new NotFoundHttpException('Not found');
```

### Pattern 4: Logging & Tracing (5 min)

**Always include correlation ID:**
```php
$correlationId = TraceContext::getCorrelationId();

Logger::info('Operation started', [
    'entity_id' => $id,
    'correlation_id' => $correlationId
]);

// Framework automatically adds transaction_id and request_id
```

✅ **Checkpoint:** Do you understand the 4 core patterns?

---

## 📚 **Required Reading (Next Steps)**

Now that you've built your first API, read these documents:

### Essential (Day 1-2)
1. **[API-Features.md](api/API-Features.md)** - Developer checklist (250+ items you must follow)
2. **[DEVELOPER_GUIDE.md](guides/DEVELOPER_GUIDE.md)** - Complete development patterns
3. **[CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md)** - Code quality standards

### Feature Implementation (Day 3-5)
4. **[OTP_SECURITY_GUIDE.md](guides/OTP_SECURITY_GUIDE.md)** - Implementing OTP
5. **[CACHING_GUIDE.md](guides/CACHING_GUIDE.md)** - Adding caching
6. **[ROLES_PERMISSIONS_GUIDE.md](guides/ROLES_PERMISSIONS_GUIDE.md)** - RBAC system
7. **[TESTING_GUIDE.md](guides/TESTING_GUIDE.md)** - Writing tests

### Architecture Deep-Dive (Week 2)
8. **[Prompt.md](api/Prompt.md)** - 16 core modules explained
9. **[MODULES_GUIDE.md](architecture/MODULES_GUIDE.md)** - Module system
10. **[BACKEND_ARCHITECTURE.md](architecture/BACKEND_ARCHITECTURE.md)** - Complete architecture

---

## ✅ **Checklist: Am I Ready?**

After completing this guide, you should be able to answer YES to all:

**Environment:**
- [ ] Can start/stop Docker services
- [ ] Can access backend container
- [ ] Can run health check API
- [ ] Can view logs

**Architecture:**
- [ ] Understand single entry point
- [ ] Know Controller → Service → DAO → SP pattern
- [ ] Understand why we use stored procedures
- [ ] Know difference between MySQL (data) vs MongoDB (logs)

**Development:**
- [ ] Created database table
- [ ] Created stored procedures
- [ ] Created DAO, Service, Controller
- [ ] Tested API with curl
- [ ] Understand response formats

**Standards:**
- [ ] Always use service layer
- [ ] Always use stored procedures
- [ ] Always use Response:: methods
- [ ] Always log with correlation ID

---

## 🆘 **Common Issues & Solutions**

### Issue: Docker services won't start
**Solution:**
```powershell
# Check port conflicts
netstat -an | findstr ":8000"
netstat -an | findstr ":3306"

# Restart Docker Desktop
# Then retry: docker-compose up -d
```

### Issue: Database connection failed
**Solution:**
```powershell
# Check .env has correct credentials
# Verify MySQL container is running
docker logs farm-mysql

# Try manual connection
docker exec -it farm-mysql mysql -u root -p
```

### Issue: Route not found (404)
**Solution:**
```php
// Verify attribute routing is correct
#[RouteGroup('/api/v1/items', ...)]
#[Route('/', method: 'POST', ...)]

// Clear route cache
php artisan route:clear
```

### Issue: Stored procedure not found
**Solution:**
```sql
-- Check procedure exists
SHOW PROCEDURE STATUS WHERE Db = 'your_db_name';

-- Re-run migration
SOURCE backend/database/mysql/stored_procedures/my_feature/sp_create_item.sql;
```

---

## 🎯 **Next Steps**

**Week 1: Build 3 APIs**
- Create module for your domain
- Implement CRUD operations
- Add authentication
- Write tests

**Week 2: Advanced Features**
- Add caching to your APIs
- Implement rate limiting
- Add role-based access
- Set up CI/CD

**Week 3: Production Readiness**
- Review [API-Features.md](api/API-Features.md) checklist
- Complete all 250+ checklist items
- Perform security audit
- Load test your APIs

---

## 📞 **Getting Help**

**Documentation:**
- [INDEX.md](INDEX.md) - Find any document
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Quick command reference

**Common Tasks:**
- Creating API → This guide
- Adding auth → [OTP_SECURITY_GUIDE.md](guides/OTP_SECURITY_GUIDE.md)
- Testing → [TESTING_GUIDE.md](guides/TESTING_GUIDE.md)
- Debugging → [QUICK_REFERENCE.md](QUICK_REFERENCE.md)

---

## 🏆 **Congratulations!**

You've completed the PHPFrarm onboarding! You now know:

✅ How to set up the environment  
✅ How to understand the architecture  
✅ How to build a complete API  
✅ How to follow best practices  

**You're ready to contribute to production code!**

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-28  
**Estimated Time:** 2 hours  
**Difficulty:** Beginner to Intermediate
