<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class AccountStatusApiTest extends ApiTestCase
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
    public function it_requires_auth_for_account_deactivate()
    {
        $response = $this->postJson('/account/deactivate', ['reason' => 'test']);

        $this->assertResponseUnauthorized($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_authenticated_account_deactivate()
    {
        $user = [
            'user_id' => 'user-' . uniqid(),
            'email' => 'user+' . uniqid() . '@example.com',
            'role' => 'user'
        ];

        $response = $this->actingAs($user)->postJson('/account/deactivate', ['reason' => 'test']);

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_admin_account_status_actions()
    {
        $admin = $this->makeAdmin();

        $lock = $this->actingAs($admin)->postJson('/admin/users/123/lock', ['reason' => 'test']);
        $this->assertResponseOk($lock);

        $history = $this->actingAs($admin)->getJson('/admin/users/123/status-history');
        $this->assertResponseOk($history);
    }
}
