<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class SocialAuthApiTest extends ApiTestCase
{
    /** @test */
    public function it_rejects_social_auth_without_redirect()
    {
        $response = $this->getJson('/api/auth/social/google');

        $this->assertResponseBadRequest($response);
        $this->assertHasTraceIds($response);
    }

    /** @test */
    public function it_allows_social_auth_with_redirect()
    {
        $response = $this->getJson('/api/auth/social/google?redirect_uri=https://example.com');

        $this->assertResponseOk($response);
        $this->assertHasTraceIds($response);
    }
}
