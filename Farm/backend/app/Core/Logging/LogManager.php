<?php

declare(strict_types=1);

namespace Farm\Backend\App\Core\Logging;

use PHPFrarm\Core\Logger as BaseLogger;

/**
 * LogManager shim for Farm\\Backend\\App namespace.
 */
class LogManager
{
    public function info(string $message, array $context = []): void
    {
        BaseLogger::info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        BaseLogger::warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        BaseLogger::error($message, $context);
    }

    public function security(string $message, array $context = []): void
    {
        BaseLogger::security($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        BaseLogger::debug($message, $context);
    }
}
