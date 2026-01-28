# 🎯 Annotation-Based Routing System

## 🚀 Overview

The framework now supports **automatic route discovery** via PHP 8 attributes. No need to manually register controllers or routes in `routes.php`!

### Benefits:
- ✅ **Zero Configuration** - Routes defined directly in controllers
- ✅ **Auto-Discovery** - Framework scans and registers routes automatically
- ✅ **Redis Caching** - Route definitions cached for performance
- ✅ **Type Safe** - PHP attributes provide IDE autocomplete
- ✅ **Self-Documenting** - Routes documented where they're implemented

---

## 📝 Route Attributes

### 1️⃣ `#[RouteGroup]` - Class-Level Attribute

Apply common prefix and middleware to all routes in a controller.

```php
use PHPFrarm\Core\Attributes\RouteGroup;

#[RouteGroup('/api/products', middleware: ['auth', 'rateLimit'])]
class ProductController
{
    // All methods in this controller will have:
    // - Path prefix: /api/products
    // - Middleware: ['auth', 'rateLimit']
}
```

**Parameters:**
- `prefix` (string) - URL prefix for all routes
- `middleware` (array) - Middleware applied to all routes

---

### 2️⃣ `#[Route]` - Method-Level Attribute

Define individual route for a controller method.

```php
use PHPFrarm\Core\Attributes\Route;

#[Route('/create', method: 'POST', middleware: ['jsonParser'], description: 'Create new product')]
public function create(array $request): void
{
    // Implementation
}
```

**Parameters:**
- `path` (string, required) - Route path (combined with RouteGroup prefix)
- `method` (string) - HTTP method: GET, POST, PUT, DELETE (default: GET)
- `middleware` (array) - Additional middleware for this route
- `description` (string) - Route description for documentation

---

## 🏗️ Complete Controller Example

```php
<?php

namespace PHPFrarm\Modules\Product\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\Product\Services\ProductService;
use PHPFrarm\Modules\Product\DTO\CreateProductDTO;

/**
 * Product Controller
 * 
 * All routes automatically discovered - no manual registration needed!
 */
#[RouteGroup('/api/products', middleware: ['auth', 'rateLimit'])]
class ProductController
{
    private ProductService $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    /**
     * Create a new product
     * 
     * @route POST /api/products
     * @middleware auth, rateLimit, jsonParser (combined from class + method)
     */
    #[Route('/', method: 'POST', middleware: ['jsonParser'], description: 'Create product')]
    public function create(array $request): void
    {
        $dto = new CreateProductDTO($request['body']);
        
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('Validation failed', $errors);
            return;
        }

        try {
            $result = $this->productService->create($dto->name, $dto->price);
            Response::success($result, 'Product created', 201);
        } catch (\Exception $e) {
            Response::serverError('Failed to create product');
        }
    }

    /**
     * Get product by ID
     * 
     * @route GET /api/products/{id}
     * @middleware auth, rateLimit (from class)
     */
    #[Route('/{id}', method: 'GET', description: 'Get product by ID')]
    public function getById(array $request, string $id): void
    {
        try {
            $product = $this->productService->getById($id);
            Response::success($product);
        } catch (\Exception $e) {
            Response::notFound('Product not found');
        }
    }

    /**
     * List all products
     * 
     * @route GET /api/products
     * @middleware auth, rateLimit (from class)
     */
    #[Route('/', method: 'GET', description: 'List all products')]
    public function list(array $request): void
    {
        $page = (int)($request['query']['page'] ?? 1);
        $products = $this->productService->list($page);
        Response::success($products);
    }

    /**
     * Update product
     * 
     * @route PUT /api/products/{id}
     * @middleware auth, rateLimit, jsonParser (combined)
     */
    #[Route('/{id}', method: 'PUT', middleware: ['jsonParser'], description: 'Update product')]
    public function update(array $request, string $id): void
    {
        // Implementation
    }

    /**
     * Delete product (admin only)
     * 
     * @route DELETE /api/products/{id}
     * @middleware auth, rateLimit, adminOnly (combined)
     */
    #[Route('/{id}', method: 'DELETE', middleware: ['adminOnly'], description: 'Delete product')]
    public function delete(array $request, string $id): void
    {
        // Implementation
    }
}
```

---

## 📦 Module Registration

### Update `module.php`

