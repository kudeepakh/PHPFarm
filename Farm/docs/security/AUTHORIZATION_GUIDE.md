# Authorization Module - Complete Implementation Guide

## Overview

The Authorization Module provides comprehensive role-based access control (RBAC) with support for:
- **Permission-based authorization** (`users:create`, `posts:*`)
- **Scope-based authorization** (OAuth2-style scopes)
- **Resource-level authorization** (ownership validation)
- **Policy-based authorization** (complex custom rules)
- **Admin APIs** for role & permission management

---

## Architecture

### Core Components

1. **Permission** - Granular permission definitions (`resource:action`)
2. **Role** - Groups of permissions with priorities
3. **Policy** - Abstract base for complex authorization logic
4. **AuthorizationManager** - Central authorization service
5. **AuthorizationMiddleware** - Request-level authorization checks

### Database Schema

- `roles` - Role definitions
- `permissions` - Permission definitions
- `role_permissions` - Role-permission assignments
- `user_roles` - User-role assignments

---

## Usage Guide

### 1. Basic Permission Checks in Controllers

```php
use PHPFrarm\Core\Annotations\Route;
use PHPFrarm\Core\Response;

class PostController
{
    // Permission required via middleware
    #[Route('/api/v1/posts', method: 'POST', middleware: ['auth', 'permission:posts:create'])]
    public function createPost(array $request): void
    {
        // User already authorized by middleware
        // Proceed with business logic
        Response::success(['post_id' => '...'], 'Post created');
    }
    
    // Multiple permission options
    #[Route('/api/v1/content', method: 'POST', middleware: ['auth', 'permissionAny:posts:create,pages:create'])]
    public function createContent(array $request): void
    {
        // User needs either posts:create OR pages:create
    }
    
    // All permissions required
    #[Route('/api/v1/reports/export', method: 'GET', middleware: ['auth', 'permissionAll:reports:read,reports:export'])]
    public function exportReport(array $request): void
    {
        // User needs both permissions
    }
}
```

### 2. Resource-Level Authorization (Ownership)

```php
use PHPFrarm\Core\Authorization\AuthorizationManager;

class PostController
{
    #[Route('/api/v1/posts/{postId}', method: 'PUT', middleware: ['auth', 'permission:posts:update'])]
    public function updatePost(array $request): void
    {
        $postId = $request['params']['postId'];
        
        // Load post
        $post = PostDAO::getPostById($postId);
        
        if (!$post) {
            Response::notFound('Post not found');
            return;
        }
        
        // Check ownership (user must own the resource OR have posts:* permission)
        $authz = new AuthorizationManager($request['user']);
        
        if (!$authz->canAccess($post, 'update')) {
            Response::forbidden('You can only update your own posts');
            return;
        }
        
        // Proceed with update
        PostService::updatePost($postId, $request['body']);
        Response::success(null, 'Post updated');
    }
}
```

### 3. Policy-Based Authorization

Create custom policy for complex rules:

```php
// app/Policies/PostPolicy.php
namespace PHPFrarm\Policies;

use PHPFrarm\Core\Authorization\Policy;

class PostPolicy extends Policy
{
    /**
     * Check if user can view post
     */
    public function view($post): bool
    {
        // Published posts visible to all
        if ($post['status'] === 'published') {
            return true;
        }
        
        // Draft posts only visible to owner or editors
        if ($post['status'] === 'draft') {
            return $this->owns($post) || $this->hasRole('editor');
        }
        
        return false;
    }
    
    /**
     * Check if user can publish post
     */
    public function publish($post): bool
    {
        // Only editors and above can publish
        if (!$this->hasRole('editor')) {
            return false;
        }
        
        // Can't publish deleted posts
        return $this->resourceInState($post, ['draft', 'published']);
    }
}
```

Use policy in controller:

```php
#[Route('/api/v1/posts/{postId}', method: 'GET', middleware: ['auth'])]
public function getPost(array $request): void
{
    $postId = $request['params']['postId'];
    $post = PostDAO::getPostById($postId);
    
    if (!$post) {
        Response::notFound('Post not found');
        return;
    }
    
    // Use policy for complex authorization
    $authz = new AuthorizationManager($request['user']);
    
    if (!$authz->authorize(PostPolicy::class, 'view', $post)) {
        Response::forbidden('You cannot view this post');
        return;
    }
    
    Response::success($post);
}
```

### 4. Scope-Based Authorization

```php
#[Route('/api/v1/external/data', method: 'GET', middleware: ['auth', 'scope:api:read'])]
public function getExternalData(array $request): void
{
    // User's token must have 'api:read' scope
    // Useful for API integrations, third-party access
}
```

### 5. Manual Authorization Checks

```php
use PHPFrarm\Core\Authorization\AuthorizationManager;

$authz = new AuthorizationManager($request['user']);

// Single permission check
if ($authz->can('users:delete')) {
    // User has permission
}

// Check multiple permissions - ALL required
if ($authz->canAll(['users:read', 'users:update'])) {
    // User has all permissions
}

// Check multiple permissions - ANY sufficient
if ($authz->canAny(['posts:update', 'pages:update'])) {
    // User has at least one permission
}

// Check scope
if ($authz->hasScope('api:write')) {
    // Token has scope
}

// Check resource access with ownership
if ($authz->canAccess($resource, 'delete')) {
    // User can delete resource (owns it or has permission)
}
```

---

## Admin APIs

### Role Management

```bash
# List all roles
GET /api/v1/system/roles
Middleware: auth, permission:roles:read

# Get role with permissions
GET /api/v1/system/roles/{roleId}
Middleware: auth, permission:roles:read

# Create role
POST /api/v1/system/roles
Middleware: auth, permission:roles:create
Body: { "name": "moderator", "description": "...", "priority": 400 }

# Update role
PUT /api/v1/system/roles/{roleId}
Middleware: auth, permission:roles:update
Body: { "description": "Updated description" }

# Delete role
DELETE /api/v1/system/roles/{roleId}
Middleware: auth, permission:roles:delete
```

### Permission Management

```bash
# List all permissions
GET /api/v1/system/permissions
Middleware: auth, permission:permissions:read

# Get permission
GET /api/v1/system/permissions/{permissionId}
Middleware: auth, permission:permissions:read

# Create permission
POST /api/v1/system/permissions
Middleware: auth, permission:permissions:create
Body: { "name": "comments:moderate", "description": "...", "resource": "comments", "action": "moderate" }

# Get permissions by resource
GET /api/v1/system/permissions/resource/{resource}
Middleware: auth, permission:permissions:read
```

### Role-Permission Assignment

```bash
# Assign permission to role
POST /api/v1/system/roles/{roleId}/permissions/{permissionId}
Middleware: auth, permission:roles:update

# Remove permission from role
DELETE /api/v1/system/roles/{roleId}/permissions/{permissionId}
Middleware: auth, permission:roles:update
```

### User-Role Assignment

```bash
# Get user's roles & permissions
GET /api/v1/system/users/{userId}/roles
Middleware: auth, permission:users:read

# Assign role to user
POST /api/v1/system/users/{userId}/roles/{roleId}
Middleware: auth, permission:users:update

# Remove role from user
DELETE /api/v1/system/users/{userId}/roles/{roleId}
Middleware: auth, permission:users:update

# Bulk assign roles
POST /api/v1/system/users/{userId}/roles/bulk
Middleware: auth, permission:users:update
Body: { "role_ids": ["role1", "role2"] }

# Sync roles (replace all)
PUT /api/v1/system/users/{userId}/roles/sync
Middleware: auth, permission:users:update
Body: { "role_ids": ["role1", "role2"] }
```

---

## System Roles

### Predefined Roles

1. **superadmin** (Priority: 1000)
   - Permission: `*:*` (full access)
   - Cannot be deleted

