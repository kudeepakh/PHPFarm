<?php

namespace PHPFrarm\Core\Authorization;

/**
 * Authorization Policy
 * 
 * Policies provide complex authorization logic beyond simple permissions
 * Used for resource-level and context-aware authorization
 * 
 * Examples:
 * - User can only edit their own posts
 * - Manager can approve items from their department
 * - Admin can access during business hours only
 */
abstract class Policy
{
    protected array $user;
    
    public function __construct(array $user)
    {
        $this->user = $user;
    }
    
    /**
     * Check if user can perform action on resource
     */
    abstract public function can(string $action, mixed $resource = null): bool;
    
    /**
     * Helper: Check if user owns the resource
     */
    protected function owns(mixed $resource): bool
    {
        if (is_array($resource) && isset($resource['user_id'])) {
            return $resource['user_id'] === ($this->user['user_id'] ?? null);
        }
        
        if (is_object($resource) && property_exists($resource, 'user_id')) {
            return $resource->user_id === ($this->user['user_id'] ?? null);
        }
        
        return false;
    }
    
    /**
     * Helper: Check if user has role
     */
    protected function hasRole(string $role): bool
    {
        $userRoles = $this->user['roles'] ?? [];
        
        if (is_string($userRoles)) {
            $userRoles = [$userRoles];
        }
        
        return in_array($role, $userRoles);
    }
    
    /**
     * Helper: Check if user is admin
     */
    protected function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('superadmin');
    }
    
    /**
     * Helper: Check if resource is in allowed state
     */
    protected function resourceInState(mixed $resource, array $allowedStates): bool
    {
        if (is_array($resource) && isset($resource['status'])) {
            return in_array($resource['status'], $allowedStates);
        }
        
        if (is_object($resource) && property_exists($resource, 'status')) {
            return in_array($resource->status, $allowedStates);
        }
        
        return false;
    }
}
