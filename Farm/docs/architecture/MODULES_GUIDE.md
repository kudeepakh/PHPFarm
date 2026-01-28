# 🔄 Module Auto-Loading System

## Overview

The PHPFrarm framework now features an **auto-loading module system** with **configurable middleware**. You no longer need to modify `index.php` when adding new modules!

## Architecture

### Core Components

1. **Router** (`app/Core/Router.php`) - Route registration and middleware execution
2. **ModuleLoader** (`app/Core/ModuleLoader.php`) - Auto-discovers and loads modules
3. **CommonMiddleware** (`app/Middleware/CommonMiddleware.php`) - Built-in middleware collection

## Creating a New Module

### 1. Module Structure

```
/backend/modules/YourModule/
├── module.php          # Module configuration (required)
├── routes.php          # Route definitions (optional)
├── Services/           # Business logic (optional)
└── Controllers/        # Controllers (optional)
```

### 2. Module Configuration (`module.php`)

Every module **must** have a `module.php` file:

```php
<?php

return [
    'name' => 'YourModule',
    'version' => '1.0.0',
    'description' => 'Module description',
    'enabled' => true,  // Set to false to disable module
    'dependencies' => [], // Other modules required

    // Bootstrap function (optional) - runs when module loads
    'bootstrap' => function() {
        // Initialize resources, register listeners, etc.
        \PHPFrarm\Core\Logger::info('YourModule initialized');
    },

    // Module-specific config (optional)
    'config' => [
        'setting1' => 'value1',
        'setting2' => 'value2',
    ],
];
```

### 3. Route Definitions (`routes.php`)

Define your routes with middleware:

```php
<?php

use PHPFrarm\Core\Router;
use PHPFrarm\Core\Response;

// Simple route - no middleware
Router::get('/api/yourmodule/hello', function($request) {
    Response::success(['message' => 'Hello World!']);
});

// Route with middleware
Router::post('/api/yourmodule/protected', function($request) {
    $user = $request['user']; // Added by 'auth' middleware
    Response::success(['user_id' => $user['user_id']]);
}, ['auth', 'rateLimit']);

// Route with path parameters
Router::get('/api/yourmodule/items/{itemId}', function($request, $itemId) {
    Response::success(['item_id' => $itemId]);
});

// Route group with shared middleware
Router::group('/api/yourmodule/admin', ['auth', 'adminOnly'], function() {
    
    Router::get('/dashboard', function($request) {
        Response::success(['dashboard' => 'admin data']);
    });
    
    Router::post('/settings', function($request) {
        Response::success(['updated' => true]);
    }, ['jsonParser']); // Additional middleware for this route only
    
});
```

## Built-in Middleware

The framework provides these middleware out of the box:

### 1. `auth` - Authentication
Verifies JWT token from `Authorization: Bearer <token>` header. Adds `user` object to request.

```php
Router::get('/api/profile', function($request) {
    $userId = $request['user']['user_id'];
    // ...
}, ['auth']);
```

### 2. `rateLimit` - Rate Limiting
Limits requests per IP address based on `.env` configuration.

### 3. `cors` - CORS Headers
Sets appropriate CORS headers for cross-origin requests.

### 4. `jsonParser` - JSON Body Parser
Validates and parses JSON request bodies.

### 5. `adminOnly` - Admin Access
Requires user to have `role: admin` (must be used with `auth`).

```php
Router::delete('/api/users/{id}', function($request, $id) {
    // Only admins can access
}, ['auth', 'adminOnly']);
```

### 6. `logRequest` - Request Logging
Logs request details and execution time.

## Middleware Usage Patterns

### Single Middleware
```php
Router::get('/api/endpoint', $handler, ['auth']);
```

### Multiple Middleware
```php
Router::post('/api/endpoint', $handler, ['auth', 'rateLimit', 'jsonParser']);
```

### Route Groups with Shared Middleware
```php
Router::group('/api/protected', ['auth', 'rateLimit'], function() {
    Router::get('/resource1', $handler1);
    Router::get('/resource2', $handler2); // Both use auth + rateLimit
});
```

### Nested Groups
```php
Router::group('/api/admin', ['auth'], function() {
    
    Router::group('/users', ['adminOnly'], function() {
        // These routes use: auth + adminOnly
        Router::get('/list', $handler);
        Router::delete('/{id}', $handler);
    });
    
});
```

