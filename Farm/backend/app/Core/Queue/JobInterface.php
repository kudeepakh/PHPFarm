<?php

namespace PHPFrarm\Core\Queue;

/**
 * Job Interface
 * 
 * All queueable jobs must implement this interface.
 */
interface JobInterface
{
    /**
     * Execute the job
     * 
     * @return bool Success status
     */
    public function handle(): bool;

    /**
     * Get job name/type for logging
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * Get job payload for serialization
     * 
     * @return array
     */
    public function getPayload(): array;

    /**
     * Get maximum retry attempts
     * 
     * @return int
     */
    public function getMaxAttempts(): int;

    /**
     * Get retry delay in seconds
     * 
     * @return int
     */
    public function getRetryDelay(): int;
}
