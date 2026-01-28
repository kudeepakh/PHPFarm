<?php

declare(strict_types=1);

namespace App\Core\Data;

/**
 * Optimistic Lock Manager
 * 
 * Manages optimistic locking for concurrent update handling.
 * Prevents lost updates in concurrent edit scenarios.
 * 
 * @package PHPFrarm
 * @module Data Standards (Module 13)
 */
class OptimisticLockManager
{
    /**
     * Version field name (configurable per entity)
     */
    private string $defaultVersionField = 'version';
    
    /**
     * Entity version field mapping
     */
    private array $versionFieldMap = [];
    
    /**
     * Conflict handlers per entity type
     */
    private array $conflictHandlers = [];
    
    /**
     * Lock mode configuration
     */
    private array $lockModes = [];

    /**
     * Set version field for an entity type
     */
    public function setVersionField(string $entityType, string $fieldName): void
    {
        $this->versionFieldMap[$entityType] = $fieldName;
    }

    /**
     * Get version field for an entity type
     */
    public function getVersionField(string $entityType): string
    {
        return $this->versionFieldMap[$entityType] ?? $this->defaultVersionField;
    }

    /**
     * Register a conflict handler
     */
    public function registerConflictHandler(string $entityType, callable $handler): void
    {
        $this->conflictHandlers[$entityType] = $handler;
    }

    /**
     * Set lock mode for entity type
     */
    public function setLockMode(string $entityType, string $mode): void
    {
        $this->lockModes[$entityType] = $mode;
    }

    /**
     * Get the next version number
     */
    public function getNextVersion(int $currentVersion): int
    {
        return $currentVersion + 1;
    }

    /**
     * Get next version using timestamp-based versioning
     */
    public function getTimestampVersion(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * Get next version using hash-based versioning
     */
    public function getHashVersion(array $data): string
    {
        ksort($data);
        return hash('xxh128', json_encode($data));
    }

    /**
     * Check if versions match (no conflict)
     */
    public function checkVersion(
        mixed $expectedVersion,
        mixed $currentVersion
    ): bool {
        return $expectedVersion === $currentVersion;
    }

    /**
     * Prepare update data with version increment
     */
    public function prepareUpdate(
        string $entityType,
        array $data,
        int $expectedVersion
    ): array {
        $versionField = $this->getVersionField($entityType);
        
        return array_merge($data, [
            $versionField => $this->getNextVersion($expectedVersion),
            '_expected_version' => $expectedVersion
        ]);
    }

    /**
     * Generate SQL condition for version check
     */
    public function getVersionCondition(
        string $entityType,
        int $expectedVersion
    ): array {
        $versionField = $this->getVersionField($entityType);
        
        return [
            'sql' => "{$versionField} = :expected_version",
            'params' => ['expected_version' => $expectedVersion]
        ];
    }

    /**
     * Handle version conflict
     */
    public function handleConflict(
        string $entityType,
        array $attemptedData,
        array $currentData
    ): array {
        $handler = $this->conflictHandlers[$entityType] ?? null;
        
        if ($handler) {
            return $handler($attemptedData, $currentData);
        }
        
        // Default conflict response
        return [
            'resolved' => false,
            'strategy' => 'reject',
            'error' => [
                'code' => 'VERSION_CONFLICT',
                'message' => 'The resource was modified by another request. Please refresh and try again.',
                'details' => [
                    'your_version' => $attemptedData['_expected_version'] ?? null,
                    'current_version' => $currentData[$this->getVersionField($entityType)] ?? null,
                    'conflict_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z')
                ]
            ]
        ];
    }