## Creating Custom Middleware

### 1. Add to CommonMiddleware class

```php
// In app/Middleware/CommonMiddleware.php

public static function yourCustomMiddleware(array $request, callable $next): mixed
{
    // Before logic
    if (!someCondition()) {
        Response::forbidden('Access denied');
        return null;
    }
    
    // Call next middleware/handler
    $response = $next($request);
    
    // After logic (if needed)
    
    return $response;
}
```

### 2. Register middleware

```php
// In public/index.php
Router::middleware('yourCustomMiddleware', [CommonMiddleware::class, 'yourCustomMiddleware']);
```

### 3. Use in routes

```php
Router::get('/api/endpoint', $handler, ['yourCustomMiddleware']);
```

## Request Object Structure

Middleware and route handlers receive a `$request` array:

```php
[
    'method' => 'GET',           // HTTP method
    'path' => '/api/users/123',  // Request path
    'params' => ['id' => '123'], // Path parameters
    'query' => ['page' => 1],    // Query string parameters
    'body' => [...],             // Parsed request body
    'headers' => [...],          // Request headers
    'user' => [...],             // Added by 'auth' middleware
]
```

## Module Examples

### Example 1: Simple API Module

```php
// modules/Blog/module.php
return [
    'name' => 'Blog',
    'version' => '1.0.0',
    'enabled' => true,
];

// modules/Blog/routes.php
use PHPFrarm\Core\Router;
use PHPFrarm\Core\Response;

Router::get('/api/blog/posts', function($request) {
    Response::success(['posts' => []]);
}, ['rateLimit']);

Router::post('/api/blog/posts', function($request) {
    $data = $request['body'];
    Response::success(['created' => true], 'Post created', 201);
}, ['auth', 'jsonParser']);
```

### Example 2: Admin Module

```php
// modules/Admin/module.php
return [
    'name' => 'Admin',
    'version' => '1.0.0',
    'enabled' => true,
];

// modules/Admin/routes.php
use PHPFrarm\Core\Router;
use PHPFrarm\Core\Response;

// All admin routes require auth + adminOnly
Router::group('/api/admin', ['auth', 'adminOnly', 'rateLimit'], function() {
    
    Router::get('/stats', function($request) {
        Response::success(['total_users' => 1000]);
    });
    
    Router::get('/logs', function($request) {
        Response::success(['logs' => []]);
    });
    
});
```

## Module Discovery Process

1. Framework scans `/backend/modules/` directory
2. For each subdirectory, looks for `module.php`
3. Loads module configuration
4. Executes `bootstrap` function if defined
5. Loads `routes.php` if exists
6. Module is ready to handle requests

## Disabling Modules

Set `enabled: false` in `module.php`:

```php
return [
    'name' => 'MyModule',
    'enabled' => false, // Module won't load
];
```

## Debugging

### List loaded modules

```http
GET /api/status
```

Response:
```json
{
  "success": true,
  "data": {
    "loaded_modules": {
      "User": { "path": "...", "enabled": true },
      "Auth": { "path": "...", "enabled": true }
    }
  }
}
```

### Check logs

All module loading is logged to MongoDB:
```javascript
db.application_logs.find({message: /module/i})
```

## Migration Guide

### Old Way (Manual Routing)
```php
// Had to modify index.php for each module
if (str_starts_with($path, '/auth/')) {
    require_once __DIR__ . '/../modules/Auth/routes.php';
}
```

### New Way (Auto-Loading)
1. Create `/modules/YourModule/module.php`
2. Create `/modules/YourModule/routes.php`
3. **Done!** Module auto-loads on next request

## Best Practices

1. **Module Independence**: Each module should be self-contained
2. **Middleware Order**: Most restrictive middleware first (`auth` before `adminOnly`)
3. **Error Handling**: Always use try-catch with Database calls
4. **Logging**: Use Logger for audit trails and debugging
5. **Validation**: Validate input before processing
6. **Documentation**: Document your routes and middleware requirements

## Summary

✅ **No need to modify `index.php`** when adding modules
✅ **Configurable middleware** per route or route group
✅ **Built-in middleware** for common tasks (auth, rate limiting, CORS)
✅ **Module metadata** and bootstrap support
✅ **Path parameters** support (`/api/users/{id}`)
✅ **Automatic logging** and trace ID injection
✅ **Clean separation** of concerns

The framework is now truly modular and production-ready!
