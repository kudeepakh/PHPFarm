<?php

namespace Farm\Backend\Tests\Load;

use Farm\Backend\Tests\ApiTestCase;
use Farm\Backend\App\Core\Testing\LoadTester;

/**
 * Load Test Example
 * 
 * Performance and load testing examples.
 * 
 * Run: vendor/bin/phpunit tests/Load
 */
class LoadTest extends ApiTestCase
{
    private LoadTester $loadTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadTester = new LoadTester('http://localhost');
    }

    /**
     * Test: Health endpoint handles concurrent requests
     * 
     * @test
     */
    public function it_handles_concurrent_health_checks()
    {
        $result = $this->loadTester->test('/api/v1/health', [
            'method' => 'GET',
            'concurrent_users' => 50,
            'requests_per_user' => 10
        ]);
        
        // Assert performance criteria
        $this->assertGreaterThan(
            95.0,
            $result->getSuccessRate(),
            'Health endpoint should have >95% success rate'
        );
        
        $this->assertLessThan(
            100,
            $result->getAverageLatency(),
            'Average latency should be <100ms'
        );
        
        if (($_ENV['TESTING'] ?? 'false') !== 'true') {
            echo $result->report();
        }
    }

    /**
     * Test: User registration performance
     * 
     * @test
     */
    public function it_handles_registration_load()
    {
        $result = $this->loadTester->test('/api/v1/auth/register', [
            'method' => 'POST',
            'concurrent_users' => 20,
            'requests_per_user' => 5,
            'body' => [
                'email' => 'test@example.com',
                'password' => 'SecurePassword123!',
                'name' => 'Test User'
            ]
        ]);
        
        $this->assertTrue(
            $result->passed([
                'min_success_rate' => 95.0,
                'max_avg_latency' => 500,
                'max_p95_latency' => 1000
            ]),
            'Registration endpoint failed performance criteria'
        );
        
        if (($_ENV['TESTING'] ?? 'false') !== 'true') {
            echo "\nRegistration Performance:\n";
            echo "  Throughput: " . number_format($result->getThroughput(), 2) . " req/s\n";
            echo "  Avg Latency: " . number_format($result->getAverageLatency(), 2) . " ms\n";
        }
    }

    /**
     * Test: User list pagination performance
     * 
     * @test
     */
    public function it_handles_list_pagination_load()
    {
        // Create test data
        $this->factory('User')->createMany(100);
        
        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        $result = $this->loadTester->test('/api/v1/users?page=1&per_page=20', [
            'method' => 'GET',
            'concurrent_users' => 30,
            'requests_per_user' => 10,
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);
        
        $this->assertLessThan(
            200,
            $result->getP95Latency(),
            'P95 latency should be <200ms for list endpoints'
        );
    }

    /**
     * Test: API handles stress
     * 
     * @test
     */
    public function it_handles_stress()
    {
        $result = $this->loadTester->stressTest('/api/v1/health', 30); // 30 seconds
        
        $this->assertGreaterThan(
            90.0,
            $result->getSuccessRate(),
            'API should maintain >90% success rate under stress'
        );
        
        $this->assertGreaterThan(
            100,
            $result->getThroughput(),
            'API should handle >100 req/s'
        );
        
        if (($_ENV['TESTING'] ?? 'false') !== 'true') {
            echo "\nStress Test Results:\n";
            echo $result->report();
        }
    }

    /**
     * Test: API handles traffic spikes
     * 
     * @test
     */
    public function it_handles_traffic_spikes()
    {
        $result = $this->loadTester->spikeTest('/api/v1/health', 500);
        
        $this->assertGreaterThan(
            90.0,
            $result->getSuccessRate(),
            'API should handle traffic spikes with >90% success rate'
        );
        
        $this->assertLessThan(
            1000,
            $result->getP99Latency(),
            'P99 latency should be <1000ms during spikes'
        );
    }

    /**
     * Test: Database query performance
     * 
     * @test
     */
    public function it_maintains_db_query_performance()
    {
        // Create test data
        $this->factory('User')->createMany(1000);
        
        $user = $this->factory('User')->create();
        $token = $this->issueAccessToken($user);
        
        $result = $this->loadTester->test('/api/v1/users/search?q=test', [
            'method' => 'GET',
            'concurrent_users' => 10,
            'requests_per_user' => 20,
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);
        
        $this->assertLessThan(
            300,
            $result->getAverageLatency(),
            'Search queries should average <300ms'
        );
    }

    /**
     * Test: API maintains performance under sustained load
     * 
     * @test
     */
    public function it_maintains_performance_under_sustained_load()
    {
        if (($_ENV['TESTING'] ?? 'false') === 'true') {
            $this->assertTrue(true);
            return;
        }

        $iterations = 3;
        $results = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $results[] = $this->loadTester->test('/api/v1/health', [
                'concurrent_users' => 20,
                'requests_per_user' => 50
            ]);
            
            sleep(5); // Brief pause between iterations
        }
        
        // Check performance doesn't degrade
        for ($i = 1; $i < $iterations; $i++) {
            $previousLatency = $results[$i - 1]->getAverageLatency();
            $currentLatency = $results[$i]->getAverageLatency();
            
            $degradation = (($currentLatency - $previousLatency) / $previousLatency) * 100;
            
            $this->assertLessThan(
                20,
                $degradation,
                "Performance degraded by {$degradation}% (should be <20%)"
            );
        }
    }

    // Token helper is provided by ApiTestCase
}
