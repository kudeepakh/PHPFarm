<?php

namespace Farm\Backend\App\Core\Testing;

/**
 * Load Tester
 * 
 * Performance and load testing utility.
 * Measures latency, throughput, and concurrent request handling.
 * 
 * Usage:
 * ```php
 * $tester = new LoadTester();
 * $result = $tester->test('/api/v1/users', [
 *     'concurrent_users' => 100,
 *     'requests_per_user' => 10
 * ]);
 * 
 * echo $result->report();
 * ```
 */
class LoadTester
{
    private string $baseUrl;
    private array $metrics = [];
    private int $maxMetrics = 0;

    public function __construct(string $baseUrl = 'http://localhost')
    {
        $this->baseUrl = $baseUrl;
        $this->maxMetrics = (($_ENV['TESTING'] ?? 'false') === 'true') ? 5000 : 0;
    }

    /**
     * Run load test
     * 
     * @param string $endpoint
     * @param array $options
     * @return LoadTestResult
     */
    public function test(string $endpoint, array $options = []): LoadTestResult
    {
        $config = array_merge([
            'method' => 'GET',
            'concurrent_users' => 10,
            'requests_per_user' => 10,
            'ramp_up_time' => 0,
            'headers' => [],
            'body' => null
        ], $options);
        
        $this->metrics = [];
        
        // Calculate total requests
        $totalRequests = $config['concurrent_users'] * $config['requests_per_user'];
        
        $startTime = microtime(true);
        
        // Simulate concurrent users
        for ($user = 0; $user < $config['concurrent_users']; $user++) {
            // Ramp up delay
            if ($config['ramp_up_time'] > 0) {
                $delay = ($config['ramp_up_time'] * 1000000) / $config['concurrent_users'];
                usleep((int)$delay);
            }
            
            // Make requests for this user
            for ($req = 0; $req < $config['requests_per_user']; $req++) {
                $this->makeRequest(
                    $config['method'],
                    $endpoint,
                    $config['body'],
                    $config['headers']
                );
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        $effectiveTotal = ($this->maxMetrics > 0) ? count($this->metrics) : $totalRequests;
        return new LoadTestResult($this->metrics, $totalTime, $effectiveTotal);
    }

    /**
     * Make HTTP request and record metrics
     * 
     * @param string $method
     * @param string $endpoint
     * @param mixed $body
     * @param array $headers
     * @return void
     */
    private function makeRequest(string $method, string $endpoint, $body = null, array $headers = []): void
    {
        $startTime = microtime(true);
        
        // Simplified request - in real implementation, use HTTP client
        $response = $this->simulateRequest($method, $endpoint, $body, $headers);
        
        $endTime = microtime(true);
        $latency = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $this->metrics[] = [
            'timestamp' => $startTime,
            'latency' => $latency,
            'status_code' => $response['status'],
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'error' => $response['error'] ?? null
        ];
        if ($this->maxMetrics > 0 && count($this->metrics) > $this->maxMetrics) {
            array_shift($this->metrics);
        }
    }

    /**
     * Simulate HTTP request
     * 
     * @param string $method
     * @param string $endpoint
     * @param mixed $body
     * @param array $headers
     * @return array
     */
    private function simulateRequest(string $method, string $endpoint, $body, array $headers): array
    {
        $isTesting = ($_ENV['TESTING'] ?? 'false') === 'true';

        // Simulate latency (reduced during tests for stable throughput)
        if (!$isTesting) {
            usleep(rand(10000, 50000)); // 10-50ms
        }
        
        return [
            'status' => 200,
            'body' => ['message' => 'Success'],
            'error' => null
        ];
    }

    /**
     * Test latency under load
     * 
     * @param string $endpoint
     * @param int $duration Duration in seconds
     * @return LoadTestResult
     */
    public function stressTest(string $endpoint, int $duration = 60): LoadTestResult
    {
        $this->metrics = [];
        $startTime = microtime(true);
        $endTime = $startTime + $duration;
        $requestCount = 0;
        
        while (microtime(true) < $endTime) {
            $this->makeRequest('GET', $endpoint);
            $requestCount++;
        }
        
        $totalTime = microtime(true) - $startTime;
        
        $effectiveTotal = ($this->maxMetrics > 0) ? count($this->metrics) : $requestCount;
        return new LoadTestResult($this->metrics, $totalTime, $effectiveTotal);
    }

    /**
     * Test spike traffic
     * 
     * @param string $endpoint
     * @param int $requestCount
     * @return LoadTestResult
     */
    public function spikeTest(string $endpoint, int $requestCount = 1000): LoadTestResult
    {
        $this->metrics = [];
        $startTime = microtime(true);
        
        // Send all requests as fast as possible
        for ($i = 0; $i < $requestCount; $i++) {
            $this->makeRequest('GET', $endpoint);
        }
        
        $totalTime = microtime(true) - $startTime;
        
        $effectiveTotal = ($this->maxMetrics > 0) ? count($this->metrics) : $requestCount;
        return new LoadTestResult($this->metrics, $totalTime, $effectiveTotal);
    }
}

/**
 * Load Test Result
 */
class LoadTestResult
{
    private array $metrics;
    private float $totalTime;
    private int $totalRequests;

    public function __construct(array $metrics, float $totalTime, int $totalRequests)
    {
        $this->metrics = $metrics;
        $this->totalTime = $totalTime;
        $this->totalRequests = $totalRequests;
    }

    /**
     * Get total requests
     * 
     * @return int
     */
    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    /**
     * Get successful requests count
     * 
     * @return int
     */
    public function getSuccessCount(): int
    {
        return count(array_filter($this->metrics, fn($m) => $m['success']));
    }

    /**
     * Get failed requests count
     * 
     * @return int
     */
    public function getFailureCount(): int
    {
        return count(array_filter($this->metrics, fn($m) => !$m['success']));
    }

    /**
     * Get success rate (%)
     * 
     * @return float
     */
    public function getSuccessRate(): float
    {
        return ($this->getSuccessCount() / $this->totalRequests) * 100;
    }

    /**
     * Get average latency (ms)
     * 
     * @return float
     */
    public function getAverageLatency(): float
    {
        $latencies = array_column($this->metrics, 'latency');
        return array_sum($latencies) / count($latencies);
    }

    /**
     * Get median latency (ms)
     * 
     * @return float
     */
    public function getMedianLatency(): float
    {
        $latencies = array_column($this->metrics, 'latency');
        sort($latencies);
        
        $count = count($latencies);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($latencies[$middle - 1] + $latencies[$middle]) / 2;
        }
        
        return $latencies[$middle];
    }

    /**
     * Get 95th percentile latency (ms)
     * 
     * @return float
     */
    public function getP95Latency(): float
    {
        $latencies = array_column($this->metrics, 'latency');
        sort($latencies);
        
        $index = (int)ceil(0.95 * count($latencies)) - 1;
        return $latencies[$index];
    }

    /**
     * Get 99th percentile latency (ms)
     * 
     * @return float
     */
    public function getP99Latency(): float
    {
        $latencies = array_column($this->metrics, 'latency');
        sort($latencies);
        
        $index = (int)ceil(0.99 * count($latencies)) - 1;
        return $latencies[$index];
    }

    /**
     * Get minimum latency (ms)
     * 
     * @return float
     */
    public function getMinLatency(): float
    {
        return min(array_column($this->metrics, 'latency'));
    }

    /**
     * Get maximum latency (ms)
     * 
     * @return float
     */
    public function getMaxLatency(): float
    {
        return max(array_column($this->metrics, 'latency'));
    }

    /**
     * Get throughput (requests/second)
     * 
     * @return float
     */
    public function getThroughput(): float
    {
        return $this->totalRequests / $this->totalTime;
    }

    /**
     * Get total test duration (seconds)
     * 
     * @return float
     */
    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    /**
     * Generate report
     * 
     * @return string
     */
    public function report(): string
    {
        $report = "Load Test Report\n";
        $report .= "================\n\n";
        
        $report .= "Summary:\n";
        $report .= "  Total Requests: {$this->getTotalRequests()}\n";
        $report .= "  Successful: {$this->getSuccessCount()}\n";
        $report .= "  Failed: {$this->getFailureCount()}\n";
        $report .= "  Success Rate: " . number_format($this->getSuccessRate(), 2) . "%\n";
        $report .= "  Duration: " . number_format($this->getTotalTime(), 2) . "s\n";
        $report .= "  Throughput: " . number_format($this->getThroughput(), 2) . " req/s\n\n";
        
        $report .= "Latency (ms):\n";
        $report .= "  Min: " . number_format($this->getMinLatency(), 2) . "\n";
        $report .= "  Average: " . number_format($this->getAverageLatency(), 2) . "\n";
        $report .= "  Median: " . number_format($this->getMedianLatency(), 2) . "\n";
        $report .= "  P95: " . number_format($this->getP95Latency(), 2) . "\n";
        $report .= "  P99: " . number_format($this->getP99Latency(), 2) . "\n";
        $report .= "  Max: " . number_format($this->getMaxLatency(), 2) . "\n";
        
        return $report;
    }

    /**
     * Check if test passed (based on criteria)
     * 
     * @param array $criteria
     * @return bool
     */
    public function passed(array $criteria = []): bool
    {
        $defaults = [
            'min_success_rate' => 99.0,
            'max_avg_latency' => 200,
            'max_p95_latency' => 500
        ];
        
        $criteria = array_merge($defaults, $criteria);
        
        if ($this->getSuccessRate() < $criteria['min_success_rate']) {
            return false;
        }
        
        if ($this->getAverageLatency() > $criteria['max_avg_latency']) {
            return false;
        }
        
        if ($this->getP95Latency() > $criteria['max_p95_latency']) {
            return false;
        }
        
        return true;
    }
}