    /**
     * Merge conflict resolution - attempt automatic merge
     */
    public function attemptAutoMerge(
        array $baseData,
        array $yourChanges,
        array $theirChanges
    ): array {
        $merged = $baseData;
        $conflicts = [];
        
        // Get all changed fields from both versions
        $yourFields = $this->getChangedFields($baseData, $yourChanges);
        $theirFields = $this->getChangedFields($baseData, $theirChanges);
        
        // Apply non-conflicting changes from their version
        foreach ($theirFields as $field) {
            if (!in_array($field, $yourFields)) {
                $merged[$field] = $theirChanges[$field];
            }
        }
        
        // Apply your changes (may overwrite theirs for conflicting fields)
        foreach ($yourFields as $field) {
            if (in_array($field, $theirFields)) {
                // Conflict!
                $conflicts[] = [
                    'field' => $field,
                    'base_value' => $baseData[$field] ?? null,
                    'your_value' => $yourChanges[$field],
                    'their_value' => $theirChanges[$field]
                ];
            }
            $merged[$field] = $yourChanges[$field];
        }
        
        return [
            'success' => empty($conflicts),
            'merged' => $merged,
            'conflicts' => $conflicts,
            'requires_manual_resolution' => !empty($conflicts)
        ];
    }

    /**
     * Get fields that changed between two data arrays
     */
    private function getChangedFields(array $base, array $updated): array
    {
        $changed = [];
        
        foreach ($updated as $field => $value) {
            if ($field === '_expected_version') {
                continue;
            }
            
            if (!array_key_exists($field, $base) || $base[$field] !== $value) {
                $changed[] = $field;
            }
        }
        
        return $changed;
    }

    /**
     * Create optimistic lock exception response
     */
    public function createConflictResponse(
        string $entityType,
        string $entityId,
        int $expectedVersion,
        int $currentVersion
    ): array {
        return [
            'status' => 409,
            'error' => [
                'code' => 'OPTIMISTIC_LOCK_CONFLICT',
                'message' => 'The resource has been modified by another process',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'expected_version' => $expectedVersion,
                'current_version' => $currentVersion,
                'suggested_action' => 'Fetch the latest version and retry your changes'
            ]
        ];
    }

    /**
     * Initialize entity with version
     */
    public function initializeVersion(string $entityType, array $data): array
    {
        $versionField = $this->getVersionField($entityType);
        
        if (!isset($data[$versionField])) {
            $data[$versionField] = 1;
        }
        
        return $data;
    }

    /**
     * Check if entity supports optimistic locking
     */
    public function supportsLocking(string $entityType): bool
    {
        return isset($this->versionFieldMap[$entityType]) || 
               ($this->lockModes[$entityType] ?? null) !== 'none';
    }

    /**
     * Get lock mode for entity
     */
    public function getLockMode(string $entityType): string
    {
        return $this->lockModes[$entityType] ?? 'version'; // version, timestamp, hash, none
    }

    /**
     * Build ETag from version
     */
    public function buildETag(string $entityType, string $entityId, mixed $version): string
    {
        return sprintf('"%s-%s-%s"', $entityType, $entityId, $version);
    }

    /**
     * Parse ETag to extract version
     */
    public function parseETag(string $etag): ?array
    {
        $etag = trim($etag, '"');
        $parts = explode('-', $etag);
        
        if (count($parts) < 3) {
            return null;
        }
        
        return [
            'entity_type' => $parts[0],
            'entity_id' => $parts[1],
            'version' => $parts[2]
        ];
    }

    /**
     * Validate If-Match header for optimistic locking
     */
    public function validateIfMatch(
        string $ifMatchHeader,
        string $entityType,
        string $entityId,
        mixed $currentVersion
    ): bool {
        $expectedETag = $this->buildETag($entityType, $entityId, $currentVersion);
        
        // Handle multiple ETags in If-Match
        $etags = array_map('trim', explode(',', $ifMatchHeader));
        
        foreach ($etags as $etag) {
            if ($etag === '*' || $etag === $expectedETag) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get retry advice for conflicts
     */
    public function getRetryAdvice(int $conflictCount): array
    {
        $backoffMs = min(1000 * pow(2, $conflictCount - 1), 30000);
        
        return [
            'retry_after_ms' => $backoffMs,
            'retry_after_header' => ceil($backoffMs / 1000),
            'advice' => $conflictCount >= 5 
                ? 'High contention detected. Consider using a different approach or queuing the operation.'
                : 'Exponential backoff recommended before retry.'
        ];
    }
}
