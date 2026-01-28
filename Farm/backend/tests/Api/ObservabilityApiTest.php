<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class ObservabilityApiTest extends ApiTestCase
{
    /**
     * @test
     */
    public function it_includes_trace_ids_and_security_headers_on_health()
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    /**
     * @test
     */
    public function it_generates_request_ids_for_errors()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'invalid',
            'password' => 'short'
        ]);

        $this->assertResponseBadRequest($response);
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
