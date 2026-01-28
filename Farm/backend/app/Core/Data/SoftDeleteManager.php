<?php

declare(strict_types=1);

namespace App\Core\Data;

/**
 * Soft Delete Manager
 * 
 * Manages soft delete operations with full audit trail,
 * cascading deletes, and recovery capabilities.
 * 
 * @package PHPFrarm
 * @module Data Standards (Module 13)
 */
class SoftDeleteManager
{
    /**
     * Entity cascade relationships
     * Maps entity types to their related entities for cascade operations
     */
    private array $cascadeRelationships = [];
    
    /**
     * Soft delete hooks
     */
    private array $beforeDeleteHooks = [];
    private array $afterDeleteHooks = [];
    private array $beforeRestoreHooks = [];
    private array $afterRestoreHooks = [];

    /**
     * Register cascade relationships for an entity
     */
    public function registerCascade(string $entityType, array $relationships): void
    {
        $this->cascadeRelationships[$entityType] = $relationships;
    }

    /**
     * Register before delete hook
     */
    public function beforeDelete(string $entityType, callable $callback): void
    {
        $this->beforeDeleteHooks[$entityType][] = $callback;
    }

    /**
     * Register after delete hook
     */
    public function afterDelete(string $entityType, callable $callback): void
    {
        $this->afterDeleteHooks[$entityType][] = $callback;
    }

    /**
     * Register before restore hook
     */
    public function beforeRestore(string $entityType, callable $callback): void
    {
        $this->beforeRestoreHooks[$entityType][] = $callback;
    }

    /**
     * Register after restore hook
     */
    public function afterRestore(string $entityType, callable $callback): void
    {
        $this->afterRestoreHooks[$entityType][] = $callback;
    }

    /**
     * Prepare soft delete data for an entity
     */
    public function prepareSoftDelete(
        array $entity,
        string $deletedBy,
        ?string $reason = null
    ): array {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        
        return array_merge($entity, [
            'deleted_at' => $now->format('Y-m-d H:i:s'),
            'deleted_by' => $deletedBy,
            'deletion_reason' => $reason,
            'is_deleted' => true
        ]);
    }

    /**
     * Prepare restore data for an entity
     */
    public function prepareRestore(
        array $entity,
        string $restoredBy,
        ?string $reason = null
    ): array {
        return array_merge($entity, [
            'deleted_at' => null,
            'deleted_by' => null,
            'deletion_reason' => null,
            'is_deleted' => false,
            'restored_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'restored_by' => $restoredBy,
            'restoration_reason' => $reason
        ]);
    }

    /**
     * Execute before delete hooks
     */
    public function executeBeforeDeleteHooks(string $entityType, array $entity): bool
    {
        $hooks = $this->beforeDeleteHooks[$entityType] ?? [];
        
        foreach ($hooks as $hook) {
            $result = $hook($entity);
            if ($result === false) {
                return false; // Abort delete
            }
        }
        
        return true;
    }

    /**
     * Execute after delete hooks
     */
    public function executeAfterDeleteHooks(string $entityType, array $entity): void
    {
        $hooks = $this->afterDeleteHooks[$entityType] ?? [];
        
        foreach ($hooks as $hook) {
            $hook($entity);
        }
    }

    /**
     * Execute before restore hooks
     */
    public function executeBeforeRestoreHooks(string $entityType, array $entity): bool
    {
        $hooks = $this->beforeRestoreHooks[$entityType] ?? [];
        
        foreach ($hooks as $hook) {
            $result = $hook($entity);
            if ($result === false) {
                return false; // Abort restore
            }
        }
        
        return true;
    }

    /**
     * Execute after restore hooks
     */
    public function executeAfterRestoreHooks(string $entityType, array $entity): void
    {
        $hooks = $this->afterRestoreHooks[$entityType] ?? [];
        
        foreach ($hooks as $hook) {
            $hook($entity);
        }
    }

    /**
     * Get cascade operations for an entity type
     */
    public function getCascadeOperations(string $entityType): array
    {
        return $this->cascadeRelationships[$entityType] ?? [];
    }

    /**
     * Build cascade delete plan
     */
    public function buildCascadeDeletePlan(
        string $entityType,
        string $entityId,
        callable $entityLoader
    ): array {
        $plan = [
            'root' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId
            ],
            'cascades' => []
        ];
        
