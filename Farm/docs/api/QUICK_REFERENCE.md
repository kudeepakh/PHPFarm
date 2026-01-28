# 🚀 PHPFrarm Quick Reference

## Module Creation (3 Steps)

### 1. Create Module Config
```bash
/backend/modules/YourModule/module.php
```
```php
<?php
return [
    'name' => 'YourModule',
    'version' => '1.0.0',
    'enabled' => true,
];
```

### 2. Create Routes
```bash
/backend/modules/YourModule/routes.php
```
```php
<?php
use PHPFrarm\Core\Router;
use PHPFrarm\Core\Response;

Router::get('/api/yourmodule/hello', function($request) {
    Response::success(['message' => 'Hello World!']);
});
```

### 3. Done! 🎉
Module auto-loads on next request. No index.php changes needed!

---

## Middleware Usage

### Single Route
```php
Router::get('/api/endpoint', $handler, ['auth']);
```

### Multiple Middleware
```php
Router::post('/api/endpoint', $handler, ['auth', 'rateLimit', 'jsonParser']);
```

### Route Group
```php
Router::group('/api/admin', ['auth', 'adminOnly'], function() {
    Router::get('/users', $handler1);
    Router::post('/settings', $handler2, ['jsonParser']);
});
```

---

## Built-in Middleware

| Name | Purpose | Usage |
|------|---------|-------|
| `auth` | JWT authentication | Protected endpoints |
| `rateLimit` | Rate limiting | Public endpoints |
| `cors` | CORS headers | Cross-origin requests |
| `jsonParser` | JSON validation | POST/PUT routes |
| `adminOnly` | Admin access | Admin endpoints |
| `logRequest` | Request logging | Debugging |

---

## Route Patterns

### Simple Route
```php
Router::get('/api/users', function($request) {
    Response::success(['users' => []]);
});
```

### With Path Parameters
```php
Router::get('/api/users/{id}/posts/{postId}', 
    function($request, $id, $postId) {
        Response::success(['user_id' => $id, 'post_id' => $postId]);
    }
);
```

### Protected Route
```php
Router::post('/api/profile', function($request) {
    $userId = $request['user']['user_id']; // From auth middleware
    Response::success(['user_id' => $userId]);
}, ['auth', 'rateLimit']);
```

---

## Request Object

```php
function handler($request) {
    $request['method'];    // 'GET', 'POST', etc.
    $request['path'];      // '/api/users/123'
    $request['params'];    // ['id' => '123'] (from {id})
    $request['query'];     // ['page' => 1] (from ?page=1)
    $request['body'];      // Parsed JSON body
    $request['headers'];   // All headers
    $request['user'];      // User object (from auth middleware)
}
```

---

## Database Operations

### ✅ Correct (Stored Procedure)
```php
use PHPFrarm\Core\Database;

$users = Database::callProcedure('sp_get_user_by_email', ['user@example.com']);
```

### ❌ Forbidden (Raw SQL)
```php
Database::query("SELECT * FROM users"); // THROWS EXCEPTION
```

---

## Response Methods

```php
use PHPFrarm\Core\Response;

// Success responses
Response::success($data, 'Success message', 200);
Response::paginated($items, $total, $page, $perPage);

// Error responses
Response::badRequest('Validation failed', $errors);
Response::unauthorized('Authentication required');
Response::forbidden('Access denied');
Response::notFound('Resource not found');
Response::tooManyRequests('Rate limit exceeded');
Response::serverError('Internal error');
```

---

## Logging

```php
use PHPFrarm\Core\Logger;

Logger::info('Info message', ['key' => 'value']);
Logger::warning('Warning message', $context);
Logger::error('Error message', $context);
Logger::debug('Debug message', $context);
Logger::access('Access log', $context);
Logger::audit('User action', $context);
Logger::security('Security event', $context);
```

All logs automatically include trace IDs and are stored in MongoDB.

---

## Common Patterns

