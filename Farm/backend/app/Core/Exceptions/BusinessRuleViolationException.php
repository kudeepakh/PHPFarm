<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * BusinessRuleViolationException
 * 
 * Thrown when business logic rules are violated (422 Unprocessable Entity).
 * Should NOT be retried as business rule violations won't resolve with retries.
 * 
 * Examples:
 * - Cannot cancel already shipped order
 * - Cannot withdraw more than account balance
 * - Cannot apply discount to non-eligible items
 */
class BusinessRuleViolationException extends Exception
{
    protected string $rule;

    public function __construct(string $message = 'Business rule violation', string $rule = '', int $code = 422)
    {
        $this->rule = $rule;
        parent::__construct($message, $code);
    }

    public function getRule(): string
    {
        return $this->rule;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