Register your controllers in the module's bootstrap function:

```php
<?php

use PHPFrarm\Core\ControllerRegistry;

return [
    'name' => 'Product',
    'enabled' => true,
    'description' => 'Product management module',
    'version' => '1.0.0',
    'bootstrap' => function() {
        // Option 1: Register controllers individually
        ControllerRegistry::register(\PHPFrarm\Modules\Product\Controllers\ProductController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\Product\Controllers\CategoryController::class);
        
        // Option 2: Auto-discover all controllers in directory
        ControllerRegistry::autoDiscoverControllers(
            __DIR__ . '/Controllers',
            'PHPFrarm\\Modules\\Product\\Controllers'
        );
    }
];
```

---

## 🗂️ Routes.php (Now Optional!)

The `routes.php` file is **no longer required** for basic routing:

```php
<?php

/**
 * Product Module Routes
 * 
 * ⚠️ THIS FILE IS NOW OPTIONAL! ⚠️
 * 
 * Routes are automatically discovered from controller attributes.
 * See: modules/Product/Controllers/ProductController.php
 * 
 * This file can be deleted or used for:
 * - Custom route logic that doesn't fit in controllers
 * - Third-party route registrations
 * - Legacy route compatibility
 */

// Optional: Add custom routes here if needed
use PHPFrarm\Core\Router;
use PHPFrarm\Core\Response;

Router::get('/api/products/special-endpoint', function($request) {
    Response::success(['message' => 'Custom route']);
});
```

---

## ⚙️ How It Works

### 1. Module Bootstrap (module.php)
```php
ControllerRegistry::register(ProductController::class);
```

### 2. Framework Initialization (index.php)
```php
// Load modules
$modules = ModuleLoader::loadAll(__DIR__ . '/../modules');

// Discover routes from registered controllers
$routes = ControllerRegistry::discoverRoutes();

// Register routes with Router
ControllerRegistry::registerWithRouter($routes);

// Dispatch request
Router::dispatch();
```

### 3. Route Discovery Process
1. Framework scans all registered controllers
2. Reads `#[RouteGroup]` class attributes
3. Reads `#[Route]` method attributes
4. Combines class prefix + method path
5. Merges class middleware + method middleware
6. Caches route definitions in Redis
7. Registers routes with Router

### 4. Request Handling
```
HTTP Request → Router Match → Middleware Chain → Controller Method → Response
```

---

## 🎨 Middleware Inheritance

Middleware is **merged** from class and method levels:

```php
#[RouteGroup('/api/products', middleware: ['auth', 'rateLimit'])]
class ProductController
{
    // This method will have: ['auth', 'rateLimit', 'jsonParser']
    #[Route('/create', method: 'POST', middleware: ['jsonParser'])]
    public function create(array $request): void { }
    
    // This method will have: ['auth', 'rateLimit', 'adminOnly']
    #[Route('/delete', method: 'DELETE', middleware: ['adminOnly'])]
    public function delete(array $request): void { }
    
    // This method will have: ['auth', 'rateLimit']
    #[Route('/list', method: 'GET')]
    public function list(array $request): void { }
}
```

---

## 🚀 Path Parameters

Routes support dynamic path parameters:

```php
// Route definition
#[Route('/users/{userId}/posts/{postId}', method: 'GET')]
public function getPost(array $request, string $userId, string $postId): void
{
    // $userId and $postId are automatically extracted
    Response::success([
        'user_id' => $userId,
        'post_id' => $postId
    ]);
}
```

**Request:**
```
GET /api/users/123/posts/456
```

**Method Receives:**
```php
$userId = '123'
$postId = '456'
```

---

## 🔄 Redis Caching with Auto-Invalidation

Routes are cached in Redis for performance with **automatic invalidation** when controller files change:

### Cache Configuration (.env)
```env
REDIS_HOST=redis
REDIS_PORT=6379
ROUTE_CACHE_TTL=3600  # 1 hour cache
APP_ENV=production    # Enable caching in production
```

### Cache Behavior
- **Development** (`APP_ENV=development`): Cache disabled, routes discovered on every request
- **Production** (`APP_ENV=production`): Routes cached in Redis with automatic invalidation

