<?php

namespace Farm\Backend\Tests\Integration;

use Farm\Backend\Tests\TestCase;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;

class HttpEndToEndTest extends TestCase
{
    private HttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new HttpClient([
            'base_uri' => 'http://localhost',
            'http_errors' => false,
            'timeout' => 10
        ]);
    }

    private function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Correlation-Id' => $this->generateUlid(),
            'X-Transaction-Id' => $this->generateUlid(),
            'X-Request-Id' => $this->generateUlid(),
        ];
    }

    private function jsonRequest(string $method, string $uri, array $json = []): ResponseInterface
    {
        return $this->client->request($method, $uri, [
            'headers' => array_merge($this->defaultHeaders(), [
                'Content-Type' => 'application/json'
            ]),
            'json' => $json
        ]);
    }

    private function getRequest(string $uri): ResponseInterface
    {
        return $this->client->request('GET', $uri, [
            'headers' => $this->defaultHeaders()
        ]);
    }

    private function generateUlid(): string
    {
        return '01HQZK' . strtoupper(bin2hex(random_bytes(10)));
    }

    private function assertTraceHeaders(ResponseInterface $response): void
    {
        $this->assertTrue($response->hasHeader('X-Correlation-Id'));
        $this->assertTrue($response->hasHeader('X-Transaction-Id'));
        $this->assertTrue($response->hasHeader('X-Request-Id'));
    }

    /** @test */
    public function it_serves_public_health_and_docs_endpoints(): void
    {
        $tracePaths = ['/health', '/api/status'];
        $healthPaths = ['/health/ready', '/health/live'];
        $docsPaths = ['/docs/health', '/docs/openapi.json', '/docs/errors', '/docs/postman'];

        foreach ($tracePaths as $path) {
            $response = $this->getRequest($path);
            $this->assertEquals(200, $response->getStatusCode(), "Unexpected status for $path");
            $this->assertTraceHeaders($response);
        }

        foreach ($healthPaths as $path) {
            $response = $this->getRequest($path);
            $this->assertContains($response->getStatusCode(), [200, 503], "Unexpected status for $path");
            $this->assertTraceHeaders($response);
        }

        foreach ($docsPaths as $path) {
            $response = $this->getRequest($path);
            $this->assertEquals(200, $response->getStatusCode(), "Unexpected status for $path");
        }
    }

    /** @test */
    public function it_registers_and_logs_in_user(): void
    {
        $email = 'user+' . uniqid() . '@example.com';

        $register = $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => 'StrongPass123!'
        ]);

        $this->assertEquals(201, $register->getStatusCode());
        $this->assertTraceHeaders($register);

        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'identifier' => $email,
            'password' => 'StrongPass123!'
        ]);

        $this->assertEquals(200, $login->getStatusCode());
        $this->assertTraceHeaders($login);
    }

    /** @test */
    public function it_enforces_auth_on_admin_and_account_routes(): void
    {
        $paths = [
            '/api/v1/admin/roles',
            '/api/v1/admin/permissions',
            '/api/admin/resilience/status'
        ];

        foreach ($paths as $path) {
            $response = $this->getRequest($path);
            $this->assertEquals(401, $response->getStatusCode(), "Expected 401 for $path");
            $this->assertTraceHeaders($response);
        }

        $deactivate = $this->jsonRequest('POST', '/account/deactivate', ['reason' => 'test']);
        $this->assertEquals(401, $deactivate->getStatusCode());
        $this->assertTraceHeaders($deactivate);
    }
}
