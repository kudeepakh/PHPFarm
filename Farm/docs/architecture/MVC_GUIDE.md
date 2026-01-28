# 🏗️ MVC Architecture Guide

## 📂 Module Structure (MVC Pattern)

Every module follows this structure:

```
modules/
└── {ModuleName}/
    ├── module.php                  # Module configuration
    ├── routes.php                  # Route definitions ONLY
    ├── Controllers/                # HTTP request/response handling
    │   └── {Name}Controller.php
    ├── Services/                   # Business logic
    │   └── {Name}Service.php
    ├── DAO/                        # Data Access Objects (DB operations)
    │   └── {Name}DAO.php
    └── DTO/                        # Data Transfer Objects (validation)
        └── {Name}DTO.php
```

---

## 🎯 Layer Responsibilities

### 1️⃣ **Routes** (`routes.php`)
**Purpose:** Configuration only - no logic

✅ **DO:**
- Define route paths
- Configure middleware
- Wire controllers to routes
- Group routes

❌ **DON'T:**
- Include business logic
- Access database directly
- Perform validation
- Generate responses

**Example:**
```php
use PHPFrarm\Core\Router;
use PHPFrarm\Modules\Auth\Controllers\AuthController;

$controller = new AuthController();

Router::group('/api/auth', ['cors', 'rateLimit'], function() use ($controller) {
    Router::post('/login', [$controller, 'login'], ['jsonParser']);
    Router::post('/register', [$controller, 'register'], ['jsonParser']);
});
```

---

### 2️⃣ **Controllers** (`Controllers/`)
**Purpose:** Handle HTTP requests and responses

✅ **DO:**
- Parse request data
- Validate using DTOs
- Call services for business logic
- Format HTTP responses
- Handle HTTP errors

❌ **DON'T:**
- Include business logic
- Access database directly
- Perform complex calculations
- Make external API calls

**Example:**
```php
namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Modules\Auth\DTO\LoginRequestDTO;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(array $request): void
    {
        $dto = new LoginRequestDTO($request['body']);
        
        // Validate
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('Validation failed', $errors);
            return;
        }

        // Call service
        try {
            $result = $this->authService->login($dto->email, $dto->password);
            Response::success($result, 'Login successful');
        } catch (\Exception $e) {
            Response::unauthorized($e->getMessage());
        }
    }
}
```

---

### 3️⃣ **Services** (`Services/`)
**Purpose:** Business logic and orchestration

✅ **DO:**
- Implement business rules
- Orchestrate multiple DAOs
- Handle transactions
- Call external APIs
- Generate tokens/hashes
- Implement complex algorithms

❌ **DON'T:**
- Access $_POST, $_GET directly
- Return HTTP responses
- Execute raw SQL
- Format response data

**Example:**
```php
namespace PHPFrarm\Modules\Auth\Services;

use PHPFrarm\Modules\Auth\DAO\UserDAO;
use PHPFrarm\Core\Logger;

class AuthService
{
    private UserDAO $userDAO;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
    }

    public function register(string $email, string $password, ?string $firstName, ?string $lastName): array
    {
        // Business logic: Check if user exists
        $existingUser = $this->userDAO->getUserByEmail($email);
        if ($existingUser) {
            throw new \Exception('Email already registered');
        }

        // Business logic: Generate ID and hash password
        $userId = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Persist via DAO
        $this->userDAO->createUser($userId, $email, $passwordHash, $firstName, $lastName);

        // Log the action
        Logger::audit('User registered', ['user_id' => $userId]);

        return ['user_id' => $userId, 'email' => $email];
    }
}
```

---

### 4️⃣ **DAOs** (`DAO/`)
**Purpose:** Database operations ONLY via stored procedures

✅ **DO:**
- Call stored procedures
- Map DB results to arrays
- Handle DB-specific errors
- Execute ONLY via `Database::callProcedure()`

❌ **DON'T:**
- Write raw SQL queries
- Include business logic
- Call external services
- Format response data

**Example:**
```php
namespace PHPFrarm\Modules\Auth\DAO;

use PHPFrarm\Core\Database;

class UserDAO
{
    public function createUser(string $userId, string $email, string $passwordHash, ?string $firstName, ?string $lastName): array
    {
        return Database::callProcedure('sp_create_user', [
            $userId,
            $email,
            $passwordHash,
            $firstName ?? '',
            $lastName ?? ''
        ]);
    }

    public function getUserByEmail(string $email): ?array
    {
        $users = Database::callProcedure('sp_get_user_by_email', [$email]);
        return !empty($users) ? $users[0] : null;
    }

    public function updateLastLogin(string $userId): void
    {
        Database::callProcedure('sp_update_user_last_login', [$userId]);
    }
}
```

---

### 5️⃣ **DTOs** (`DTO/`)
**Purpose:** Data validation and transformation

✅ **DO:**
- Validate request data
- Transform data formats
- Provide type safety
- Return validation errors

❌ **DON'T:**
- Access database
- Include business logic
- Call external services
- Generate responses

