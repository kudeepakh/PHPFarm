<?php

namespace PHPFrarm\Core\Authorization;

/**
 * Role Definition
 * 
 * A role is a collection of permissions that can be assigned to users
 * Roles provide a convenient way to group permissions
 * 
 * Examples:
 * - admin: Full system access
 * - editor: Content management permissions
 * - viewer: Read-only permissions
 */
class Role
{
    private string $name;
    private ?string $description;
    private array $permissions;
    private array $scopes;
    private int $priority;
    
    public function __construct(
        string $name,
        array $permissions = [],
        ?string $description = null,
        array $scopes = [],
        int $priority = 0
    ) {
        $this->name = $name;
        $this->permissions = $permissions;
        $this->description = $description;
        $this->scopes = $scopes;
        $this->priority = $priority;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getPermissions(): array
    {
        return $this->permissions;
    }
    
    public function getScopes(): array
    {
        return $this->scopes;
    }
    
    public function getPriority(): int
    {
        return $this->priority;
    }
    
    /**
     * Add permission to role
     */
    public function addPermission(string $permission): self
    {
        if (!in_array($permission, $this->permissions)) {
            $this->permissions[] = $permission;
        }
        return $this;
    }
    
    /**
     * Remove permission from role
     */
    public function removePermission(string $permission): self
    {
        $this->permissions = array_filter(
            $this->permissions,
            fn($p) => $p !== $permission
        );
        return $this;
    }
    
    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        foreach ($this->permissions as $rolePermission) {
            // Exact match
            if ($rolePermission === $permission) {
                return true;
            }
            
            // Wildcard match (e.g., "users:*" grants "users:create")
            if (str_contains($rolePermission, '*')) {
                $pattern = str_replace('*', '.*', $rolePermission);
                if (preg_match('#^' . $pattern . '$#', $permission)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Predefined system roles
     */
    public static function system(): array
    {
        return [
            'superadmin' => new self(
                'superadmin',
                ['*:*'],
                'Super Administrator - Full system access',
                [],
                1000
            ),
            'admin' => new self(
                'admin',
                ['users:*', 'roles:*', 'permissions:*', 'settings:*'],
                'Administrator - System management',
                [],
                900
            ),
            'editor' => new self(
                'editor',
                ['posts:*', 'pages:*', 'media:*'],
                'Editor - Content management',
                [],
                500
            ),
            'author' => new self(
                'author',
                ['posts:create', 'posts:update', 'posts:read', 'media:create'],
                'Author - Can create and edit own content',
                [],
                300
            ),
            'viewer' => new self(
                'viewer',
                ['posts:read', 'pages:read'],
                'Viewer - Read-only access',
                [],
                100
            ),
        ];
    }
}
