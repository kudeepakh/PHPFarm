# Module Consolidation & Standardization Plan

## Issues Found

### 1. **Duplicate Controllers**
- ✅ **UserController** (Admin vs User) - KEEP BOTH (different purposes)
  - Admin: `/api/v1/system/users/*` - Admin manages all users
  - User: `/api/v1/users/*` - Users manage their own profile
  
- ⚠️ **RoleController** (Admin vs Role) - CONSOLIDATE
  - Admin has basic version
  - Role module has complete version with services
  - **Action**: Keep Role module version, delete Admin version

### 2. **Wrong Namespaces**
- ❌ `Modules\Role\Controllers\RoleController` → ✅ `PHPFrarm\Modules\Role\Controllers\RoleController`
- ❌ `Modules\Permission\Controllers\PermissionController` → ✅ `PHPFrarm\Modules\Permission\Controllers\PermissionController`

### 3. **Module Categorization**

#### ✅ Correctly Placed
- **System Module**: HealthController, DocsController
- **User Module**: UserController, VerificationController, AccountStatusController, UserHealthController
- **Auth Module**: AuthController, OTPController, OTPAdminController, SocialAuthController, UserContextController
- **Storage Module**: StorageController

#### ⚠️ Need Review
- **Admin Module**: Should only contain "admin dashboard" controllers, not domain logic
  - UserController (Admin) - ✅ KEEP (admin view of users)
  - UserRoleController - ❓ Move to Role module or keep?
  - RoleController - ❌ DELETE (duplicate, use Role module)
  - SecurityController - ✅ KEEP (admin security monitoring)
  - TrafficController - ✅ KEEP (admin traffic monitoring)
  - ResilienceController - ✅ KEEP (admin resilience monitoring)
  - LockingController - ✅ KEEP (admin locking management)
  - CacheController - ✅ KEEP (admin cache management)

## Consolidation Actions

### Action 1: Fix Namespaces
- Update Role/Controllers/RoleController.php namespace
- Update Permission/Controllers/PermissionController.php namespace

### Action 2: Remove Duplicate RoleController
- Delete Admin/Controllers/RoleController.php
- Keep Role/Controllers/RoleController.php (more complete)

### Action 3: Analyze UserRoleController
- Check if it should be in Role module or Admin module
- Move if necessary

### Action 4: Verify All Controllers Load
- Test all endpoints after changes
- Check health, admin, auth, user, role, permission endpoints

## Standard Module Structure

```
/modules/{ModuleName}/
├── module.php                          # Module definition (required)
├── Controllers/                        # HTTP controllers
│   └── {Feature}Controller.php
├── Services/                           # Business logic
│   └── {Feature}Service.php
├── DAO/                                # Data access (stored procedures)
│   └── {Feature}DAO.php
├── DTO/                                # Data transfer objects
│   └── {Feature}DTO.php
├── lang/                               # Translations
│   ├── en.php
│   └── es.php
└── routes.php                          # Optional route definitions
```

## Standard Namespace Pattern

✅ **Correct**: `PHPFrarm\Modules\{Module}\{Type}\{ClassName}`
- `PHPFrarm\Modules\User\Controllers\UserController`
- `PHPFrarm\Modules\Role\Services\RoleService`
- `PHPFrarm\Modules\Auth\DAO\AuthDAO`

❌ **Wrong**: `Modules\{Module}\{Type}\{ClassName}` (missing PHPFrarm prefix)
