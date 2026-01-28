<?php

declare(strict_types=1);

namespace PHPFrarm\Modules\Permission\Services;

use PHPFrarm\Modules\Permission\DAO\PermissionDAO;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Attributes\Route;
use Exception;
use ReflectionClass;
use ReflectionMethod;

class PermissionService
{
    private PermissionDAO $permissionDAO;

    public function __construct()
    {
        $this->permissionDAO = new PermissionDAO();
    }

    /**
     * Normalize single permission (add 'id' from 'permission_id')
     */
    private function normalizePermission(array $permission): array
    {
        if (isset($permission['permission_id'])) {
            $permission['id'] = $permission['permission_id'];
        }
        return $permission;
    }

    /**
     * Normalize array of permissions
     */
    private function normalizePermissions(array $permissions): array
    {
        return array_map([$this, 'normalizePermission'], $permissions);
    }

    /**
     * List permissions with pagination
     */
    public function listPermissions(int $page = 1, int $perPage = 100): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $permissions = $this->permissionDAO->listPermissions($perPage, $offset);
            $total = $this->permissionDAO->countPermissions();

            return [
                'permissions' => $this->normalizePermissions($permissions),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to list permissions', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to retrieve permissions');
        }
    }

    /**
     * Get all permissions without pagination
     */
    public function getAllPermissions(): array
    {
        try {
            return $this->normalizePermissions($this->permissionDAO->getAllPermissions());
        } catch (Exception $e) {
            Logger::error('Failed to get all permissions', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to retrieve permissions');
        }
    }

    /**
     * Auto-discover permissions from route attributes
     */
    public function discoverPermissions(): array
    {
        try {
            $discovered = [];
            $created = 0;
            $updated = 0;
            $errors = [];

            // Scan all controller files
            $controllersPath = dirname(__DIR__, 3) . '/modules';
            $controllers = $this->findControllers($controllersPath);

            foreach ($controllers as $controllerClass) {
                try {
                    $permissions = $this->extractPermissionsFromController($controllerClass);
                    
                    foreach ($permissions as $permission) {
                        $discovered[] = $permission;
                        
                        // Check if permission exists
                        $existing = $this->permissionDAO->getPermissionByName(
                            $permission['resource'],
                            $permission['action']
                        );

                        // Upsert permission
                        $success = $this->permissionDAO->upsertPermission([
                            'id' => $existing['id'] ?? null,
                            'resource' => $permission['resource'],
                            'action' => $permission['action'],
                            'description' => $permission['description']
                        ]);

                        if ($success) {
                            if ($existing) {
                                $updated++;
                            } else {
                                $created++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'controller' => $controllerClass,
                        'error' => $e->getMessage()
                    ];
                    Logger::warning('Failed to discover permissions from controller', [
                        'controller' => $controllerClass,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Log audit trail
            Logger::audit('permissions_discovered', [
                'total_discovered' => count($discovered),
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors)
            ]);

            return [
                'success' => true,
                'message' => 'Permissions discovered successfully',
                'stats' => [
                    'discovered' => count($discovered),
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => count($errors)
                ],
                'permissions' => $discovered,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            Logger::error('Failed to discover permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to discover permissions: ' . $e->getMessage());
        }
    }

    /**
     * Find all controller classes
     */
    private function findControllers(string $path): array
    {
        $controllers = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();
                
                // Only scan Controller files
                if (strpos($filePath, 'Controllers') !== false) {
                    $className = $this->getClassNameFromFile($filePath);
                    if ($className && class_exists($className)) {
                        $controllers[] = $className;
                    }
                }
            }
        }

        return $controllers;
    }

    /**
     * Get class name from file
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        
        // Extract namespace
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? '';

        // Extract class name
        preg_match('/class\s+(\w+)/', $content, $classMatches);
        $className = $classMatches[1] ?? '';

        if ($namespace && $className) {
            return $namespace . '\\' . $className;
        }

        return null;
    }

    /**
     * Extract permissions from controller using Route attributes
     */
    private function extractPermissionsFromController(string $controllerClass): array
    {
        $permissions = [];
        
        try {
            $reflection = new ReflectionClass($controllerClass);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes();
                
                foreach ($attributes as $attribute) {
                    $attributeName = $attribute->getName();
                    
                    // Check if it's a Route attribute
                    if ($attributeName === Route::class || 
                        $attributeName === 'PHPFrarm\\Core\\Attributes\\Route' ||
                        str_ends_with($attributeName, '\\Route')) {
                        
                        $args = $attribute->getArguments();
                        
                        // Extract permission from middleware
                        if (isset($args['middleware']) && is_array($args['middleware'])) {
                            foreach ($args['middleware'] as $middleware) {
                                if (str_starts_with($middleware, 'permission:')) {
                                    $permissionName = substr($middleware, 11);
                                    $parts = explode(':', $permissionName);
                                    
                                    if (count($parts) === 2) {
                                        $permissions[] = [
                                            'resource' => $parts[0],
                                            'action' => $parts[1],
                                            'description' => $this->generateDescription(
                                                $parts[0],
                                                $parts[1],
                                                $args['path'] ?? '',
                                                $args['method'] ?? 'GET'
                                            )
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Logger::warning('Failed to extract permissions from controller', [
                'controller' => $controllerClass,
                'error' => $e->getMessage()
            ]);
        }

        return array_unique($permissions, SORT_REGULAR);
    }

    /**
     * Generate permission description
     */
    private function generateDescription(string $resource, string $action, string $path, string $method): string
    {
        $actionMap = [
            'read' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'list' => 'List',
            'import' => 'Import',
            'export' => 'Export',
            'manage' => 'Manage'
        ];

        $actionText = $actionMap[$action] ?? ucfirst($action);
        $resourceText = ucfirst(str_replace(['-', '_'], ' ', $resource));

        return "{$actionText} {$resourceText} - {$method} {$path}";
    }
}
