<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * ValidationException
 * 
 * Thrown when request validation fails (400 Bad Request).
 * Should NOT be retried as validation errors won't resolve with retries.
 */
class ValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors = [], string $message = 'Validation failed', int $code = 400)
    {
        $this->errors = $errors;
        parent::__construct($message, $code);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
