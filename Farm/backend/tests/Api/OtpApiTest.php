<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class OtpApiTest extends ApiTestCase
{
    /** @test */
    public function it_requests_and_verifies_otp()
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'identifier' => 'user@example.com',
            'type' => 'email',
            'purpose' => 'login'
        ]);

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'identifier' => 'user@example.com',
            'otp' => '123456',
            'purpose' => 'login'
        ]);

        $this->assertResponseOk($verify);
        $this->assertHasTraceIds($verify);
    }

    /** @test */
    public function it_rejects_invalid_otp_request_payload()
    {
        $response = $this->postJson('/api/v1/auth/otp/request', []);

        $this->assertResponseBadRequest($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_handles_password_reset_otp_flow()
    {
        $request = $this->postJson('/api/v1/auth/password/forgot', [
            'identifier' => 'user@example.com',
            'type' => 'email'
        ]);

        $this->assertResponseOk($request);
        $this->assertHasTraceIds($request);

        $reset = $this->postJson('/api/v1/auth/password/reset', [
            'identifier' => 'user@example.com',
            'type' => 'email',
            'otp' => '123456',
            'new_password' => 'StrongPass123!'
        ]);

        $this->assertResponseOk($reset);
        $this->assertHasTraceIds($reset);
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
