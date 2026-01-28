<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * ForbiddenException
 * 
 * Thrown when user lacks permission to access resource (403 Forbidden).
 * Should NOT be retried as permission issues won't resolve with retries.
 */
class ForbiddenException extends Exception
{
    public function __construct(string $message = 'Forbidden', int $code = 403)
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