**Example:**
```php
namespace PHPFrarm\Modules\Auth\DTO;

class LoginRequestDTO
{
    public string $email;
    public string $password;

    public function __construct(array $data)
    {
        $this->email = $data['email'] ?? '';
        $this->password = $data['password'] ?? '';
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->email)) {
            $errors[] = 'Email is required';
        }

        if (empty($this->password)) {
            $errors[] = 'Password is required';
        }

        return $errors;
    }
}
```

---

## 🔄 Request Flow

```
1. HTTP Request → routes.php (route matching)
                      ↓
2. Middleware Chain (auth, rateLimit, jsonParser)
                      ↓
3. Controller (parse request, validate)
                      ↓
4. DTO (validate input data)
                      ↓
5. Service (business logic)
                      ↓
6. DAO (database operations via stored procedures)
                      ↓
7. Service (process results)
                      ↓
8. Controller (format HTTP response)
                      ↓
9. HTTP Response (JSON with trace IDs)
```

---

## 📝 Creating a New Module

### Step 1: Create Folder Structure
```bash
mkdir -p modules/Product/{Controllers,Services,DAO,DTO}
```

### Step 2: Create `module.php`
```php
<?php
return [
    'name' => 'Product',
    'enabled' => true,
    'description' => 'Product management module',
    'version' => '1.0.0',
    'bootstrap' => function() {
        // Optional initialization code
    }
];
```

### Step 3: Create DTO
```php
// modules/Product/DTO/CreateProductDTO.php
namespace PHPFrarm\Modules\Product\DTO;

class CreateProductDTO
{
    public string $name;
    public float $price;

    public function __construct(array $data)
    {
        $this->name = $data['name'] ?? '';
        $this->price = (float)($data['price'] ?? 0);
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->name)) $errors[] = 'Name is required';
        if ($this->price <= 0) $errors[] = 'Price must be positive';
        return $errors;
    }
}
```

### Step 4: Create DAO
```php
// modules/Product/DAO/ProductDAO.php
namespace PHPFrarm\Modules\Product\DAO;

use PHPFrarm\Core\Database;

class ProductDAO
{
    public function createProduct(string $id, string $name, float $price): array
    {
        return Database::callProcedure('sp_create_product', [$id, $name, $price]);
    }

    public function getProductById(string $id): ?array
    {
        $products = Database::callProcedure('sp_get_product_by_id', [$id]);
        return !empty($products) ? $products[0] : null;
    }
}
```

### Step 5: Create Service
```php
// modules/Product/Services/ProductService.php
namespace PHPFrarm\Modules\Product\Services;

use PHPFrarm\Modules\Product\DAO\ProductDAO;

class ProductService
{
    private ProductDAO $productDAO;

    public function __construct()
    {
        $this->productDAO = new ProductDAO();
    }

    public function createProduct(string $name, float $price): array
    {
        $productId = bin2hex(random_bytes(16));
        $this->productDAO->createProduct($productId, $name, $price);
        return ['id' => $productId, 'name' => $name, 'price' => $price];
    }
}
```

### Step 6: Create Controller
```php
// modules/Product/Controllers/ProductController.php
namespace PHPFrarm\Modules\Product\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Modules\Product\Services\ProductService;
use PHPFrarm\Modules\Product\DTO\CreateProductDTO;

class ProductController
{
    private ProductService $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    public function create(array $request): void
    {
        $dto = new CreateProductDTO($request['body']);
        
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('Validation failed', $errors);
            return;
        }

        try {
            $result = $this->productService->createProduct($dto->name, $dto->price);
            Response::success($result, 'Product created', 201);
        } catch (\Exception $e) {
            Response::serverError('Failed to create product');
        }
    }
}
```

### Step 7: Create Routes
```php
// modules/Product/routes.php
use PHPFrarm\Core\Router;
use PHPFrarm\Modules\Product\Controllers\ProductController;

$controller = new ProductController();

Router::group('/api/products', ['auth', 'rateLimit'], function() use ($controller) {
    Router::post('/', [$controller, 'create'], ['jsonParser']);
});
```

### Step 8: Register Autoloader & Restart
```bash
docker-compose exec backend composer dump-autoload
docker-compose restart backend
```

---

## ✅ Benefits of This Architecture

1. **Separation of Concerns** - Each layer has a single responsibility
2. **Testability** - Easy to mock and test each layer
3. **Maintainability** - Changes in one layer don't affect others
4. **Reusability** - Services can be reused across controllers
5. **Security** - Database access ONLY via stored procedures
6. **Scalability** - Easy to add new features without breaking existing code

---

## 🎯 Quick Reference

| Layer | Namespace | Purpose | Can Access |
|-------|-----------|---------|------------|
| Routes | N/A | Configuration | Controllers only |
| Controllers | `Controllers\` | HTTP handling | Services, DTOs, Response |
| Services | `Services\` | Business logic | DAOs, Logger, Other Services |
| DAOs | `DAO\` | Database ops | Database class only |
| DTOs | `DTO\` | Validation | Nothing (pure data) |

---

## 🚫 Anti-Patterns to Avoid

❌ Controller accessing DAO directly
❌ Service returning Response objects
❌ DAO containing business logic
❌ Routes with inline functions (use controllers)
❌ Raw SQL queries anywhere
❌ Global variables
❌ Mixing concerns in a single file