        $relationships = $this->getCascadeOperations($entityType);
        
        foreach ($relationships as $relation) {
            $relatedType = $relation['entity_type'];
            $foreignKey = $relation['foreign_key'];
            $cascadeType = $relation['cascade'] ?? 'soft_delete'; // soft_delete, nullify, restrict
            
            // Load related entities
            $relatedEntities = $entityLoader($relatedType, $foreignKey, $entityId);
            
            foreach ($relatedEntities as $related) {
                $plan['cascades'][] = [
                    'entity_type' => $relatedType,
                    'entity_id' => $related['id'],
                    'cascade_type' => $cascadeType,
                    'foreign_key' => $foreignKey
                ];
                
                // Recursive cascade check
                $subCascades = $this->buildCascadeDeletePlan($relatedType, $related['id'], $entityLoader);
                $plan['cascades'] = array_merge($plan['cascades'], $subCascades['cascades']);
            }
        }
        
        return $plan;
    }

    /**
     * Validate if entity can be deleted (check restrict constraints)
     */
    public function canDelete(
        string $entityType,
        string $entityId,
        callable $entityLoader
    ): array {
        $result = [
            'can_delete' => true,
            'restrictions' => []
        ];
        
        $relationships = $this->getCascadeOperations($entityType);
        
        foreach ($relationships as $relation) {
            if (($relation['cascade'] ?? 'soft_delete') !== 'restrict') {
                continue;
            }
            
            $relatedType = $relation['entity_type'];
            $foreignKey = $relation['foreign_key'];
            
            $relatedEntities = $entityLoader($relatedType, $foreignKey, $entityId);
            
            if (!empty($relatedEntities)) {
                $result['can_delete'] = false;
                $result['restrictions'][] = [
                    'entity_type' => $relatedType,
                    'count' => count($relatedEntities),
                    'message' => "Cannot delete: {$relatedType} records exist with {$foreignKey} = {$entityId}"
                ];
            }
        }
        
        return $result;
    }

    /**
     * Generate WHERE clause for filtering deleted/non-deleted records
     */
    public function getFilterClause(bool $includeDeleted = false, bool $onlyDeleted = false): string
    {
        if ($onlyDeleted) {
            return "deleted_at IS NOT NULL";
        }
        
        if ($includeDeleted) {
            return "1=1"; // No filter
        }
        
        return "deleted_at IS NULL";
    }

    /**
     * Get soft delete statistics for an entity type
     */
    public function getDeleteStatistics(callable $statsLoader): array
    {
        return $statsLoader();
    }

    /**
     * Build audit entry for soft delete operation
     */
    public function buildAuditEntry(
        string $operation, // 'delete' or 'restore'
        string $entityType,
        string $entityId,
        array $entityData,
        string $performedBy,
        ?string $reason = null
    ): array {
        return [
            'operation' => $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_snapshot' => $operation === 'delete' ? $entityData : null,
            'performed_by' => $performedBy,
            'reason' => $reason,
            'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'metadata' => [
                'cascade_triggered' => !empty($this->getCascadeOperations($entityType))
            ]
        ];
    }

    /**
     * Get default cascade configuration
     */
    public static function getDefaultCascadeConfiguration(): array
    {
        return [
            'user' => [
                [
                    'entity_type' => 'user_sessions',
                    'foreign_key' => 'user_id',
                    'cascade' => 'soft_delete'
                ],
                [
                    'entity_type' => 'user_tokens',
                    'foreign_key' => 'user_id',
                    'cascade' => 'soft_delete'
                ],
                [
                    'entity_type' => 'audit_logs',
                    'foreign_key' => 'user_id',
                    'cascade' => 'nullify' // Keep audit logs but remove user reference
                ],
                [
                    'entity_type' => 'orders',
                    'foreign_key' => 'user_id',
                    'cascade' => 'restrict' // Cannot delete user with orders
                ]
            ],
            'organization' => [
                [
                    'entity_type' => 'teams',
                    'foreign_key' => 'organization_id',
                    'cascade' => 'soft_delete'
                ],
                [
                    'entity_type' => 'projects',
                    'foreign_key' => 'organization_id',
                    'cascade' => 'soft_delete'
                ]
            ]
        ];
    }
}
