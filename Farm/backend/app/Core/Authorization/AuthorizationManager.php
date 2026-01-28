<?php

namespace PHPFrarm\Core\Authorization;

use PHPFrarm\Core\Logger;

/**
 * Authorization Manager
 * 
 * Central authorization service for permission and policy checks
 * 
 * Features:
 * - Permission-based authorization (RBAC)
 * - Scope-based authorization
 * - Resource-level authorization (ownership)
 * - Policy-based authorization
 * - Role hierarchy
 * 
 * Usage:
 * ```php
 * $authz = new AuthorizationManager($user);
 * 
 * if ($authz->can('users:create')) {
 *     // Allow action
 * }
 * 
 * if ($authz->canAccess($resource, 'update')) {
 *     // Allow resource update
 * }
 * ```
 */
class AuthorizationManager
{
    private array $user;
    private array $permissions = [];
    private array $scopes = [];
    private array $policies = [];
    private ?PolicyEngine $policyEngine = null;
    
    public function __construct(array $user, ?PolicyEngine $policyEngine = null)
    {
        $this->user = $user;
        $this->policyEngine = $policyEngine;
        $this->loadUserPermissions();
        $this->loadUserScopes();
    }
    
    /**
     * Check if user has permission
     */
    public function can(string $permission): bool
    {
        // Superadmin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Check explicit permissions
        foreach ($this->permissions as $userPermission) {
            if ($this->permissionMatches($userPermission, $permission)) {
                Logger::debug('Permission granted', [
                    'user_id' => $this->user['user_id'] ?? null,
                    'permission' => $permission
                ]);
                return true;
            }
        }
        
        Logger::debug('Permission denied', [
            'user_id' => $this->user['user_id'] ?? null,
            'permission' => $permission
        ]);
        
        return false;
    }
    