### Automatic Cache Invalidation
The framework **automatically detects** when controller files change:
- Tracks file modification time (`filemtime`) for each controller
- Stores modification times with cached routes
- Compares on each request - if any file changed, cache is invalidated
- **No manual cache clearing needed!**

### How It Works
```php
// 1. Cache routes with file modification times
$cacheData = [
    'routes' => [...],
    'file_mod_times' => [
        'AuthController' => 1705584320,
        'UserController' => 1705584321
    ]
];

// 2. On next request, check if files changed
foreach ($file_mod_times as $class => $cachedTime) {
    $currentTime = filemtime($class->file);
    if ($currentTime !== $cachedTime) {
        // Controller changed - invalidate cache!
    }
}
```

### Manual Cache Clear (Optional)
```bash
# Via PHP
ControllerRegistry::clearCache();

# Via Redis CLI
docker-compose exec redis redis-cli
> DEL framework:routes:registry
```

### Cache Logs
Monitor cache behavior in logs:
```
✅ Routes loaded from Redis cache
⚠️ Controller file modified, invalidating cache: AuthController.php
🔄 Discovering routes from controllers: reason=controller_files_changed
✅ Routes cached in Redis with file tracking
```

---

## 🛠️ Development Workflow

### 1. Create Controller with Attributes
```php
#[RouteGroup('/api/orders', middleware: ['auth'])]
class OrderController
{
    #[Route('/', method: 'POST', middleware: ['jsonParser'])]
    public function create(array $request): void { }
}
```

### 2. Register in module.php
```php
ControllerRegistry::register(\PHPFrarm\Modules\Order\Controllers\OrderController::class);
```

### 3. Restart Server (if in production)
```bash
docker-compose restart backend
```

### 4. Test Endpoint
```bash
curl -X POST http://localhost/api/orders \
  -H "Authorization: Bearer token" \
  -H "Content-Type: application/json" \
  -d '{"product_id": "123"}'
```

---

## 📊 Route Documentation

### List All Routes (Debug Endpoint)

Create a debug controller to list all registered routes:

```php
#[RouteGroup('/api/debug')]
class DebugController
{
    #[Route('/routes', method: 'GET', description: 'List all routes')]
    public function listRoutes(array $request): void
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            Response::forbidden('Not available in production');
            return;
        }
        
        $routes = ControllerRegistry::discoverRoutes();
        Response::success(['routes' => $routes]);
    }
}
```

---

## ✅ Benefits Summary

| Feature | Before | After |
|---------|--------|-------|
| Route Definition | Separate `routes.php` | Attributes in controller |
| Controller Registration | Manual instantiation | Auto-instantiated |
| Middleware Config | Spread across files | In controller attributes |
| Documentation | Separate docs | Self-documenting |
| Performance | N/A | Redis caching |
| Type Safety | None | PHP 8 attributes |
| IDE Support | Limited | Full autocomplete |

---

## 🎯 Best Practices

✅ **DO:**
- Use `#[RouteGroup]` for common prefix/middleware
- Document routes with PHPDoc comments
- Keep route logic minimal (delegate to services)
- Use descriptive route descriptions
- Clear cache after route changes in production

❌ **DON'T:**
- Mix attribute routes with manual routes (choose one pattern)
- Put business logic in controller route methods
- Forget to register controllers in `module.php`
- Hardcode middleware in multiple places

---

## 🔍 Troubleshooting

### Routes Not Working?
1. Check controller is registered in `module.php`
2. Verify PHP 8+ is installed (attributes require PHP 8)
3. Clear route cache: `ControllerRegistry::clearCache()`
4. Restart backend: `docker-compose restart backend`
5. Check logs: `docker-compose logs backend`

### Cache Issues?
1. Set `APP_ENV=development` to disable caching
2. Manually clear Redis: `DEL framework:routes:registry`
3. Check Redis connection in logs

### Middleware Not Applied?
1. Verify middleware name matches registered middleware
2. Check both class and method middleware arrays
3. Use `Router::getRoutes()` to debug registered middleware

---

## 📚 Additional Resources

- [MVC_GUIDE.md](./MVC_GUIDE.md) - MVC architecture patterns
- [MODULES_GUIDE.md](./MODULES_GUIDE.md) - Module development guide
- [QUICK_REFERENCE.md](./QUICK_REFERENCE.md) - Quick reference card

---

🎉 **You're ready to build annotation-based APIs!**
