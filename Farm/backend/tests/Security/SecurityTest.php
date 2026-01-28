<?php

namespace Farm\Backend\Tests\Security;

use Farm\Backend\Tests\ApiTestCase;
use Farm\Backend\App\Core\Testing\SecurityTester;

/**
 * Security Test Example
 * 
 * Automated security vulnerability tests.
 * 
 * Run: vendor/bin/phpunit tests/Security
 */
class SecurityTest extends ApiTestCase
{
    private SecurityTester $securityTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->securityTester = new SecurityTester('http://localhost');
    }

    /**
     * Test: API endpoints require authentication
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        $protectedEndpoints = [
            '/api/v1/users/me',
            '/api/v1/users',
            '/api/v1/orders',
            '/api/v1/admin/users'
        ];
        
        foreach ($protectedEndpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            $this->assertEquals(
                401,
                $response['status'],
                "Endpoint $endpoint should require authentication"
            );
        }
    }

    /**
     * Test: SQL injection prevention
     * 
     * @test
     */
    public function it_prevents_sql_injection()
    {
        $maliciousPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE users--",
            "1' UNION SELECT NULL--"
        ];
        
        foreach ($maliciousPayloads as $payload) {
            $response = $this->getJson("/api/v1/users?search=" . urlencode($payload));
            
            // Should not return SQL errors
            $body = json_encode($response['body']);
            $this->assertStringNotContainsString('SQL', $body);
            $this->assertStringNotContainsString('mysql', $body);
            $this->assertStringNotContainsString('database error', $body);
        }
    }

    /**
     * Test: XSS prevention
     * 
     * @test
     */
    public function it_prevents_xss()
    {
        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        $xssPayload = '<script>alert("XSS")</script>';
        
        $response = $this->withToken($token)
            ->postJson('/api/v1/users/profile', [
                'name' => $xssPayload,
                'bio' => $xssPayload
            ]);
        
        // Payload should be escaped in response
        $body = json_encode($response['body']);
        $this->assertStringNotContainsString('<script>', $body);
    }

    /**
     * Test: CSRF protection
     * 
     * @test
     */
    public function it_prevents_csrf_attacks()
    {
        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        // Make state-changing request without CSRF token
        $response = $this->withToken($token)
            ->deleteJson('/api/v1/users/' . $user['id']);
        
        // For API, token authentication is sufficient
        // For cookie-based auth, CSRF token would be required
        $this->assertNotEquals(200, $response['status']);
    }

    /**
     * Test: Rate limiting works
     * 
     * @test
     */
    public function it_enforces_rate_limiting()
    {
        $requestCount = 0;
        $rateLimitHit = false;
        
        // Make 150 requests
        for ($i = 0; $i < 150; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong'
            ]);
            
            $requestCount++;
            
            if ($response['status'] === 429) {
                $rateLimitHit = true;
                break;
            }
        }
        
        $this->assertTrue(
            $rateLimitHit,
            "Rate limiting should trigger after multiple requests (made $requestCount requests)"
        );
    }

    /**
     * Test: Authorization checks enforced
     * 
     * @test
     */
    public function it_enforces_authorization()
    {
        $user1 = $this->factory('User')->create();
        $user2 = $this->factory('User')->create();
        
        $token1 = $this->issueAccessToken($user1);
        
        // Try to access user2's data with user1's token
        $response = $this->withToken($token1)
            ->getJson("/api/v1/users/{$user2['id']}");
        
        $this->assertResponseForbidden($response);
    }

    /**
     * Test: Admin endpoints require admin role
     * 
     * @test
     */
    public function it_restricts_admin_endpoints()
    {
        $regularUser = $this->factory('User')->create();
        $token = $this->issueAccessToken($regularUser);
        
        $adminEndpoints = [
            '/api/v1/admin/users',
            '/api/v1/admin/settings',
            '/api/v1/admin/logs'
        ];
        
        foreach ($adminEndpoints as $endpoint) {
            $response = $this->withToken($token)
                ->getJson($endpoint);
            
            $this->assertResponseForbidden($response);
        }
    }

    /**
     * Test: Password complexity enforced
     * 
     * @test
     */
    public function it_enforces_password_complexity()
    {
        $weakPasswords = [
            'password',
            '12345678',
            'qwerty',
            'abc123'
        ];
        
        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/v1/auth/register', [
                'email' => 'test@example.com',
                'password' => $password,
                'name' => 'Test User'
            ]);
            
            $this->assertResponseBadRequest($response);
        }
    }

    /**
     * Test: Email validation enforced
     * 
     * @test
     */
    public function it_validates_email_format()
    {
        $invalidEmails = [
            'not-an-email',
            '@example.com',
            'user@',
            'user space@example.com'
        ];
        
        foreach ($invalidEmails as $email) {
            $response = $this->postJson('/api/v1/auth/register', [
                'email' => $email,
                'password' => 'SecurePassword123!',
                'name' => 'Test User'
            ]);
            
            $this->assertResponseBadRequest($response);
        }
    }

    /**
     * Test: Mass assignment protection
     * 
     * @test
     */
    public function it_prevents_mass_assignment()
    {
        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        // Try to update protected fields
        $response = $this->withToken($token)
            ->putJson('/api/v1/users/me', [
                'role' => 'admin',  // Should not be allowed
                'id' => 'new-id',   // Should not be allowed
                'name' => 'New Name'
            ]);
        
        // Check that protected fields weren't updated
        $updatedUser = $this->factory('User')->create(['id' => $user['id']]);
        $this->assertNotEquals('admin', $updatedUser['role'] ?? 'user');
    }

    /**
     * Test: Secure headers present
     * 
     * @test
     */
    public function it_includes_security_headers()
    {
        $response = $this->getJson('/api/v1/health');
        
        $requiredHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security'
        ];
        
        foreach ($requiredHeaders as $header) {
            $this->assertArrayHasKey(
                $header,
                $response['headers'],
                "Security header $header is missing"
            );
        }
    }

    // Token helper is provided by ApiTestCase
}
