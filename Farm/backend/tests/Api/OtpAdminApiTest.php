<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class OtpAdminApiTest extends ApiTestCase
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
    public function it_requires_admin_for_otp_admin_routes()
    {
        $response = $this->getJson('/admin/otp/history');

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_admin_for_otp_admin_routes()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->getJson('/admin/otp/history');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }
}
