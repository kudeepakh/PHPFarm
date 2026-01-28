<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * TransientException
 * 
 * Thrown for temporary/transient failures that are expected to resolve (503 Service Unavailable).
 * SHOULD be retried as the condition is temporary.
 * 
 * Examples:
 * - Database connection pool exhausted (wait and retry)
 * - External service temporarily unavailable
 * - Rate limit exceeded (wait and retry)
 * - Resource locked (wait and retry)
 */
class TransientException extends Exception
{
    protected bool $retryable;
    protected ?int $retryAfterSeconds;

    public function __construct(
        string $message = 'Temporary failure, please retry',
        bool $retryable = true,
        ?int $retryAfterSeconds = null,
        int $code = 503
    ) {
        $this->retryable = $retryable;
        $this->retryAfterSeconds = $retryAfterSeconds;
        parent::__construct($message, $code);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function getStatusCode(): int
    {
        return 503;
    }
}
