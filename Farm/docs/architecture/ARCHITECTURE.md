# 🏗️ Improved Framework Architecture

## Before vs After

### ❌ Old Architecture (Manual Routing)
```
index.php
├── Manual route checking: if (path === '/auth')
├── Manual module loading: require 'modules/Auth/routes.php'
├── No middleware system
└── Had to modify index.php for every new module
```

### ✅ New Architecture (Auto-Loading + Middleware)
```
index.php (Never needs modification!)
├── ModuleLoader::loadAll()
│   ├── Scans /modules directory
│   ├── Loads module.php from each module
│   ├── Executes bootstrap() if defined
│   └── Loads routes.php if exists
├── Router::dispatch()
│   ├── Matches request to registered routes
│   ├── Executes middleware chain
│   └── Calls route handler
└── Response with trace IDs
```

## Request Flow

```
1. HTTP Request
   ↓
2. index.php (Bootstrap)
   ├── Load environment
   ├── Initialize TraceContext (generate IDs)
   ├── Register middleware
   └── Load all modules
   ↓
3. ModuleLoader
   ├── Find modules (*/module.php)
   ├── Check if enabled
   ├── Run bootstrap()
   └── Load routes
   ↓
4. Router::dispatch()
   ├── Match route pattern
   ├── Extract path params
   └── Build middleware chain
   ↓
5. Middleware Execution (in order)
   ├── cors → Set headers
   ├── rateLimit → Check limits
   ├── auth → Verify token
   ├── jsonParser → Parse body
   └── adminOnly → Check role
   ↓
6. Route Handler
   ├── Business logic
   ├── Database calls (stored procedures)
   └── Return response
   ↓
7. Response::success/error
   ├── Add trace IDs
   ├── Set headers
   └── JSON output
   ↓
8. Logger (MongoDB)
   ├── Log request
   ├── Log response
   └── Include trace IDs
```

## Module Auto-Loading Flow

```
/modules
├── Auth/
│   ├── module.php ────┐
│   └── routes.php ────┤
├── User/              │
│   ├── module.php ────┤──> ModuleLoader scans all
│   └── routes.php ────┤
└── Blog/              │
    ├── module.php ────┤
    └── routes.php ────┘

ModuleLoader Process:
1. Scan /modules for directories
2. Check for module.php in each
3. Load & validate config
4. Skip if enabled=false
5. Run bootstrap() function
6. Load routes.php
7. Routes registered with Router
```

## Middleware Chain Execution

```
Example: POST /api/users/profile
Middleware: ['auth', 'rateLimit', 'jsonParser']

Execution Order:
┌─────────────────────────────────────┐
│ 1. Request arrives                  │
└───────────┬─────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 2. cors middleware                  │
│    - Set CORS headers               │
└───────────┬─────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 3. auth middleware                  │
│    - Verify JWT token               │
│    - Add user to request            │
│    - If invalid: Response::401      │
└───────────┬─────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 4. rateLimit middleware             │
│    - Check IP request count         │
│    - If exceeded: Response::429     │
└───────────┬─────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 5. jsonParser middleware            │
│    - Validate JSON body             │
│    - If invalid: Response::400      │
└───────────┬─────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 6. Route Handler                    │
│    - Your business logic            │
│    - Database::callProcedure()      │
│    - Response::success()            │
└───────────┬─────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 7. Response with trace IDs          │
└─────────────────────────────────────┘
```

## Route Registration Patterns

### Pattern 1: Simple Route
```php
Router::get('/api/hello', function($request) {
    Response::success(['message' => 'Hello']);
});
```

### Pattern 2: With Middleware
```php
Router::post('/api/data', function($request) {
    // ...
}, ['auth', 'rateLimit']);
```

### Pattern 3: With Path Parameters
```php
Router::get('/api/users/{userId}/posts/{postId}', 
    function($request, $userId, $postId) {
        // $userId and $postId extracted from URL
    }
);
```

### Pattern 4: Route Group
```php
Router::group('/api/admin', ['auth', 'adminOnly'], function() {
    Router::get('/users', $handler1);      // Uses: auth + adminOnly
    Router::delete('/users/{id}', $handler2, ['logRequest']); // Uses: auth + adminOnly + logRequest
});
```

### Pattern 5: Nested Groups
```php
Router::group('/api', ['cors'], function() {
    
    Router::group('/public', ['rateLimit'], function() {
        Router::get('/info', $handler); // Uses: cors + rateLimit
    });
    
    Router::group('/private', ['auth'], function() {
        Router::get('/profile', $handler); // Uses: cors + auth
    });
    
});
```

## File Organization

```
/farm/backend/
├── public/
│   └── index.php ─────────────────┐ Entry point (no modification needed)
├── app/
│   ├── Core/
│   │   ├── Database.php ──────────┤ Stored procedure enforcer
│   │   ├── Logger.php ────────────┤ MongoDB logging
│   │   ├── Response.php ──────────┤ Standard envelopes
│   │   ├── TraceContext.php ──────┤ Trace ID manager
│   │   ├── Router.php ────────────┤ NEW: Route & middleware manager
│   │   └── ModuleLoader.php ──────┤ NEW: Auto-discover modules
│   └── Middleware/
│       └── CommonMiddleware.php ──┤ NEW: Built-in middleware
└── modules/
    ├── Auth/
    │   ├── module.php ────────────┤ Module config
    │   └── routes.php ────────────┤ Auth routes
    └── User/
        ├── module.php ────────────┤ Module config
        └── routes.php ────────────┤ User routes
```

## Key Improvements

### 1. ✅ Zero Index.php Modifications
- Add new module → Create folder → Done!
- No routing logic in index.php
- Pure bootstrap code only

### 2. ✅ Configurable Middleware
- Apply per route: `['auth']`
- Apply to groups: `Router::group('/api', ['auth'], ...)`
- Combine multiple: `['auth', 'rateLimit', 'adminOnly']`
- Execution order matters

### 3. ✅ Module Metadata
```php
// module.php provides:
- name, version, description
- enabled flag (turn on/off)
- dependencies (other modules)
- bootstrap function
- custom config
```

### 4. ✅ Path Parameters
```php
// Automatic extraction
Router::get('/users/{id}/posts/{postId}', 
    function($request, $id, $postId) { ... }
);
```

### 5. ✅ Request Object
```php
[
    'method' => 'POST',
    'path' => '/api/users/123',
    'params' => ['id' => '123'],
    'query' => ['page' => 1],
    'body' => ['name' => 'John'],
    'headers' => ['Authorization' => '...'],
    'user' => ['user_id' => '...'] // Added by auth middleware
]
```

## Benefits Summary

| Feature | Before | After |
|---------|--------|-------|
| Add Module | Modify index.php | Just create folder |
| Middleware | Manual in each route | Configurable per route/group |
| Route Params | Manual parsing | Auto-extracted |
| Module Config | Hardcoded | Declarative module.php |
| Enable/Disable | Comment out code | Set enabled=false |
| Bootstrap Logic | Scattered | Centralized in bootstrap() |
| Code Reuse | Copy-paste | Middleware composition |

## Production Ready Features

✅ Authentication (JWT)
✅ Rate Limiting
✅ CORS handling
✅ Admin-only routes
✅ Request validation
✅ Structured logging
✅ Trace ID propagation
✅ Error handling
✅ Stored procedure enforcement
✅ Module isolation
✅ Zero-config module loading

---

**The framework is now fully modular, maintainable, and production-ready!**
