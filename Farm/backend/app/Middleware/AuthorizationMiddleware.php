<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Authorization\AuthorizationManager;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;

/**
 * Authorization Middleware Collection
 * 
 * Provides middleware for different authorization checks:
 * - Permission-based
 * - Scope-based
 * - Resource ownership
 * - Policy-based
 */
class AuthorizationMiddleware
{
    /**
     * Require specific permission
     * 
     * Usage: #[Route('/users', middleware: ['auth', 'permission:users:create'])]
     */
    public static function requirePermission(string $permission): callable
    {
        return function(array $request, callable $next) use ($permission) {
            $user = $request['user'] ?? null;
            
            if (!$user) {
                Logger::security('Permission check failed - no user', [
                    'permission' => $permission
                ]);
                Response::unauthorized('auth.required');
                return null;
            }
            
            $authz = new AuthorizationManager($user);
            
            if (!$authz->can($permission)) {
                Logger::security('Permission denied', [
                    'user_id' => $user['user_id'] ?? null,
                    'permission' => $permission,
                    'path' => $request['path']
                ]);
                
                Response::forbidden('auth.permission_required');
                return null;
            }
            
            // Add authz to request for controller use
            $request['authz'] = $authz;
            
            return $next($request);
        };
    }
    
    /**
     * Require any of the permissions
     * 
     * Usage: #[Route('/content', middleware: ['auth', 'permissionAny:posts:create,pages:create'])]
     */
    public static function requireAnyPermission(array $permissions): callable
    {
        return function(array $request, callable $next) use ($permissions) {
            $user = $request['user'] ?? null;
            
            if (!$user) {
                Response::unauthorized('auth.required');
                return null;
            }
            
            $authz = new AuthorizationManager($user);
            
            if (!$authz->canAny($permissions)) {
                Logger::security('Any permission denied', [
                    'user_id' => $user['user_id'] ?? null,
                    'permissions' => $permissions
                ]);
                
                Response::forbidden('auth.insufficient_permissions');
                return null;
            }
            
            $request['authz'] = $authz;
            return $next($request);
        };
    }
    
    /**
     * Require all permissions
     * 
     * Usage: #[Route('/admin/reports', middleware: ['auth', 'permissionAll:reports:read,reports:export'])]
     */
    public static function requireAllPermissions(array $permissions): callable
    {
        return function(array $request, callable $next) use ($permissions) {
            $user = $request['user'] ?? null;
            
            if (!$user) {
                Response::unauthorized('auth.required');
                return null;
            }
            
            $authz = new AuthorizationManager($user);
            
            if (!$authz->canAll($permissions)) {
                Logger::security('All permissions denied', [
                    'user_id' => $user['user_id'] ?? null,
                    'permissions' => $permissions
                ]);
                
                Response::forbidden('auth.insufficient_permissions');
                return null;
            }
            
            $request['authz'] = $authz;
            return $next($request);
        };
    }
    
    /**
     * Require specific scope
     * 
     * Usage: #[Route('/api/data', middleware: ['auth', 'scope:api:read'])]
     */
    public static function requireScope(string $scope): callable
    {
        return function(array $request, callable $next) use ($scope) {
            $user = $request['user'] ?? null;
            
            if (!$user) {
                Response::unauthorized('auth.required');
                return null;
            }
            
            $authz = new AuthorizationManager($user);
            
            if (!$authz->hasScope($scope)) {
                Logger::security('Scope denied', [
                    'user_id' => $user['user_id'] ?? null,
                    'scope' => $scope
                ]);
                
                Response::forbidden('auth.scope_required');
                return null;
            }
            
            $request['authz'] = $authz;
            return $next($request);
        };
    }
    
    /**
     * Check resource ownership
     * This middleware loads the resource and checks ownership
     * 
     * Usage: In controller after loading resource
     */
    public static function checkOwnership(mixed $resource, string $action = 'access'): bool
    {
        global $currentRequest;
        $user = $currentRequest['user'] ?? null;
        
        if (!$user) {
            return false;
        }
        
        $authz = new AuthorizationManager($user);
        return $authz->canAccess($resource, $action);
    }
    
    /**
     * Helper: Create authorization manager for current user
     */
    public static function createAuthzManager(array $request): AuthorizationManager
    {
        $user = $request['user'] ?? null;
        
        if (!$user) {
            throw new \Exception('No authenticated user');
        }
        
        return new AuthorizationManager($user);
    }
}