2. **admin** (Priority: 900)
   - Permissions: `users:*`, `roles:*`, `permissions:*`, `settings:*`
   - Full user & system management

3. **editor** (Priority: 500)
   - Permissions: `posts:*`, `pages:*`, `media:*`
   - Content management

4. **author** (Priority: 300)
   - Permissions: `posts:create`, `posts:read`, `posts:update`
   - Content creation

5. **viewer** (Priority: 100)
   - Permissions: `posts:read`
   - Read-only access

---

## Permission Format

### Naming Convention
`{resource}:{action}`

### Examples
- `users:create` - Create users
- `users:read` - Read users
- `users:update` - Update users
- `users:delete` - Delete users
- `users:*` - All user actions (wildcard)
- `*:*` - All resources, all actions (superadmin)

### Wildcard Matching
- `posts:*` matches `posts:create`, `posts:update`, `posts:delete`, etc.
- `*:read` matches `users:read`, `posts:read`, `comments:read`, etc.
- `*:*` matches everything

---

## Database Setup

### 1. Create Tables

```bash
mysql -u root -p phpfrarm < farm/backend/database/mysql/tables/authorization.sql
```

### 2. Create Stored Procedures

```bash
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_roles.sql
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_permissions.sql
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_role_permissions.sql
mysql -u root -p phpfrarm < farm/backend/database/mysql/stored_procedures/authorization/sp_user_roles.sql
```

---

## Security Considerations

1. **Superadmin Bypass** - Superadmin (`*:*`) bypasses all authorization checks
2. **System Roles** - Cannot be deleted (protected)
3. **Ownership** - Resources with `user_id` automatically support ownership checks
4. **Token Scopes** - Stored in JWT payload as `scopes` array
5. **Audit Logs** - All permission checks logged to MongoDB

---

## Testing

### Example: Test Permission Check

```php
// Create test user with role
$userId = UUIDGenerator::v7();
$roleId = '01000000-0000-7000-8000-000000000004'; // author role

RoleDAO::assignRoleToUser($userId, $roleId);

// Get user permissions
$permissions = RoleDAO::getUserPermissions($userId);
// Should contain: posts:create, posts:read, posts:update

// Test authorization
$user = ['user_id' => $userId, 'roles' => ['author']];
$authz = new AuthorizationManager($user);

assert($authz->can('posts:create') === true);
assert($authz->can('posts:delete') === false);
```

---

## Migration from Old System

If you had `adminOnly` middleware:

**Before:**
```php
#[Route('/api/v1/users', middleware: ['auth', 'adminOnly'])]
```

**After:**
```php
#[Route('/api/v1/users', middleware: ['auth', 'permission:users:read'])]
```

Or use role check:
```php
if (!$authz->hasRole('admin')) {
    Response::forbidden('Admin access required');
}
```

---

## Best Practices

1. **Use permissions over roles** in middleware - More granular
2. **Define custom policies** for complex business rules
3. **Check ownership** for user-owned resources
4. **Use scopes** for API access control
5. **Wildcard permissions** for admin roles only
6. **Log all authorization failures** for security monitoring

---

## Complete Module Summary

✅ **Implemented:**
- Permission class with wildcard matching
- Role class with 5 system roles
- Policy abstract base class
- AuthorizationManager service
- AuthorizationMiddleware (permission, scope, ownership)
- RoleDAO, PermissionDAO
- AuthorizationService
- RoleController, PermissionController, UserRoleController
- Database tables: roles, permissions, role_permissions, user_roles
- 20+ stored procedures
- Seeded system roles & permissions

✅ **Features:**
- RBAC (Role-Based Access Control)
- Scope-based permissions
- Resource-level authorization
- Ownership validation
- Policy-driven access
- Admin APIs for management
- Wildcard permission matching
- Superadmin bypass
- Audit logging

**Status:** Module 3 - Complete Authorization & Access Control - ✅ COMPLETE
