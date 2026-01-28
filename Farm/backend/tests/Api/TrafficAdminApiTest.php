<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class TrafficAdminApiTest extends ApiTestCase
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
    public function it_requires_admin_for_traffic_routes()
    {
        $response = $this->getJson('/admin/traffic/rate-limit/stats');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_admin_for_traffic_routes()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->getJson('/admin/traffic/rate-limit/stats');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }
}
