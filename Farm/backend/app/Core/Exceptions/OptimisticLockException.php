<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * OptimisticLockException
 * 
 * Thrown when a version conflict is detected during concurrent updates.
 * Indicates that the resource was modified by another process between read and write.
 * 
 * HTTP Status: 409 Conflict
 * 
 * Client should:
 * 1. Refetch the resource with current version
 * 2. Reapply their changes
 * 3. Retry the update with new version
 */
class OptimisticLockException extends Exception
{
    protected string $entityType;
    protected $entityId;
    protected int $expectedVersion;
    protected int $currentVersion;
    protected bool $retryable;

    /**
     * @param string $entityType Type of entity (e.g., 'Product', 'Order')
     * @param mixed $entityId ID of the entity
     * @param int $expectedVersion Version that was expected
     * @param int $currentVersion Current version in database
     * @param string $message Custom error message
     */
    public function __construct(
        string $entityType,
        $entityId,
        int $expectedVersion,
        int $currentVersion,
        string $message = 'Resource was modified by another process. Please refresh and try again.',
        bool $retryable = true
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->expectedVersion = $expectedVersion;
        $this->currentVersion = $currentVersion;
        $this->retryable = $retryable;

        parent::__construct($message, 409);
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId()
    {
        return $this->entityId;
    }

    public function getExpectedVersion(): int
    {
        return $this->expectedVersion;
    }

    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * Get detailed conflict information
     */
    public function getConflictDetails(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'expected_version' => $this->expectedVersion,
            'current_version' => $this->currentVersion,
            'version_difference' => $this->currentVersion - $this->expectedVersion,
            'retryable' => $this->retryable,
        ];
    }

    /**
     * Create from database result
     */
    public static function fromDatabaseResult(
        string $entityType,
        $entityId,
        int $expectedVersion,
        int $currentVersion
    ): self {
        return new self(
            $entityType,
            $entityId,
            $expectedVersion,
            $currentVersion,
            "Optimistic lock conflict on {$entityType} #{$entityId}. Expected version {$expectedVersion}, but current version is {$currentVersion}."
        );
    }
}
