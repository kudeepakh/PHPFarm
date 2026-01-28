<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * InsufficientFundsException
 * 
 * Thrown when account has insufficient funds for transaction (402 Payment Required).
 * Should NOT be retried as balance issues won't resolve with retries.
 * 
 * This is a specific business rule violation for payment scenarios.
 */
class InsufficientFundsException extends Exception
{
    protected float $required;
    protected float $available;

    public function __construct(
        float $required = 0,
        float $available = 0,
        string $message = 'Insufficient funds',
        int $code = 402
    ) {
        $this->required = $required;
        $this->available = $available;
        parent::__construct($message, $code);
    }

    public function getRequired(): float
    {
        return $this->required;
    }

    public function getAvailable(): float
    {
        return $this->available;
    }

    public function getShortfall(): float
    {
        return $this->required - $this->available;
    }

    public function getStatusCode(): int
    {
        return 402;
    }
}
