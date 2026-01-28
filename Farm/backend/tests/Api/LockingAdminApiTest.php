<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class LockingAdminApiTest extends ApiTestCase
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
    public function it_requires_admin_for_locking_routes()
    {
        $response = $this->getJson('/admin/locking/statistics');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_admin_for_locking_routes()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->getJson('/admin/locking/statistics');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }
}
