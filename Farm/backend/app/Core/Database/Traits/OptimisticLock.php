<?php

namespace PHPFrarm\Core\Database\Traits;

use App\Core\Exceptions\OptimisticLockException;

/**
 * OptimisticLock Trait
 * 
 * Provides optimistic locking functionality for entities.
 * Automatically manages version column to prevent lost updates.
 * 
 * Usage:
 * 1. Add 'version' column (INT, default 1) to your table
 * 2. Use this trait in your entity class
 * 3. Version will auto-increment on updates
 * 4. Throws OptimisticLockException on conflict
 * 
 * Example:
 * ```php
 * class Product
 * {
 *     use OptimisticLock;
 *     
 *     protected string $table = 'products';
 *     protected bool $useOptimisticLock = true;
 * }
 * ```
 */
trait OptimisticLock
{
    /**
     * Version column name
     */
    protected string $versionColumn = 'version';

    /**
     * Enable/disable optimistic locking
     */
    protected bool $useOptimisticLock = true;

    /**
     * Current version loaded from database
     */
    protected ?int $loadedVersion = null;

    /**
     * Get version column name
     */
    public function getVersionColumn(): string
    {
        return $this->versionColumn;
    }

    /**
     * Check if optimistic locking is enabled
     */
    public function usesOptimisticLocking(): bool
    {
        return $this->useOptimisticLock;
    }

    /**
     * Get current version
     */
    public function getVersion(): ?int
    {
        return $this->loadedVersion;
    }

    /**
     * Set version (used when loading from DB)
     */
    public function setVersion(int $version): void
    {
        $this->loadedVersion = $version;
    }

    /**
     * Increment version
     */
    public function incrementVersion(): int
    {
        if ($this->loadedVersion === null) {
            $this->loadedVersion = 1;
        } else {
            $this->loadedVersion++;
        }
        return $this->loadedVersion;
    }

    /**
     * Validate version before update
     * 
     * @throws OptimisticLockException
     */
    public function validateVersion(int $currentDbVersion): void
    {
        if (!$this->useOptimisticLock) {
            return;
        }

        if ($this->loadedVersion === null) {
            throw new \LogicException('Cannot validate version: entity version not loaded');
        }

        if ($this->loadedVersion !== $currentDbVersion) {
            throw OptimisticLockException::fromDatabaseResult(
                $this->getEntityType(),
                $this->getEntityId(),
                $this->loadedVersion,
                $currentDbVersion
            );
        }
    }

    /**
     * Get entity type name (override in child class if needed)
     */
    protected function getEntityType(): string
    {
        return class_basename(static::class);
    }

    /**
     * Get entity ID (override in child class if needed)
     */
    protected function getEntityId()
    {
        return $this->id ?? null;
    }

    /**
     * Get version-aware update query
     * 
     * Generates SQL with version check:
     * UPDATE table SET col1=?, col2=?, version=version+1 
     * WHERE id=? AND version=?
     */
    public function getVersionedUpdateQuery(string $table, array $columns, $id): array
    {
        if (!$this->useOptimisticLock) {
            // Regular update without version check
            $setClauses = array_map(fn($col) => "{$col}=?", $columns);
            $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE id=?";
            return ['sql' => $sql, 'requires_version' => false];
        }

        // Version-aware update
        $setClauses = array_map(fn($col) => "{$col}=?", $columns);
        $setClauses[] = "{$this->versionColumn}={$this->versionColumn}+1";
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . 
               " WHERE id=? AND {$this->versionColumn}=?";

        return ['sql' => $sql, 'requires_version' => true];
    }

    /**
     * Check update result and throw exception if version conflict
     * 
     * @param int $affectedRows Number of rows affected by update
     * @throws OptimisticLockException
     */
    public function checkUpdateResult(int $affectedRows): void
    {
        if (!$this->useOptimisticLock) {
            return;
        }

        if ($affectedRows === 0) {
            // Either record doesn't exist or version mismatch
            // Need to query DB to determine which case
            throw OptimisticLockException::fromDatabaseResult(
                $this->getEntityType(),
                $this->getEntityId(),
                $this->loadedVersion ?? 0,
                $this->loadedVersion ? $this->loadedVersion + 1 : 1
            );
        }

        // Update was successful, increment local version
        $this->incrementVersion();
    }

    /**
     * Generate ETag for HTTP caching
     */
    public function generateETag(): string
    {
        $version = $this->loadedVersion ?? 1;
        $id = $this->getEntityId();
        return sprintf('W/"%s-%d"', $id, $version);
    }

    /**
     * Validate If-Match header
     * 
     * @throws OptimisticLockException
     */
    public function validateIfMatch(?string $ifMatchHeader): void
    {
        if (!$this->useOptimisticLock || $ifMatchHeader === null) {
            return;
        }

        $currentETag = $this->generateETag();
        
        // Remove quotes if present
        $ifMatchHeader = trim($ifMatchHeader, '"');
        $currentETag = trim($currentETag, '"');

        if ($ifMatchHeader !== $currentETag && $ifMatchHeader !== '*') {
            throw OptimisticLockException::fromDatabaseResult(
                $this->getEntityType(),
                $this->getEntityId(),
                $this->extractVersionFromETag($ifMatchHeader),
                $this->loadedVersion ?? 1
            );
        }
    }

    /**
     * Extract version from ETag
     */
    private function extractVersionFromETag(string $etag): int
    {
        // ETag format: W/"id-version" or "id-version"
        $etag = str_replace(['W/', '"'], '', $etag);
        $parts = explode('-', $etag);
        return (int) end($parts);
    }
}
