<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * UnauthorizedException
 * 
 * Thrown when authentication is required but missing or invalid (401 Unauthorized).
 * Should NOT be retried as auth issues won't resolve with retries.
 */
class UnauthorizedException extends Exception
{
    public function __construct(string $message = 'Unauthorized', int $code = 401)
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return 401;
    }
}
