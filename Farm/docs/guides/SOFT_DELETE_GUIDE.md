# Soft Delete Implementation Guide

## Overview
The framework provides automatic soft delete functionality through traits and stored procedures.

## Database Setup

### 1. Add `deleted_at` Column
```sql
ALTER TABLE your_table ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_deleted_at ON your_table(deleted_at);
```

### 2. Import Stored Procedures
```bash
mysql -u root -p your_database < database/mysql/stored_procedures/soft_delete.sql
```

## Usage in DAOs

### Basic Usage
```php
<?php
namespace PHPFrarm\Modules\User\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Traits\SoftDelete;

class UserDAO
{
    use SoftDelete; // Enable soft delete
    
    private Database $db;
    protected string $table = 'users'; // Required for soft delete trait
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    // Your other methods...
}
```

### Available Methods

#### Soft Delete (mark as deleted)
```php
$userDAO->softDelete('user_id_here');
```

#### Restore Deleted Record
```php
$userDAO->restore('user_id_here');
```

#### Permanently Delete
```php
$userDAO->forceDelete('user_id_here'); // Cannot be restored!
```

#### Check If Deleted
```php
if ($userDAO->isDeleted('user_id_here')) {
    echo "User is soft deleted";
}
```

#### Get Only Deleted Records
```php
$deletedUsers = $userDAO->onlyTrashed();
```

#### Get All Records (including deleted)
```php
$allUsers = $userDAO->withTrashed();
```

## Service Layer Example

```php
<?php
namespace PHPFrarm\Modules\User\Services;

class UserService
{
    private UserDAO $userDAO;
    
    public function deleteUser(string $userId): bool
    {
        // Soft delete by default
        return $this->userDAO->softDelete($userId);
    }
    
    public function restoreUser(string $userId): bool
    {
        return $this->userDAO->restore($userId);
    }
    
    public function permanentlyDeleteUser(string $userId): bool
    {
        // Requires admin permission
        return $this->userDAO->forceDelete($userId);
    }
}
```

## Controller Example

```php
<?php
namespace PHPFrarm\Modules\User\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Response;

#[RouteGroup('/api/v1/users')]
class UserController
{
    #[Route('/{id}', method: 'DELETE', middleware: ['auth'])]
    public function delete(array $request, string $id): void
    {
        $success = $this->userService->deleteUser($id);
        
        if ($success) {
            Response::success(null, 'User deleted successfully');
        } else {
            Response::notFound('User not found');
        }
    }
    
    #[Route('/{id}/restore', method: 'POST', middleware: ['auth', 'adminOnly'])]
    public function restore(array $request, string $id): void
    {
        $success = $this->userService->restoreUser($id);
        
        if ($success) {
            Response::success(null, 'User restored successfully');
        } else {
            Response::notFound('User not found or not deleted');
        }
    }
}
```

## Benefits

1. **Data Safety**: Accidentally deleted data can be recovered
2. **Audit Trail**: Maintain history of deletions
3. **Compliance**: Meet regulatory requirements for data retention
4. **Soft Cascade**: Related records can remain accessible
5. **Flexible Queries**: Filter by deleted status easily

## Best Practices

1. **Index `deleted_at`**: Always create index for performance
2. **Cleanup Policy**: Schedule job to permanently delete old soft-deleted records
3. **Admin Access**: Restrict `forceDelete()` to admin users only
4. **Default Queries**: Exclude soft-deleted by default in `SELECT` queries
5. **Cascade Logic**: Define behavior for related records

## Stored Procedure Reference

All soft delete operations use stored procedures for consistency:

- `sp_soft_delete(table, id, deleted_at)` - Mark as deleted
- `sp_restore_deleted(table, id)` - Restore deleted record
- `sp_force_delete(table, id)` - Permanently delete
- `sp_is_deleted(table, id)` - Check deleted status
- `sp_get_trashed(table)` - Get only deleted records
- `sp_get_with_trashed(table)` - Get all records including deleted