    /**
     * Check if user cannot perform action (inverse of can)
     */
    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }
    
    /**
     * Check if user has any of the given permissions
     */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all of the given permissions
     */
    public function canAll(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->can($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if user has scope
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes);
    }
    
    /**
     * Check if user has any of the scopes
     */
    public function hasAnyScope(array $scopes): bool
    {
        return !empty(array_intersect($this->scopes, $scopes));
    }
    
    /**
     * Check resource-level access (ownership + permission + policies)
     */
    public function canAccess(mixed $resource, string $action, array $context = []): bool
    {
        // Check if resource exists
        if (empty($resource)) {
            return false;
        }
        
        // Superadmin can access everything
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Extract resource type
        $resourceType = $this->getResourceType($resource);
        $permission = "{$resourceType}:{$action}";
        
        // Must have base permission
        if (!$this->can($permission)) {
            Logger::security('Resource access denied - missing permission', [
                'user_id' => $this->user['user_id'] ?? null,
                'resource_type' => $resourceType,
                'action' => $action
            ]);
            return false;
        }
        
        // Check policy engine if available
        if ($this->policyEngine) {
            $policyAllowed = $this->policyEngine->can($this->user, $action, $resource, $context);
            if (!$policyAllowed) {
                Logger::security('Resource access denied by policy', [
                    'user_id' => $this->user['user_id'] ?? null,
                    'resource_type' => $resourceType,
                    'action' => $action
                ]);
                return false;
            }
        }
        
        // Check ownership if resource has user_id
        if ($this->hasOwnership($resource)) {
            if ($this->owns($resource)) {
                return true;
            }
            
            // Check if user has admin override
            if ($this->can("{$resourceType}:*") || $this->can('*:*')) {
                return true;
            }
            
            Logger::security('Resource access denied - not owner', [
                'user_id' => $this->user['user_id'] ?? null,
                'resource_type' => $resourceType
            ]);
            
            return false;
        }
        
        // No ownership required, permission + policy is enough
        return true;
    }
    
    /**
     * Check policy-based authorization
     */
    public function authorize(string $policyClass, string $action, mixed $resource = null): bool
    {
        // Check if policy is registered
        if (!isset($this->policies[$policyClass])) {
            // Dynamically instantiate policy
            if (class_exists($policyClass)) {
                $this->policies[$policyClass] = new $policyClass($this->user);
            } else {
                Logger::error('Policy class not found', ['policy' => $policyClass]);
                return false;
            }
        }
        
        $policy = $this->policies[$policyClass];
        
        if (!($policy instanceof Policy)) {
            Logger::error('Invalid policy class', ['policy' => $policyClass]);
            return false;
        }
        
        return $policy->can($action, $resource);
    }
    
    /**
     * Register a policy
     */
    public function registerPolicy(string $name, Policy $policy): void
    {
        $this->policies[$name] = $policy;
    }
    
    /**
     * Check if user is superadmin
     */
    private function isSuperAdmin(): bool
    {
        $roles = $this->normalizeRoles($this->user['roles'] ?? []);
        
        return in_array('superadmin', $roles);
    }
    
    /**
     * Check if user owns resource
     */
    private function owns(mixed $resource): bool
    {
        $userId = $this->user['user_id'] ?? null;
        
        if (is_array($resource)) {
            return ($resource['user_id'] ?? null) === $userId;
        }
        
        if (is_object($resource) && property_exists($resource, 'user_id')) {
            return $resource->user_id === $userId;
        }
        
        return false;
    }
    
    /**
     * Check if resource has ownership concept
     */
    private function hasOwnership(mixed $resource): bool
    {
        if (is_array($resource)) {
            return isset($resource['user_id']);
        }
        
        if (is_object($resource)) {
            return property_exists($resource, 'user_id');
        }
        
        return false;
    }
    
    /**
     * Get resource type from resource
     */
    private function getResourceType(mixed $resource): string
    {
        if (is_array($resource) && isset($resource['_type'])) {
            return $resource['_type'];
        }
        
        if (is_object($resource)) {
            $class = get_class($resource);
            return strtolower(basename(str_replace('\\', '/', $class)));
        }
        
        return 'resource';
    }
    
    /**
     * Check if permission matches pattern
     */
    private function permissionMatches(string $userPermission, string $requiredPermission): bool
    {
        // Exact match
        if ($userPermission === $requiredPermission) {
            return true;
        }
        
        // Wildcard match (e.g., "users:*" matches "users:create")
        if (str_contains($userPermission, '*')) {
            $pattern = str_replace('*', '.*', $userPermission);
            return (bool)preg_match('#^' . $pattern . '$#', $requiredPermission);
        }
        
        return false;
    }
    
    /**
     * Load user permissions from roles
     */
    private function loadUserPermissions(): void
    {
        // First, load permissions directly from user object (from database)
        // These are loaded by AuthService::buildAuthClaims() during login
        if (isset($this->user['permissions']) && is_array($this->user['permissions'])) {
            $this->permissions = array_merge($this->permissions, $this->user['permissions']);
        }
        
        // Also check for permissions in different format (permission_names)
        if (isset($this->user['permission_names']) && is_array($this->user['permission_names'])) {
            $this->permissions = array_merge($this->permissions, $this->user['permission_names']);
        }
        
        // CRITICAL: Scopes in JWT token contain the permissions!
        // AuthService::buildAuthClaims() puts permission_names into scopes
        // and JWT payload has 'scopes' array with all permissions
        if (isset($this->user['scopes']) && is_array($this->user['scopes'])) {
            $this->permissions = array_merge($this->permissions, $this->user['scopes']);
        }
        
        // Fallback: Load from system roles for backwards compatibility
        $roles = $this->normalizeRoles($this->user['roles'] ?? []);
        
        $systemRoles = Role::system();
        
        foreach ($roles as $roleName) {
            if (isset($systemRoles[$roleName])) {
                $role = $systemRoles[$roleName];
                $this->permissions = array_merge(
                    $this->permissions,
                    $role->getPermissions()
                );
            }
        }
        
        // Remove duplicates
        $this->permissions = array_unique($this->permissions);
    }

    private function normalizeRoles(array|string $roles): array
    {
        if (is_string($roles)) {
            return [$roles];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $normalized[] = $role;
                continue;
            }
            if (is_array($role)) {
                $name = $role['name'] ?? $role['role_name'] ?? null;
                if ($name) {
                    $normalized[] = $name;
                }
            }
        }

        return $normalized;
    }
    
    /**
     * Load user scopes
     */
    private function loadUserScopes(): void
    {
        $this->scopes = $this->user['scopes'] ?? [];
        
        if (is_string($this->scopes)) {
            $this->scopes = explode(',', $this->scopes);
        }
    }
    
    /**
     * Get user permissions
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
    
    /**
     * Get user scopes
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}
