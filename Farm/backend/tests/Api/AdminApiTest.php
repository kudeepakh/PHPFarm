<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class AdminApiTest extends ApiTestCase
{
    private function makeAdmin(): array
    {
        $id = 'admin-' . uniqid();
        return [
            'user_id' => $id,
            'email' => 'admin+' . $id . '@example.com',
            'role' => 'admin',
            'roles' => ['admin']
        ];
    }

    /**
     * @test
     */
    public function it_blocks_admin_routes_without_authentication()
    {
        $response = $this->getJson('/api/v1/admin/roles');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    /**
     * @test
     */
    public function it_blocks_admin_routes_for_non_admin_user()
    {
        $id = 'user-' . uniqid();
        $user = [
            'user_id' => $id,
            'email' => 'user+' . $id . '@example.com',
            'role' => 'user',
            'roles' => ['user']
        ];

        $response = $this->actingAs($user)->getJson('/api/v1/admin/roles');

        $this->assertResponseForbidden($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    /**
     * @test
     */
    public function it_allows_admin_routes_for_admin_user()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
        $this->assertSecurityHeaders($response);
    }

    /**
     * @test
     */
    public function it_allows_admin_permission_and_user_role_routes_for_admin_user()
    {
        $admin = $this->makeAdmin();

        $permissionResponse = $this->actingAs($admin)->getJson('/api/v1/admin/permissions');
        $this->assertResponseOk($permissionResponse);
        $this->assertHasTraceIds($permissionResponse);

        $userRoleResponse = $this->actingAs($admin)->getJson('/api/v1/admin/users/123/roles');
        $this->assertResponseOk($userRoleResponse);
        $this->assertHasTraceIds($userRoleResponse);
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
