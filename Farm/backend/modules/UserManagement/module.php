<?php

/**
 * User Management Module
 * 
 * Provides administrative user management functionality:
 * - CRUD operations on any user (admin-only)
 * - Role and permission assignment
 * - Account status management (lock, unlock, suspend, activate)
 * - Email verification management
 * - User identifiers management (email, phone)
 * - User search and filtering
 * - Bulk user operations
 * 
 * All endpoints require authentication and specific permissions.
 * 
 * This is separate from the User module which handles self-service operations.
 */

return [
    'name' => 'UserManagement',
    'version' => '1.0.0',
    'description' => 'Administrative user management: CRUD, roles, status, identifiers, and bulk operations',
    'author' => 'PHPFrarm Framework',
    
    'requires' => [
        'php' => '>=8.1',
        'modules' => ['User', 'Role', 'Permission']
    ],
    
    'config' => [
        'require_admin' => true,
        'audit_all_actions' => true,
        'max_bulk_operations' => 1000,
        'allow_user_deletion' => false, // Enforce soft deletes only
        'require_approval_for_role_changes' => false
    ],
    
    'database' => [
        // Stored procedures are shared with User module
        'stored_procedures' => [
            'sp_get_all_users',
            'sp_get_user_by_id',
            'sp_update_user',
            'sp_soft_delete_user',
            'sp_count_users',
            'sp_search_users'
        ]
    ],
    
    'bootstrap' => function() {
        \PHPFrarm\Core\Logger::info('UserManagement module initialized', [
            'admin_only' => true,
            'audit_enabled' => true,
            'soft_delete_enforced' => true
        ]);
    }
];
