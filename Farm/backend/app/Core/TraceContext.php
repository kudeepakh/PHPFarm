<?php

namespace PHPFrarm\Core;

/**
 * TraceContext - Manages correlation, transaction, and request IDs
 * 
 * MANDATORY: All APIs must generate and propagate these IDs
 */
class TraceContext
{
    private static ?string $correlationId = null;
    private static ?string $transactionId = null;
    private static ?string $requestId = null;
    private const CROCKFORD_BASE32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    /**
     * Initialize trace context from headers or generate new IDs
     */
    public static function initialize(): void
    {
        $incomingCorrelationId = $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
        $incomingTransactionId = $_SERVER['HTTP_X_TRANSACTION_ID'] ?? null;

        // Get or generate Correlation ID
        self::$correlationId = self::isValidUlid($incomingCorrelationId)
            ? strtoupper($incomingCorrelationId)
            : self::generateULID();

        // Get or generate Transaction ID
        self::$transactionId = self::isValidUlid($incomingTransactionId)
            ? strtoupper($incomingTransactionId)
            : self::generateULID();

        // Always generate new Request ID
        self::$requestId = self::generateULID();
    }

    /**
     * Get Correlation ID
     */
    public static function getCorrelationId(): string
    {
        if (self::$correlationId === null) {
            self::initialize();
        }
        return self::$correlationId;
    }

    /**
     * Get Transaction ID
     */
    public static function getTransactionId(): string
    {
        if (self::$transactionId === null) {
            self::initialize();
        }
        return self::$transactionId;
    }

    /**
     * Get Request ID
     */
    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::initialize();
        }
        return self::$requestId;
    }

    /**
     * Set Correlation ID (used by shims/tests)
     */
    public static function setCorrelationId(string $value): void
    {
        if (self::isValidUlid($value)) {
            self::$correlationId = strtoupper($value);
        }
    }

    /**
     * Set Transaction ID (used by shims/tests)
     */
    public static function setTransactionId(string $value): void
    {
        if (self::isValidUlid($value)) {
            self::$transactionId = strtoupper($value);
        }
    }

    /**
     * Set Request ID (used by shims/tests)
     */
    public static function setRequestId(string $value): void
    {
        if (self::isValidUlid($value)) {
            self::$requestId = strtoupper($value);
        }
    }

    /**
     * Set response headers with trace IDs
     */
    public static function setResponseHeaders(): void
    {
        header('X-Correlation-Id: ' . self::getCorrelationId());
        header('X-Transaction-Id: ' . self::getTransactionId());
        header('X-Request-Id: ' . self::getRequestId());
    }

    /**
     * Generate ULID (Universally Unique Lexicographically Sortable Identifier)
     */
    private static function generateULID(): string
    {
        $timeMs = (int) (microtime(true) * 1000);
        $timePart = self::encodeTime($timeMs);
        $randomPart = self::encodeRandom(random_bytes(10), 16);

        return $timePart . $randomPart;
    }

    /**
     * Encode 48-bit time to 10-char Crockford Base32
     */
    private static function encodeTime(int $timeMs): string
    {
        $chars = self::CROCKFORD_BASE32;
        $out = '';

        for ($i = 0; $i < 10; $i++) {
            $index = $timeMs % 32;
            $out = $chars[$index] . $out;
            $timeMs = intdiv($timeMs, 32);
        }

        return $out;
    }

    /**
     * Encode random bytes to Crockford Base32
     */
    private static function encodeRandom(string $bytes, int $length): string
    {
        $chars = self::CROCKFORD_BASE32;
        $buffer = 0;
        $bits = 0;
        $out = '';

        $byteLen = strlen($bytes);
        for ($i = 0; $i < $byteLen; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5 && strlen($out) < $length) {
                $bits -= 5;
                $index = ($buffer >> $bits) & 0x1F;
                $out .= $chars[$index];
            }
        }

        if ($bits > 0 && strlen($out) < $length) {
            $index = ($buffer << (5 - $bits)) & 0x1F;
            $out .= $chars[$index];
        }

        return substr($out, 0, $length);
    }

    private static function isValidUlid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match(self::ULID_PATTERN, $value);
    }

    /**
     * Get all trace IDs as array
     */
    public static function getAll(): array
    {
        return [
            'correlation_id' => self::getCorrelationId(),
            'transaction_id' => self::getTransactionId(),
            'request_id' => self::getRequestId(),
        ];
    }
}
