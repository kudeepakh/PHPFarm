<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * TimeoutException
 * 
 * Thrown when operation exceeds configured timeout (408 Request Timeout / 504 Gateway Timeout).
 * SHOULD be retried as timeout may be transient (network congestion, temporary slowdown).
 */
class TimeoutException extends Exception
{
    protected int $timeoutMs;

    public function __construct(int $timeoutMs = 0, string $message = 'Operation timed out', int $code = 504)
    {
        $this->timeoutMs = $timeoutMs;
        parent::__construct($message, $code);
    }

    public function getTimeoutMs(): int
    {
        return $this->timeoutMs;
    }

    public function getStatusCode(): int
    {
        return 504;
    }
}
