<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * NotFoundException
 * 
 * Thrown when requested resource does not exist (404 Not Found).
 * Should NOT be retried as missing resources won't appear with retries.
 */
class NotFoundException extends Exception
{
    public function __construct(string $message = 'Resource not found', int $code = 404)
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
