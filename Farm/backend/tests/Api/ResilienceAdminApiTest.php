<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class ResilienceAdminApiTest extends ApiTestCase
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

    /** @test */
    public function it_requires_admin_for_resilience_routes()
    {
        $response = $this->getJson('/api/admin/resilience/retry/stats');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_admin_for_resilience_routes()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->getJson('/api/admin/resilience/retry/stats');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }
}
