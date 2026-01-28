<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class VerificationApiTest extends ApiTestCase
{
    /** @test */
    public function it_rejects_verify_email_without_token()
    {
        $response = $this->postJson('/verify-email', []);

        $this->assertResponseBadRequest($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_verify_email_with_token()
    {
        $response = $this->postJson('/verify-email', ['token' => 'test-token']);

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_requires_auth_for_verification_status_and_phone()
    {
        $status = $this->getJson('/verification-status');
        $this->assertResponseUnauthorized($status);

        $sendOtp = $this->postJson('/verify-phone/send-otp', ['phone' => '+1234567890']);
        $this->assertResponseUnauthorized($sendOtp);
    }

    /** @test */
    public function it_allows_authenticated_verification_checks()
    {
        $user = [
            'user_id' => 'user-' . uniqid(),
            'email' => 'user+' . uniqid() . '@example.com',
            'role' => 'user'
        ];

        $status = $this->actingAs($user)->getJson('/verification-status');
        $this->assertResponseOk($status);
        $this->assertHasTraceIds($status);

        $sendOtp = $this->actingAs($user)->postJson('/verify-phone/send-otp', ['phone' => '+1234567890']);
        $this->assertResponseOk($sendOtp);
        $this->assertHasTraceIds($sendOtp);
    }
}
