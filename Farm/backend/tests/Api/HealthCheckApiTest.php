<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class HealthCheckApiTest extends ApiTestCase
{
    /** @test */
    public function it_returns_health_endpoints()
    {
        $paths = ['/health', '/health/ready', '/health/live'];

        foreach ($paths as $path) {
            $response = $this->getJson($path);
            $this->assertResponseOk($response);
            $this->assertHasTraceIds($response);
        }
    }
}
