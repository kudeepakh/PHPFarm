<?php

namespace PHPFrarm\Core\Authorization;

/**
 * Permission Definition
 * 
 * Represents a specific action that can be performed in the system
 * Permissions are fine-grained and composable
 * 
 * Examples:
 * - users:read, users:create, users:update, users:delete
 * - posts:publish, posts:unpublish
 * - reports:generate, reports:export
 * 
 * Format: {resource}:{action}
 */
class Permission
{
    private string $name;
    private string $resource;
    private string $action;
    private ?string $description;
    private array $scopes;
    
    public function __construct(
        string $name,
        ?string $description = null,
        array $scopes = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->scopes = $scopes;
        
        // Parse resource and action from name (e.g., "users:create")
        if (str_contains($name, ':')) {
            [$this->resource, $this->action] = explode(':', $name, 2);
        } else {
            $this->resource = $name;
            $this->action = 'all';
        }
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getResource(): string
    {
        return $this->resource;
    }
    
    public function getAction(): string
    {
        return $this->action;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getScopes(): array
    {
        return $this->scopes;
    }
    
    /**
     * Check if this permission matches another permission or wildcard
     */
    public function matches(string $permission): bool
    {
        // Exact match
        if ($this->name === $permission) {
            return true;
        }
        
        // Wildcard match (e.g., "users:*" matches "users:create")
        if (str_contains($permission, '*')) {
            $pattern = str_replace('*', '.*', $permission);
            return (bool)preg_match('#^' . $pattern . '$#', $this->name);
        }
        
        return false;
    }
    
    /**
     * Create permission from string
     */
    public static function fromString(string $permission): self
    {
        return new self($permission);
    }
    
    /**
     * Common permission definitions
     */
    public static function define(string $resource): array
    {
        return [
            'read' => new self("{$resource}:read", "Read {$resource}"),
            'create' => new self("{$resource}:create", "Create {$resource}"),
            'update' => new self("{$resource}:update", "Update {$resource}"),
            'delete' => new self("{$resource}:delete", "Delete {$resource}"),
            'all' => new self("{$resource}:*", "All {$resource} actions"),
        ];
    }
}
