<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class UserApiTest extends ApiTestCase
{
    private function makeUser(array $overrides = []): array
    {
        $id = $overrides['user_id'] ?? ('user-' . uniqid());
        $email = $overrides['email'] ?? ('user+' . $id . '@example.com');

        return array_merge([
            'user_id' => $id,
            'email' => $email,
            'role' => 'user'
        ], $overrides);
    }

    /**
     * @test
     */
    public function it_requires_authentication_for_profile()
    {
        $response = $this->getJson('/api/v1/users/me');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    /**
     * @test
     */
    public function it_returns_profile_for_authenticated_user()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->getJson('/api/v1/users/me');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
        $this->assertJsonHas('data.id', $response);
        $this->assertJsonHas('data.email', $response);
    }

    /**
     * @test
     */
    public function it_returns_paginated_user_list()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->getJson('/api/v1/users?page=1&per_page=20');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
        $this->assertJsonHas('meta.pagination', $response);
        $this->assertJsonHas('data.items', $response);
    }

    /**
     * @test
     */
    public function it_prevents_access_to_other_user_resource()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->getJson('/api/v1/users/other-user');

        $this->assertResponseForbidden($response);
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
