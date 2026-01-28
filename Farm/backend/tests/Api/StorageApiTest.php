<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class StorageApiTest extends ApiTestCase
{
    /**
     * @test
     */
    public function it_exposes_public_config_with_observability_headers()
    {
        $response = $this->getJson('/api/v1/storage/public-config');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
        $this->assertJsonHas('data.categories', $response);
        $this->assertJsonHas('data.max_upload_size', $response);
    }

    /**
     * @test
     */
    public function it_requires_authentication_for_storage_config()
    {
        $response = $this->getJson('/api/v1/storage/config');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    private function assertSecurityHeaders(array $response): void
    {
        $headers = $response['headers'] ?? [];

        $this->assertArrayHasKey('X-Correlation-Id', $headers);
        $this->assertArrayHasKey('X-Transaction-Id', $headers);
        $this->assertArrayHasKey('X-Request-Id', $headers);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('X-XSS-Protection', $headers);
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
    }
}