### Create Operation
```php
Router::post('/api/users', function($request) {
    $data = $request['body'];
    
    // Validate
    if (empty($data['email'])) {
        Response::badRequest('Email is required');
        return;
    }
    
    try {
        // Use stored procedure
        $result = Database::callProcedure('sp_create_user', [
            bin2hex(random_bytes(16)),
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT)
        ]);
        
        Logger::audit('User created', ['email' => $data['email']]);
        Response::success($result, 'User created', 201);
        
    } catch (\Exception $e) {
        Logger::error('Failed to create user', ['error' => $e->getMessage()]);
        Response::serverError('Creation failed');
    }
}, ['auth', 'jsonParser', 'rateLimit']);
```

### Read Operation
```php
Router::get('/api/users/{id}', function($request, $id) {
    try {
        $users = Database::callProcedure('sp_get_user_by_id', [$id]);
        
        if (empty($users)) {
            Response::notFound('User not found');
            return;
        }
        
        Response::success($users[0]);
        
    } catch (\Exception $e) {
        Logger::error('Failed to get user', ['user_id' => $id]);
        Response::serverError('Failed to retrieve user');
    }
}, ['auth']);
```

### Update Operation
```php
Router::put('/api/users/{id}', function($request, $id) {
    $data = $request['body'];
    $userId = $request['user']['user_id'];
    
    // Check ownership or admin
    if ($id !== $userId && $request['user']['role'] !== 'admin') {
        Response::forbidden('Cannot update other users');
        return;
    }
    
    try {
        Database::callProcedure('sp_update_user', [$id, $data['name']]);
        Logger::audit('User updated', ['user_id' => $id]);
        Response::success(['updated' => true]);
        
    } catch (\Exception $e) {
        Logger::error('Update failed', ['user_id' => $id]);
        Response::serverError('Update failed');
    }
}, ['auth', 'jsonParser']);
```

### Delete Operation
```php
Router::delete('/api/users/{id}', function($request, $id) {
    try {
        Database::callProcedure('sp_soft_delete_user', [$id]);
        Logger::audit('User deleted', [
            'user_id' => $id,
            'deleted_by' => $request['user']['user_id']
        ]);
        Response::success(['deleted' => true]);
        
    } catch (\Exception $e) {
        Logger::error('Delete failed', ['user_id' => $id]);
        Response::serverError('Delete failed');
    }
}, ['auth', 'adminOnly']);
```

---

## Module Disable/Enable

### Disable Module
```php
// In module.php
return [
    'enabled' => false, // Module won't load
];
```

### Check Module Status
```bash
GET /api/status
```

---

## Testing Endpoints

### Health Check
```bash
GET /health
```

### Module Status
```bash
GET /api/status
```

### Protected Endpoint
```bash
POST /api/users/profile
Headers:
  Authorization: Bearer <JWT_TOKEN>
  Content-Type: application/json
Body:
  {"first_name": "John"}
```

---

## Directory Reference

```
/farm/backend/
├── public/index.php          # Entry point (never modify!)
├── app/
│   ├── Core/                 # Framework classes
│   └── Middleware/           # Middleware collection
├── modules/                  # Your modules here
│   ├── Auth/                 # Auth module (example)
│   └── User/                 # User module (example)
├── database/
│   ├── mysql/
│   │   ├── tables/           # SQL table definitions
│   │   └── stored_procedures/# SQL procedures
│   └── mongo/indexes/        # MongoDB indexes
├── MODULES_GUIDE.md          # Detailed module guide
└── ARCHITECTURE.md           # Architecture overview
```

---

## Need Help?

- 📘 **Full Guide**: [MODULES_GUIDE.md](MODULES_GUIDE.md)
- 🏗️ **Architecture**: [ARCHITECTURE.md](ARCHITECTURE.md)
- 📖 **Setup**: [README.md](../README.md)
- 🐛 **Logs**: MongoDB `phpfrarm_logs` database

---

**Framework Version**: 1.0.0
**Last Updated**: January 2026
