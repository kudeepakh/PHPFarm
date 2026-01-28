# 🚀 PHPFrarm Developer Guide - Building New Features

**Version:** 1.0.0  
**Last Updated:** January 18, 2026

This guide explains how to develop new features, modules, and APIs for the PHPFrarm framework following established standards and best practices.

---

## 📋 Table of Contents

1. [Quick Start - Creating a New Module](#1-quick-start---creating-a-new-module)
2. [Step-by-Step Feature Development](#2-step-by-step-feature-development)
3. [Database Development (MySQL + Stored Procedures)](#3-database-development)
4. [API Development Guidelines](#4-api-development-guidelines)
5. [Authentication & Authorization](#5-authentication--authorization)
6. [Input Validation](#6-input-validation)
7. [Caching Strategy](#7-caching-strategy)
8. [Testing Requirements](#8-testing-requirements)
9. [Documentation Requirements](#9-documentation-requirements)
10. [Deployment Checklist](#10-deployment-checklist)

---

## 1. Quick Start - Creating a New Module

### Option A: Use the CLI Scaffolding (Recommended)

```bash
# Generate a complete module with all components
php artisan make:module Blog --full

# Generate a basic module (controller + service only)
php artisan make:module Blog

# Generate with specific components
php artisan make:module Blog --with-dao --with-dto --with-migration
```

This generates:
```
modules/Blog/
├── Controllers/
│   └── BlogController.php      # REST API endpoints
├── Services/
│   └── BlogService.php         # Business logic
├── DAO/
│   └── BlogDAO.php             # Data access (stored procedures)
├── DTOs/
│   ├── CreateBlogDTO.php       # Input validation
│   └── UpdateBlogDTO.php
├── Tests/
│   └── BlogTest.php            # PHPUnit tests
└── BlogModule.php              # Module registration

database/mysql/
├── tables/blog.sql             # Table DDL
├── stored_procedures/blog/     # CRUD procedures
│   ├── sp_blog_create.sql
│   ├── sp_blog_get_by_id.sql
│   ├── sp_blog_update.sql
│   ├── sp_blog_delete.sql
│   ├── sp_blog_list.sql
│   ├── sp_blog_search.sql
│   ├── sp_blog_soft_delete.sql
│   └── sp_blog_restore.sql
└── migrations/
    └── 2026_01_18_create_blog_table.php
```

### Option B: Manual Creation

Follow the steps in [Section 2](#2-step-by-step-feature-development).

---

## 2. Step-by-Step Feature Development

### Step 1: Plan Your Feature

Before coding, answer these questions:

```
□ What is the feature name? (e.g., "Product Catalog")
□ What entities are involved? (e.g., Product, Category, Review)
□ What operations are needed? (CRUD, search, bulk operations?)
□ Who can access it? (roles, permissions needed)
□ Does it need caching? (read-heavy = yes)
□ Does it integrate with external services?
```

### Step 2: Create the Module Structure

```bash
# Create module directory
mkdir -p modules/Product/{Controllers,Services,DAO,DTOs,Tests}

# Create the module registration file
touch modules/Product/ProductModule.php
```

### Step 3: Define the Module Registration

```php
<?php
// modules/Product/ProductModule.php

namespace Modules\Product;

use PHPFrarm\Core\ModuleLoader;

class ProductModule
{
    public static function register(): void
    {
        ModuleLoader::registerModule('Product', [
            'controllers' => [
                Controllers\ProductController::class,
            ],
            'services' => [
                Services\ProductService::class,
            ],
            'middleware' => [
                // Module-specific middleware
            ],
            'routes' => [
                'prefix' => '/api/v1/products',
                'middleware' => ['auth', 'rate-limit'],
            ],
        ]);
    }
    
    public static function getDatabaseFiles(): array
    {
        return [
            'tables' => [
                __DIR__ . '/../../database/mysql/tables/products.sql',
            ],
            'procedures' => [
                __DIR__ . '/../../database/mysql/stored_procedures/products/',
            ],
        ];
    }
}
```

### Step 4: Create the Controller

```php
<?php
// modules/Product/Controllers/ProductController.php

namespace Modules\Product\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Documentation\Attributes\ApiDoc;
use PHPFrarm\Core\Documentation\Attributes\ApiParam;
use PHPFrarm\Core\Documentation\Attributes\ApiResponse;
use PHPFrarm\Core\Traffic\Attributes\RateLimit;
use PHPFrarm\Core\Cache\Attributes\Cacheable;
use Modules\Product\Services\ProductService;
use Modules\Product\DTOs\CreateProductDTO;
use Modules\Product\DTOs\UpdateProductDTO;

#[RouteGroup(
    prefix: '/api/v1/products',
    middleware: ['auth', 'throttle:60,1']
)]
class ProductController
{
    private ProductService $productService;
    
    public function __construct()
    {
        $this->productService = new ProductService();
    }
    
    /**
     * List all products with pagination
     */
    #[Route('/', methods: ['GET'])]
    #[ApiDoc(
        summary: 'List all products',
        description: 'Returns paginated list of products',
        tags: ['Products']
    )]
    #[ApiParam(name: 'page', in: 'query', type: 'integer', description: 'Page number')]
    #[ApiParam(name: 'per_page', in: 'query', type: 'integer', description: 'Items per page')]
    #[ApiResponse(status: 200, description: 'Product list')]
    #[RateLimit(requests: 100, window: 60)]
    #[Cacheable(ttl: 300, tags: ['products'])]
    public function index(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 20);
        
        $result = $this->productService->list($page, $perPage);
        
        Response::paginated(
            $result['data'],
            $result['total'],
            $page,
            $perPage
        );
    }
    
    /**
     * Get single product
     */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiDoc(summary: 'Get product by ID', tags: ['Products'])]
    #[ApiParam(name: 'id', in: 'path', type: 'string', format: 'ulid', required: true)]
    #[ApiResponse(status: 200, description: 'Product details')]
    #[ApiResponse(status: 404, description: 'Product not found')]
    #[Cacheable(ttl: 600, tags: ['products', 'product:{id}'])]
    public function show(string $id): void
    {
        $product = $this->productService->getById($id);
        
        if (!$product) {
            Response::notFound('Product not found');
        }
        
        Response::success($product);
    }
    
    /**
     * Create new product
     */
    #[Route('/', methods: ['POST'])]
    #[ApiDoc(summary: 'Create a new product', tags: ['Products'])]
    #[ApiResponse(status: 201, description: 'Product created')]
    #[ApiResponse(status: 400, description: 'Validation error')]
    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input using DTO
        $dto = CreateProductDTO::fromArray($data);
        $errors = $dto->validate();
        
        if (!empty($errors)) {
            Response::badRequest('Validation failed', $errors);
        }
        
        $product = $this->productService->create($dto);
        
        Response::success($product, 'Product created successfully', 201);
    }
    
    /**
     * Update product
     */
    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    #[ApiDoc(summary: 'Update a product', tags: ['Products'])]
    public function update(string $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $dto = UpdateProductDTO::fromArray($data);
        $errors = $dto->validate();
        
        if (!empty($errors)) {
            Response::badRequest('Validation failed', $errors);
        }
        
        $product = $this->productService->update($id, $dto);
        
        Response::success($product, 'Product updated successfully');
    }
    
    /**
     * Delete product (soft delete)
     */
    #[Route('/{id}', methods: ['DELETE'])]
    #[ApiDoc(summary: 'Delete a product', tags: ['Products'])]
    public function destroy(string $id): void
    {
        $this->productService->delete($id);
        
        Response::success(null, 'Product deleted successfully');
    }
}
```

### Step 5: Create the Service Layer

```php
<?php
// modules/Product/Services/ProductService.php

namespace Modules\Product\Services;

use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Utils\IdGenerator;
use PHPFrarm\Core\Cache\CacheManager;
use Modules\Product\DAO\ProductDAO;
use Modules\Product\DTOs\CreateProductDTO;
use Modules\Product\DTOs\UpdateProductDTO;

class ProductService
{
    private ProductDAO $dao;
    private CacheManager $cache;
    
    public function __construct()
    {
        $this->dao = new ProductDAO();
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * List products with pagination
     */
    public function list(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $products = $this->dao->list($perPage, $offset);
        $total = $this->dao->count();
        
        Logger::info('Products listed', [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ]);
        
        return [
            'data' => $products,
            'total' => $total
        ];
    }
    
    /**
     * Get product by ID
     */
    public function getById(string $id): ?array
    {
        $cacheKey = "product:{$id}";
        
        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $product = $this->dao->getById($id);
        
        if ($product) {
            $this->cache->set($cacheKey, $product, 600, ['products', "product:{$id}"]);
        }
        
        return $product;
    }
    
    /**
     * Create new product
     */
    public function create(CreateProductDTO $dto): array
    {
        $id = IdGenerator::generate(); // ULID by default
        
        $product = $this->dao->create([
            'id' => $id,
            'name' => $dto->name,
            'description' => $dto->description,
            'price' => $dto->price,
            'category_id' => $dto->categoryId,
            'status' => 'active',
        ]);
        
        // Invalidate list cache
        $this->cache->invalidateTag('products');
        
        Logger::audit('Product created', [
            'product_id' => $id,
            'name' => $dto->name
        ]);
        
        return $product;
    }
    
    /**
     * Update product
     */
    public function update(string $id, UpdateProductDTO $dto): array
    {
        $product = $this->dao->update($id, $dto->toArray());
        
        // Invalidate caches
        $this->cache->invalidateTag("product:{$id}");
        $this->cache->invalidateTag('products');
        
        Logger::audit('Product updated', ['product_id' => $id]);
        
        return $product;
    }
    
    /**
     * Soft delete product
     */
    public function delete(string $id): void
    {
        $this->dao->softDelete($id);
        
        // Invalidate caches
        $this->cache->invalidateTag("product:{$id}");
        $this->cache->invalidateTag('products');
        
        Logger::audit('Product deleted', ['product_id' => $id]);
    }
}
```

### Step 6: Create the DAO (Data Access Object)

```php
<?php
// modules/Product/DAO/ProductDAO.php

namespace Modules\Product\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Traits\SoftDelete;

/**
 * Product Data Access Object
 * 
 * ALL database operations MUST use stored procedures.
 * Direct SQL queries are BLOCKED by the framework.
 */
class ProductDAO
{
    use SoftDelete;
    
    protected string $table = 'products';
    
    /**
     * Create a new product
     */
    public function create(array $data): array
    {
        Database::callProcedure('sp_product_create', [
            $data['id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['category_id'],
            $data['status'],
        ]);
        
        return $this->getById($data['id']);
    }
    
    /**
     * Get product by ID
     */
    public function getById(string $id): ?array
    {
        $result = Database::callProcedure('sp_product_get_by_id', [$id]);
        
        return $result[0] ?? null;
    }
    
    /**
     * List products with pagination
     */
    public function list(int $limit, int $offset): array
    {
        return Database::callProcedure('sp_product_list', [$limit, $offset]);
    }
    
    /**
     * Count total products
     */
    public function count(): int
    {
        $result = Database::callProcedure('sp_product_count', []);
        
        return (int) ($result[0]['total'] ?? 0);
    }
    
    /**
     * Update product
     */
    public function update(string $id, array $data): array
    {
        Database::callProcedure('sp_product_update', [
            $id,
            $data['name'] ?? null,
            $data['description'] ?? null,
            $data['price'] ?? null,
            $data['category_id'] ?? null,
            $data['status'] ?? null,
        ]);
        
        return $this->getById($id);
    }
    
    /**
     * Search products
     */
    public function search(string $query, int $limit, int $offset): array
    {
        return Database::callProcedure('sp_product_search', [$query, $limit, $offset]);
    }
}
```

### Step 7: Create DTOs for Validation

```php
<?php
// modules/Product/DTOs/CreateProductDTO.php

namespace Modules\Product\DTOs;

class CreateProductDTO
{
    public string $name;
    public ?string $description;
    public float $price;
    public ?string $categoryId;
    
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? '';
        $dto->description = $data['description'] ?? null;
        $dto->price = (float) ($data['price'] ?? 0);
        $dto->categoryId = $data['category_id'] ?? null;
        
        return $dto;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->name)) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($this->name) > 255) {
            $errors['name'] = 'Name must be 255 characters or less';
        }
        
        if ($this->price < 0) {
            $errors['price'] = 'Price must be positive';
        }
        
        if ($this->categoryId && !$this->isValidUlid($this->categoryId)) {
            $errors['category_id'] = 'Invalid category ID format';
        }
        
        return $errors;
    }
    
    private function isValidUlid(string $value): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', strtoupper($value));
    }
}
```

---

## 3. Database Development

### 3.1 Create Table DDL

```sql
-- database/mysql/tables/products.sql

CREATE TABLE IF NOT EXISTS products (
    id CHAR(26) PRIMARY KEY,                    -- ULID
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category_id CHAR(26),
    status ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'draft',
    
    -- Audit fields (MANDATORY)
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by CHAR(26),
    updated_by CHAR(26),
    
    -- Soft delete fields (MANDATORY)
    deleted_at TIMESTAMP NULL,
    deleted_by CHAR(26),
    
    -- Optimistic locking (RECOMMENDED)
    version INT NOT NULL DEFAULT 1,
    
    -- Indexes
    INDEX idx_products_status (status),
    INDEX idx_products_category (category_id),
    INDEX idx_products_created_at (created_at),
    INDEX idx_products_deleted_at (deleted_at),
    
    -- Foreign keys
    CONSTRAINT fk_products_category 
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Create Stored Procedures

```sql
-- database/mysql/stored_procedures/products/sp_product_create.sql

DELIMITER //

CREATE PROCEDURE sp_product_create(
    IN p_id CHAR(26),
    IN p_name VARCHAR(255),
    IN p_description TEXT,
    IN p_price DECIMAL(10, 2),
    IN p_category_id CHAR(26),
    IN p_status VARCHAR(20)
)
BEGIN
    INSERT INTO products (
        id, name, description, price, category_id, status, 
        created_at, version
    ) VALUES (
        p_id, p_name, p_description, p_price, p_category_id, p_status,
        UTC_TIMESTAMP(), 1
    );
END //

DELIMITER ;
```

```sql
-- database/mysql/stored_procedures/products/sp_product_get_by_id.sql

DELIMITER //

CREATE PROCEDURE sp_product_get_by_id(
    IN p_id CHAR(26)
)
BEGIN
    SELECT 
        id, name, description, price, category_id, status,
        created_at, updated_at, version
    FROM products
    WHERE id = p_id AND deleted_at IS NULL;
END //

DELIMITER ;
```

```sql
-- database/mysql/stored_procedures/products/sp_product_list.sql

DELIMITER //

CREATE PROCEDURE sp_product_list(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT 
        id, name, description, price, category_id, status,
        created_at, updated_at
    FROM products
    WHERE deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //

DELIMITER ;
```

```sql
-- database/mysql/stored_procedures/products/sp_product_soft_delete.sql

DELIMITER //

CREATE PROCEDURE sp_product_soft_delete(
    IN p_id CHAR(26),
    IN p_deleted_by CHAR(26)
)
BEGIN
    UPDATE products
    SET 
        deleted_at = UTC_TIMESTAMP(),
        deleted_by = p_deleted_by,
        version = version + 1
    WHERE id = p_id AND deleted_at IS NULL;
END //

DELIMITER ;
```

### 3.3 Create Migration

```php
<?php
// database/mysql/migrations/2026_01_18_000001_create_products_table.php

namespace Database\Migrations;

class CreateProductsTable
{
    public function up(): string
    {
        return file_get_contents(__DIR__ . '/../tables/products.sql');
    }
    
    public function down(): string
    {
        return 'DROP TABLE IF EXISTS products;';
    }
    
    public function procedures(): array
    {
        return glob(__DIR__ . '/../stored_procedures/products/*.sql');
    }
}
```

### 3.4 Run Migrations

```bash
# Run all pending migrations
php artisan migrate

# Check migration status
php artisan migrate --status

# Rollback last migration
php artisan migrate --rollback

# Fresh install (drop all, recreate)
php artisan migrate --fresh
```

---

## 4. API Development Guidelines

### 4.1 URL Naming Conventions

```
✅ CORRECT:
GET    /api/v1/products              # List products
GET    /api/v1/products/{id}         # Get single product
POST   /api/v1/products              # Create product
PUT    /api/v1/products/{id}         # Update product (full)
PATCH  /api/v1/products/{id}         # Update product (partial)
DELETE /api/v1/products/{id}         # Delete product

GET    /api/v1/products/{id}/reviews          # Nested resource
POST   /api/v1/products/{id}/reviews          # Create review for product
GET    /api/v1/products/search?q=phone        # Search
GET    /api/v1/products/categories/{cat}/items # Filtered list

❌ INCORRECT:
GET    /api/v1/getProducts           # Verb in URL
POST   /api/v1/createProduct         # Verb in URL
GET    /api/v1/product               # Singular (should be plural)
```

### 4.2 HTTP Status Codes

```php
// Success
Response::success($data);                    // 200 OK
Response::success($data, 'Created', 201);    // 201 Created
Response::success(null, 'Deleted', 204);     // 204 No Content

// Client Errors
Response::badRequest('Validation failed');   // 400 Bad Request
Response::unauthorized('Invalid token');     // 401 Unauthorized
Response::forbidden('Access denied');        // 403 Forbidden
Response::notFound('Product not found');     // 404 Not Found
Response::conflict('Version mismatch');      // 409 Conflict
Response::tooManyRequests('Rate limited');   // 429 Too Many Requests

// Server Errors
Response::error('Internal error', 500);      // 500 Internal Server Error
Response::error('Service unavailable', 503); // 503 Service Unavailable
```

### 4.3 Response Format

All responses follow this envelope:

```json
{
    "success": true,
    "message": "Product retrieved successfully",
    "data": {
        "id": "01HQXYZ1234567890ABCDEF",
        "name": "iPhone 15",
        "price": 999.99
    },
    "meta": {
        "timestamp": "2026-01-18T10:30:00Z",
        "api_version": "v1"
    },
    "trace": {
        "correlation_id": "01HQXYZ...",
        "transaction_id": "01HQXYZ...",
        "request_id": "01HQXYZ..."
    }
}
```

---

## 5. Authentication & Authorization

### 5.1 Protect Routes

```php
// Require authentication
#[Route('/products', methods: ['POST'])]
#[Middleware(['auth'])]  // Requires valid JWT
public function store(): void { }

// Require specific permission
#[Route('/products/{id}', methods: ['DELETE'])]
#[Middleware(['auth', 'permission:products:delete'])]
public function destroy(string $id): void { }

// Require specific role
#[Route('/admin/users', methods: ['GET'])]
#[Middleware(['auth', 'role:admin'])]
public function listUsers(): void { }

// Check ownership
#[Route('/my/orders/{id}', methods: ['GET'])]
#[Middleware(['auth', 'owns:Order,id'])]
public function myOrder(string $id): void { }
```

### 5.2 Check Permissions in Code

```php
use PHPFrarm\Core\Authorization\AuthorizationManager;

public function update(string $id): void
{
    $user = $this->getCurrentUser();
    $authz = new AuthorizationManager($user);
    
    // Check permission
    if (!$authz->can('products:update')) {
        Response::forbidden('You cannot update products');
    }
    
    // Check resource ownership
    $product = $this->productService->getById($id);
    if (!$authz->canAccess($product, 'update')) {
        Response::forbidden('You cannot update this product');
    }
    
    // Proceed with update...
}
```

### 5.3 Define Custom Policy

```php
<?php
// modules/Product/Policies/ProductPolicy.php

namespace Modules\Product\Policies;

use PHPFrarm\Core\Authorization\Policy;

class ProductPolicy extends Policy
{
    public function view(array $user, array $product): bool
    {
        // Everyone can view active products
        if ($product['status'] === 'active') {
            return true;
        }
        
        // Only owner can view draft products
        return $product['created_by'] === $user['id'];
    }
    
    public function update(array $user, array $product): bool
    {
        // Admin can update any product
        if ($this->hasRole($user, 'admin')) {
            return true;
        }
        
        // Owner can update their own product
        return $product['created_by'] === $user['id'];
    }
    
    public function delete(array $user, array $product): bool
    {
        // Only admin can delete products
        return $this->hasRole($user, 'admin');
    }
}
```

---

## 6. Input Validation

### 6.1 Using ValidateInput Attribute

```php
use PHPFrarm\Core\Attributes\ValidateInput;

#[Route('/products', methods: ['POST'])]
#[ValidateInput(
    body: [
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'category_id' => 'nullable|ulid',
        'tags' => 'nullable|array',
        'tags.*' => 'string|max:50'
    ]
)]
public function store(): void
{
    // If validation fails, 400 response is auto-sent
    // If passes, proceed...
}
```

### 6.2 Using InputValidator

```php
use PHPFrarm\Core\Validation\InputValidator;

public function store(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new InputValidator();
    
    $errors = $validator->validateBody($data, [
        'name' => ['required', 'string', 'max:255'],
        'price' => ['required', 'numeric', 'min:0'],
        'email' => ['required', 'email'],
        'phone' => ['nullable', 'phone'],
        'category_id' => ['nullable', 'ulid'],
    ]);
    
    if (!empty($errors)) {
        Response::badRequest('Validation failed', $errors);
    }
    
    // Proceed...
}
```

### 6.3 Available Validation Rules

```
# Type Rules
string, integer, numeric, boolean, array, object

# String Rules
min:N, max:N, length:N, between:min,max
alpha, alpha_num, alpha_dash
email, url, ip, phone, json

# ID Rules
uuid, ulid

# Comparison Rules
min:N, max:N, gt:N, gte:N, lt:N, lte:N
in:val1,val2,val3
not_in:val1,val2

# Date Rules
date, date_format:Y-m-d, before:date, after:date

# Other Rules
required, nullable, confirmed
regex:/pattern/
```

---

## 7. Caching Strategy

### 7.1 Route-Level Caching

```php
use PHPFrarm\Core\Cache\Attributes\Cacheable;

// Cache for 5 minutes
#[Cacheable(ttl: 300)]
public function index(): void { }

// Cache with tags for invalidation
#[Cacheable(ttl: 600, tags: ['products'])]
public function index(): void { }

// Cache with dynamic tags
#[Cacheable(ttl: 600, tags: ['products', 'product:{id}'])]
public function show(string $id): void { }

// Conditional caching
#[Cacheable(
    ttl: 300,
    when: 'request.query.cache !== "false"',
    unless: 'response.status >= 400'
)]
public function index(): void { }
```

### 7.2 Manual Cache Control

```php
use PHPFrarm\Core\Cache\CacheManager;

$cache = CacheManager::getInstance();

// Set cache
$cache->set('product:123', $data, 600, ['products', 'product:123']);

// Get cache
$data = $cache->get('product:123');

// Delete cache
$cache->delete('product:123');

// Invalidate by tag
$cache->invalidateTag('products');  // Clears all product caches

// Cache with callback (get or set)
$data = $cache->remember('product:123', 600, function() {
    return $this->dao->getById('123');
});
```

---

## 8. Testing Requirements

### 8.1 Create Unit Tests

```php
<?php
// modules/Product/Tests/ProductServiceTest.php

namespace Modules\Product\Tests;

use PHPUnit\Framework\TestCase;
use Modules\Product\Services\ProductService;
use Modules\Product\DTOs\CreateProductDTO;

class ProductServiceTest extends TestCase
{
    private ProductService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductService();
    }
    
    public function test_create_product_with_valid_data(): void
    {
        $dto = CreateProductDTO::fromArray([
            'name' => 'Test Product',
            'price' => 99.99,
        ]);
        
        $product = $this->service->create($dto);
        
        $this->assertNotNull($product['id']);
        $this->assertEquals('Test Product', $product['name']);
        $this->assertEquals(99.99, $product['price']);
    }
    
    public function test_create_product_fails_with_negative_price(): void
    {
        $dto = CreateProductDTO::fromArray([
            'name' => 'Test Product',
            'price' => -10,
        ]);
        
        $errors = $dto->validate();
        
        $this->assertArrayHasKey('price', $errors);
    }
}
```

### 8.2 Create API Tests

```php
<?php
// modules/Product/Tests/ProductApiTest.php

namespace Modules\Product\Tests;

use Tests\ApiTestCase;

class ProductApiTest extends ApiTestCase
{
    public function test_list_products_returns_paginated_results(): void
    {
        $response = $this->get('/api/v1/products?page=1&per_page=10');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['*' => ['id', 'name', 'price']],
            'meta' => ['pagination' => ['total', 'page', 'per_page']]
        ]);
    }
    
    public function test_create_product_requires_authentication(): void
    {
        $response = $this->post('/api/v1/products', [
            'name' => 'Test Product',
            'price' => 99.99
        ]);
        
        $response->assertStatus(401);
    }
    
    public function test_create_product_with_valid_data(): void
    {
        $this->actingAs($this->createUser(['role' => 'admin']));
        
        $response = $this->post('/api/v1/products', [
            'name' => 'Test Product',
            'price' => 99.99
        ]);
        
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }
}
```

### 8.3 Run Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit modules/Product/Tests/ProductServiceTest.php

# Run specific test method
./vendor/bin/phpunit --filter test_create_product_with_valid_data

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Run specific suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite API
./vendor/bin/phpunit --testsuite Security
```

---

## 9. Documentation Requirements

### 9.1 Add API Documentation Attributes

```php
use PHPFrarm\Core\Documentation\Attributes\{ApiDoc, ApiParam, ApiResponse, ApiExample};

#[Route('/products', methods: ['POST'])]
#[ApiDoc(
    summary: 'Create a new product',
    description: 'Creates a new product in the catalog. Requires admin or seller role.',
    tags: ['Products'],
    security: [['bearerAuth' => []]]
)]
#[ApiParam(name: 'name', in: 'body', type: 'string', required: true, description: 'Product name')]
#[ApiParam(name: 'price', in: 'body', type: 'number', required: true, description: 'Product price')]
#[ApiParam(name: 'category_id', in: 'body', type: 'string', format: 'ulid', description: 'Category ID')]
#[ApiResponse(status: 201, description: 'Product created successfully', schema: ProductDTO::class)]
#[ApiResponse(status: 400, description: 'Validation error')]
#[ApiResponse(status: 401, description: 'Unauthorized')]
#[ApiExample(
    request: ['name' => 'iPhone 15', 'price' => 999.99],
    response: ['success' => true, 'data' => ['id' => '01HQXYZ...', 'name' => 'iPhone 15']]
)]
public function store(): void { }
```

### 9.2 Generate Documentation

```bash
# Generate all documentation
php artisan docs:generate

# Outputs:
# - public/docs/openapi.json (OpenAPI 3.0 spec)
# - public/docs/errors.md (Error catalog)
# - public/docs/postman.json (Postman collection)
```

### 9.3 Access Documentation

```
Swagger UI:       http://localhost/docs
OpenAPI JSON:     http://localhost/docs/openapi.json
Error Catalog:    http://localhost/docs/errors
Postman Export:   http://localhost/docs/postman
```

---

## 10. Deployment Checklist

### 10.1 Pre-Deployment Checklist

```
□ Code Review
  □ All code follows PSR-12 standards
  □ No hardcoded credentials or secrets
  □ All public methods have DocBlocks
  □ No TODO/FIXME left in production code

□ Security
  □ All routes have proper authentication
  □ All inputs are validated
  □ No SQL injection possible (stored procedures only)
  □ Rate limiting configured
  □ CORS properly configured

□ Testing
  □ All unit tests pass
  □ All API tests pass
  □ Security tests pass
  □ Test coverage ≥ 80%

□ Database
  □ All migrations created
  □ All stored procedures created
  □ Indexes defined for queries
  □ Rollback script tested

□ Documentation
  □ API documentation updated
  □ CHANGELOG.md updated
  □ README updated if needed

□ Configuration
  □ Environment variables documented
  □ No secrets in code or config files
  □ Production config reviewed
```

### 10.2 Deploy Steps

```bash
# 1. Create feature branch
git checkout -b feature/product-module

# 2. Develop and test locally
php artisan migrate
./vendor/bin/phpunit

# 3. Commit changes
git add .
git commit -m "feat(products): add product module with CRUD operations"

# 4. Push and create PR
git push origin feature/product-module

# 5. After PR approval and merge, deploy
# CI/CD pipeline will:
#   - Run all tests
#   - Build Docker image
#   - Deploy to staging
#   - Run smoke tests
#   - Deploy to production (if staging passes)
```

### 10.3 Post-Deployment

```
□ Verify health check: GET /health
□ Verify readiness: GET /health/ready
□ Test new endpoints manually
□ Check logs for errors: MongoDB logs
□ Monitor metrics: Prometheus/Grafana
□ Verify cache is working: Check Redis
□ Update status page if needed
```

---

## 📚 Quick Reference

### File Locations

| Type | Location |
|------|----------|
| Controllers | `modules/{Module}/Controllers/` |
| Services | `modules/{Module}/Services/` |
| DAOs | `modules/{Module}/DAO/` |
| DTOs | `modules/{Module}/DTOs/` |
| Tests | `modules/{Module}/Tests/` |
| Tables | `database/mysql/tables/` |
| Procedures | `database/mysql/stored_procedures/{module}/` |
| Migrations | `database/mysql/migrations/` |
| Config | `config/` |

### CLI Commands

```bash
php artisan make:module {Name}     # Create module
php artisan migrate                # Run migrations
php artisan migrate --rollback     # Rollback
php artisan migrate --status       # Show status
php artisan docs:generate          # Generate docs
php artisan list                   # List commands
```

### Useful Classes

```php
use PHPFrarm\Core\Response;           // API responses
use PHPFrarm\Core\Database;           // Database (stored proc)
use PHPFrarm\Core\Logger;             // MongoDB logging
use PHPFrarm\Core\TraceContext;       // Trace IDs
use PHPFrarm\Core\Utils\IdGenerator;  // ULID/UUID
use PHPFrarm\Core\Cache\CacheManager; // Redis cache
```

---

## 🆘 Getting Help

1. **Framework Guides:**
   - `ARCHITECTURE.md` - System overview
   - `MVC_GUIDE.md` - MVC patterns
   - `MODULES_GUIDE.md` - Module system
   - `TESTING_GUIDE.md` - Testing patterns
   - `QUICK_REFERENCE.md` - Cheat sheet

2. **API Documentation:**
   - Visit `/docs` for Swagger UI
   - Check `DOCUMENTATION_GUIDE.md`

3. **Troubleshooting:**
   - Check MongoDB logs
   - Check `/health/ready` endpoint
   - Review `CIRCUIT_BREAKER_GUIDE.md` for failures

---

*This guide was created for the PHPFrarm Framework v1.0.0*
