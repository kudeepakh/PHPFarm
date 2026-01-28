<?php

declare(strict_types=1);

namespace Farm\Backend\App\Core\Observability;

use PHPFrarm\Core\TraceContext as BaseTraceContext;

/**
 * TraceContext shim for Farm\\Backend\\App namespace.
 */
class TraceContext
{
    public static function initialize(): void
    {
        BaseTraceContext::initialize();
    }

    public static function getCorrelationId(): string
    {
        return BaseTraceContext::getCorrelationId();
    }

    public static function getTransactionId(): string
    {
        return BaseTraceContext::getTransactionId();
    }

    public static function getRequestId(): string
    {
        return BaseTraceContext::getRequestId();
    }

    public static function setCorrelationId(string $value): void
    {
        BaseTraceContext::setCorrelationId($value);
    }

    public static function setTransactionId(string $value): void
    {
        BaseTraceContext::setTransactionId($value);
    }

    public static function setRequestId(string $value): void
    {
        BaseTraceContext::setRequestId($value);
    }
}
