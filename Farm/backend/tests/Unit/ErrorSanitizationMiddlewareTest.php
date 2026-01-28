<?php

namespace Farm\Backend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPFrarm\Core\Response;
use PHPFrarm\Middleware\ErrorSanitizationMiddleware;

class ErrorSanitizationMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Response::disableTestMode();
        parent::tearDown();
    }

    public function testDatabaseErrorReturns503(): void
    {
        Response::enableTestMode();

        ErrorSanitizationMiddleware::handle([], function () {
            throw new \PDOException('db failed');
        });

        $captured = Response::getLastResponse();
        $this->assertNotNull($captured);
        $this->assertSame(503, $captured['status']);
        $this->assertSame('ERR_DATABASE_UNAVAILABLE', $captured['body']['error_code']);
    }

    public function testDomainErrorReturns400(): void
    {
        Response::enableTestMode();

        ErrorSanitizationMiddleware::handle([], function () {
            throw new \RuntimeException('validation.failed');
        });

        $captured = Response::getLastResponse();
        $this->assertNotNull($captured);
        $this->assertSame(400, $captured['status']);
        $this->assertSame('ERR_DOMAIN', $captured['body']['error_code']);
    }
}
