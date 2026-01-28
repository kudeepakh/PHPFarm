<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class AuthApiTest extends ApiTestCase
{
    /**
     * @test
     */
    public function it_registers_user_and_includes_observability_headers()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User'
        ]);

        $this->assertResponseCreated($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
        $this->assertJsonHas('data.user_id', $response);
    }

    /**
     * @test
     */
    public function it_rejects_invalid_registration_payload()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'invalid-email',
            'password' => 'weak'
        ]);

        $this->assertResponseBadRequest($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    /**
     * @test
     */
    public function it_enforces_rate_limit_on_login()
    {
        $_ENV['RATE_LIMIT_REQUESTS'] = 1;
        putenv('RATE_LIMIT_REQUESTS=1');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPass123!'
        ], ['X-Forwarded-For' => '10.0.0.1']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPass123!'
        ], ['X-Forwarded-For' => '10.0.0.1']);

        $this->assertEquals(429, $response['status']);
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
