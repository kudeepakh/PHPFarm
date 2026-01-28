<?php

namespace Farm\Backend\Tests\Contract;

use Farm\Backend\Tests\ApiTestCase;
use Farm\Backend\App\Core\Testing\ContractTester;

/**
 * Contract Test Example
 * 
 * Tests API responses against OpenAPI specification.
 * 
 * Run: vendor/bin/phpunit tests/Contract
 */
class ContractTest extends ApiTestCase
{
    private ContractTester $contractTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->contractTester = new ContractTester();
    }

    /**
     * Test: All endpoints match OpenAPI spec
     * 
     * @test
     */
    public function it_validates_all_endpoints_exist()
    {
        $endpoints = $this->contractTester->getEndpoints();
        
        $this->assertNotEmpty($endpoints, 'No endpoints found in OpenAPI spec');
        
        foreach ($endpoints as $endpoint) {
            $this->assertArrayHasKey('method', $endpoint);
            $this->assertArrayHasKey('path', $endpoint);
        }
    }

    /**
     * Test: User registration matches contract
     * 
     * @test
     */
    public function it_validates_user_registration_contract()
    {
        // Make request
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'name' => 'Test User'
        ]);
        
        // Validate request against spec
        $requestValidation = $this->contractTester->validateRequest(
            'POST',
            '/api/v1/auth/register',
            [],
            [
                'email' => 'test@example.com',
                'password' => 'SecurePassword123!',
                'name' => 'Test User'
            ]
        );
        
        $this->assertTrue(
            $requestValidation->isValid(),
            'Request validation failed: ' . $requestValidation->getFirstError()
        );
        
        // Validate response against spec
        $responseValidation = $this->contractTester->validateResponse(
            'POST',
            '/api/v1/auth/register',
            $response['status'],
            $response['body']
        );
        
        $this->assertTrue(
            $responseValidation->isValid(),
            'Response validation failed: ' . $responseValidation->getFirstError()
        );
    }

    /**
     * Test: User login matches contract
     * 
     * @test
     */
    public function it_validates_login_contract()
    {
        // Create test user
        $user = $this->factory('User')->create([
            'email' => 'login@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT)
        ]);
        
        // Make request
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123'
        ]);
        
        // Validate response
        $validation = $this->contractTester->validateResponse(
            'POST',
            '/api/v1/auth/login',
            $response['status'],
            $response['body']
        );
        
        $this->assertTrue(
            $validation->isValid(),
            'Login response validation failed: ' . $validation->getFirstError()
        );
        
        // Check token structure
        $this->assertArrayHasKey('token', $response['body']['data']);
        $this->assertArrayHasKey('refresh_token', $response['body']['data']);
    }

    /**
     * Test: Get user profile matches contract
     * 
     * @test
     */
    public function it_validates_get_profile_contract()
    {
        // Create and authenticate user
        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        // Make request
        $response = $this->withToken($token)
            ->getJson('/api/v1/users/me');
        
        // Validate response
        $validation = $this->contractTester->validateResponse(
            'GET',
            '/api/v1/users/me',
            $response['status'],
            $response['body']
        );
        
        $this->assertTrue(
            $validation->isValid(),
            'Profile response validation failed: ' . $validation->getFirstError()
        );
    }

    /**
     * Test: Error responses match contract
     * 
     * @test
     */
    public function it_validates_error_response_contract()
    {
        // Make invalid request (missing required field)
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'invalid-email', // Invalid email format
        ]);
        
        // Validate error response
        $validation = $this->contractTester->validateResponse(
            'POST',
            '/api/v1/auth/register',
            $response['status'],
            $response['body']
        );
        
        $this->assertTrue(
            $validation->isValid(),
            'Error response validation failed: ' . $validation->getFirstError()
        );
        
        // Check error structure
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertArrayHasKey('code', $response['body']['error']);
        $this->assertArrayHasKey('message', $response['body']['error']);
    }

    /**
     * Test: Pagination matches contract
     * 
     * @test
     */
    public function it_validates_pagination_contract()
    {
        // Create test users
        $this->factory('User')->createMany(15);

        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        // Get paginated list
        $response = $this->withToken($token)
            ->getJson('/api/v1/users?page=1&per_page=10');
        
        // Validate response
        $validation = $this->contractTester->validateResponse(
            'GET',
            '/api/v1/users',
            $response['status'],
            $response['body']
        );
        
        $this->assertTrue(
            $validation->isValid(),
            'Pagination response validation failed: ' . $validation->getFirstError()
        );
        
        // Check pagination structure
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertArrayHasKey('meta', $response['body']);
        $this->assertArrayHasKey('pagination', $response['body']['meta']);
    }

    /**
     * Test: Trace IDs present in all responses
     * 
     * @test
     */
    public function it_validates_trace_ids_in_responses()
    {
        $response = $this->getJson('/api/v1/health');
        
        $this->assertHasTraceIds($response);
    }

    // Token helper is provided by ApiTestCase
}
