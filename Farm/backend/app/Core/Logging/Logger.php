<?php

declare(strict_types=1);

namespace App\Core\Logging;

use PHPFrarm\Core\Logger as BaseLogger;

/**
 * Logger shim for legacy App namespace.
 */
class Logger
{
    public static function info(string $message, array $context = []): void
    {
        BaseLogger::info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        BaseLogger::warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        BaseLogger::error($message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        BaseLogger::security($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        BaseLogger::debug($message, $context);
    }
}
