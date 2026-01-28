<?php

namespace Farm\Backend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPFrarm\Middleware\CommonMiddleware;

class RateLimitMiddlewareTest extends TestCase
{
    public function testRateLimitTracksCountsAndRemaining(): void
    {
        $ip = '192.0.2.' . uniqid();
        $limit = 2;
        $window = 5;

        $first = CommonMiddleware::checkRateLimit($ip, $limit, $window);
        $second = CommonMiddleware::checkRateLimit($ip, $limit, $window);
        $third = CommonMiddleware::checkRateLimit($ip, $limit, $window);

        $this->assertTrue($first['allowed']);
        $this->assertSame(1, $first['remaining']);
        $this->assertTrue($second['allowed']);
        $this->assertSame(0, $second['remaining']);
        $this->assertFalse($third['allowed']);
        $this->assertSame(0, $third['remaining']);
        $this->assertGreaterThanOrEqual(time(), $third['reset']);
    }
}
