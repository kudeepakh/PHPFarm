<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class DocsApiTest extends ApiTestCase
{
    /** @test */
    public function it_serves_documentation_endpoints()
    {
        $paths = [
            '/docs/health',
            '/docs/openapi.json',
            '/docs/errors',
            '/docs/postman'
        ];

        foreach ($paths as $path) {
            $response = $this->getJson($path);
            $this->assertResponseOk($response);
            $this->assertHasTraceIds($response);
        }
    }
}
