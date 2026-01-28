<?php

namespace PHPFrarm\Core\Queue;

use PHPFrarm\Core\Logger;

/**
 * Abstract Job Base Class
 * 
 * Provides common functionality for all jobs.
 */
abstract class Job implements JobInterface
{
    protected array $payload = [];
    protected int $maxAttempts = 3;
    protected int $retryDelay = 60; // seconds
    protected int $attempts = 0;

    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job - must be implemented by child classes
     */
    abstract public function handle(): bool;

    /**
     * Get job name based on class name
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * Get job payload
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get max retry attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get retry delay
     */
    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    /**
     * Set current attempt count
     */
    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    /**
     * Get current attempt count
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Serialize job for queue storage
     */
    public function serialize(): string
    {
        return json_encode([
            'class' => static::class,
            'payload' => $this->payload,
            'max_attempts' => $this->maxAttempts,
            'retry_delay' => $this->retryDelay,
            'attempts' => $this->attempts,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Deserialize job from queue storage
     */
    public static function deserialize(string $data): ?JobInterface
    {
        $decoded = json_decode($data, true);
        if (!$decoded || !isset($decoded['class'])) {
            Logger::error('Failed to deserialize job', ['data' => $data]);
            return null;
        }

        $class = $decoded['class'];
        if (!class_exists($class)) {
            Logger::error('Job class not found', ['class' => $class]);
            return null;
        }

        $job = new $class($decoded['payload'] ?? []);
        $job->setAttempts($decoded['attempts'] ?? 0);

        return $job;
    }

    /**
     * Called when job fails after all retries
     */
    public function failed(\Exception $exception): void
    {
        Logger::error('Job failed permanently', [
            'job' => $this->getName(),
            'payload' => $this->payload,
            'attempts' => $this->attempts,
            'error' => $exception->getMessage(),
        ]);
    }
}
