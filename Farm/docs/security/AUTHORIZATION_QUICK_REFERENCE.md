# 🚀 Authorization Module - Quick Reference Card

## 📌 Permission Format

```
{resource}:{action}

Examples:
- users:create
- posts:read
- users:*        (all user actions)
- *:*            (everything - superadmin)
```

---

## 🔐 Middleware Usage

### Single Permission
```php
#[Route('/api/v1/posts', method: 'POST', middleware: ['auth', 'permission:posts:create'])]
```

### Any Permission (OR logic)
```php
#[Route('/api/v1/content', method: 'POST', middleware: ['auth', 'permissionAny:posts:create,pages:create'])]
```

### All Permissions (AND logic)
```php
#[Route('/api/v1/reports', method: 'GET', middleware: ['auth', 'permissionAll:reports:read,reports:export'])]
```

### Scope-Based
```php
#[Route('/api/v1/external', method: 'GET', middleware: ['auth', 'scope:api:read'])]
```

---

## 🎯 Manual Authorization Checks

```php
use PHPFrarm\Core\Authorization\AuthorizationManager;

$authz = new AuthorizationManager($request['user']);

// Single permission
if ($authz->can('users:delete')) { /* ... */ }

// Multiple permissions - ALL required
if ($authz->canAll(['users:read', 'users:update'])) { /* ... */ }

// Multiple permissions - ANY sufficient
if ($authz->canAny(['posts:update', 'pages:update'])) { /* ... */ }

// Scope check
if ($authz->hasScope('api:write')) { /* ... */ }

// Resource access with ownership
if ($authz->canAccess($resource, 'delete')) { /* ... */ }
```

---

## 🏆 Resource-Level Authorization

```php
// Load resource
$post = PostDAO::getPostById($postId);

// Check ownership (user must own OR have posts:* permission)
$authz = new AuthorizationManager($request['user']);

if (!$authz->canAccess($post, 'update')) {
    Response::forbidden('You can only update your own posts');
    return;
}

// Proceed with update
```

**Requirements for ownership:**
- Resource must have `user_id` field
- Matches current user's `user_id`
- OR user has wildcard permission for resource (`posts:*` or `*:*`)

---

## 📜 Policy-Based Authorization

### Create Policy
```php
// app/Policies/PostPolicy.php
namespace PHPFrarm\Policies;

use PHPFrarm\Core\Authorization\Policy;

class PostPolicy extends Policy
{
    public function view($post): bool
    {
        if ($post['status'] === 'published') return true;
        if ($post['status'] === 'draft') return $this->owns($post);
        return false;
    }
    
    public function publish($post): bool
    {
        return $this->hasRole('editor') && 
               $this->resourceInState($post, ['draft']);
    }
}
```

### Use Policy
```php
$authz = new AuthorizationManager($request['user']);

if (!$authz->authorize(PostPolicy::class, 'view', $post)) {
    Response::forbidden('You cannot view this post');
    return;
}
```

---

## 👥 System Roles

| Role | Priority | Permissions |
|------|----------|-------------|
| **superadmin** | 1000 | `*:*` - Everything |
| **admin** | 900 | `users:*`, `roles:*`, `permissions:*`, `settings:*` |
| **editor** | 500 | `posts:*`, `pages:*`, `media:*` |
| **author** | 300 | `posts:create`, `posts:read`, `posts:update` |
| **viewer** | 100 | `posts:read` |

---

## 🛠️ Admin API Endpoints

### Roles
```bash
GET    /api/v1/system/roles
GET    /api/v1/system/roles/{roleId}
POST   /api/v1/system/roles
PUT    /api/v1/system/roles/{roleId}
DELETE /api/v1/system/roles/{roleId}
```

### Permissions
```bash
GET    /api/v1/system/permissions
GET    /api/v1/system/permissions/{permissionId}
POST   /api/v1/system/permissions
PUT    /api/v1/system/permissions/{permissionId}
DELETE /api/v1/system/permissions/{permissionId}
```

### User Roles
```bash
GET    /api/v1/system/users/{userId}/roles
POST   /api/v1/system/users/{userId}/roles/{roleId}
DELETE /api/v1/system/users/{userId}/roles/{roleId}
POST   /api/v1/system/users/{userId}/roles/bulk
PUT    /api/v1/system/users/{userId}/roles/sync
```

---

## 🗄️ Database Setup

```bash
# Create tables
mysql -u root -p phpfrarm < farm/backend/database/mysql/tables/authorization.sql

# Create stored procedures
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_roles.sql
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_permissions.sql
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_role_permissions.sql
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_user_roles.sql
```

---

## 🔍 Policy Helper Methods

```php
// In your Policy class:

$this->can('permission:name')           // Check permission
$this->owns($resource)                  // Check ownership (user_id match)
$this->hasRole('role_name')            // Check role
$this->isAdmin()                        // Check if admin or superadmin
$this->resourceInState($resource, [...]) // Check resource status
```

---

## ⚡ Quick Examples

### Example 1: Simple Permission Check
```php
#[Route('/api/v1/users', method: 'POST', middleware: ['auth', 'permission:users:create'])]
public function createUser(array $request): void
{
    // User already authorized - proceed with logic
    UserService::createUser($request['body']);
}
```

### Example 2: Ownership Check
```php
#[Route('/api/v1/posts/{postId}', method: 'DELETE', middleware: ['auth'])]
public function deletePost(array $request): void
{
    $post = PostDAO::getPostById($request['params']['postId']);
    $authz = new AuthorizationManager($request['user']);
    
    if (!$authz->canAccess($post, 'delete')) {
        Response::forbidden('Cannot delete this post');
        return;
    }
    
    PostService::deletePost($post['post_id']);
}
```

### Example 3: Policy-Based Check
```php
#[Route('/api/v1/posts/{postId}/publish', method: 'POST', middleware: ['auth'])]
public function publishPost(array $request): void
{
    $post = PostDAO::getPostById($request['params']['postId']);
    $authz = new AuthorizationManager($request['user']);
    
    if (!$authz->authorize(PostPolicy::class, 'publish', $post)) {
        Response::forbidden('Cannot publish this post');
        return;
    }
    
    PostService::publishPost($post['post_id']);
}
```

---

## 📚 Full Documentation

See [AUTHORIZATION_GUIDE.md](farm/backend/docs/AUTHORIZATION_GUIDE.md) for complete documentation.

---

## ✅ Checklist for New Feature

When implementing a new feature with authorization:

- [ ] Define permissions (`resource:action`)
- [ ] Add permission to appropriate system roles
- [ ] Use middleware for route-level checks
- [ ] Add ownership checks for user resources
- [ ] Create custom policy if complex rules needed
- [ ] Test with different roles
- [ ] Document permission requirements

---

**Framework Version:** 1.0  
**Module:** Authorization & Access Control  
**Status:** ✅ Production Ready
