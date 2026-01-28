<?php

namespace Farm\Backend\App\Core\Testing;

/**
 * HTTP Mock Server
 * 
 * Provides HTTP mocking for external service dependencies.
 * Records requests and returns configured responses.
 * 
 * Usage:
 * ```php
 * $mock = new MockServer();
 * $mock->start();
 * 
 * $mock->when('POST', '/api/charge')
 *      ->thenReturn(200, ['id' => 'ch_123', 'status' => 'succeeded']);
 * 
 * // Your code makes HTTP request...
 * 
 * $mock->assertCalled('POST', '/api/charge');
 * $mock->stop();
 * ```
 */
class MockServer
{
    private array $expectations = [];
    private array $requests = [];
    private bool $running = false;
    private string $baseUrl;

    public function __construct(string $baseUrl = 'http://localhost:8888')
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Start mock server
     * 
     * @return void
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }
        
        $this->running = true;
        $this->expectations = [];
        $this->requests = [];
    }

    /**
     * Stop mock server
     * 
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Reset all expectations and requests
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->expectations = [];
        $this->requests = [];
    }

    /**
     * Create expectation for method and path
     * 
     * @param string $method
     * @param string $path
     * @return MockExpectation
     */
    public function when(string $method, string $path): MockExpectation
    {
        $expectation = new MockExpectation($method, $path);
        $this->expectations[] = $expectation;
        
        return $expectation;
    }

    /**
     * Handle incoming request
     * 
     * @param string $method
     * @param string $path
     * @param array $headers
     * @param mixed $body
     * @return MockResponse
     */
    public function handleRequest(string $method, string $path, array $headers = [], $body = null): MockResponse
    {
        // Record request
        $this->requests[] = [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'body' => $body,
            'time' => microtime(true)
        ];
        
        // Find matching expectation
        foreach ($this->expectations as $expectation) {
            if ($expectation->matches($method, $path, $body)) {
                return $expectation->getResponse();
            }
        }
        
        // No match found
        return new MockResponse(404, ['error' => 'Mock not configured']);
    }

    /**
     * Assert request was made
     * 
     * @param string $method
     * @param string $path
     * @return void
     */
    public function assertCalled(string $method, string $path): void
    {
        foreach ($this->requests as $request) {
            if ($request['method'] === $method && $request['path'] === $path) {
                return;
            }
        }
        
        throw new \PHPUnit\Framework\AssertionFailedError(
            "Expected $method $path to be called, but it wasn't"
        );
    }

    /**
     * Assert request was called N times
     * 
     * @param string $method
     * @param string $path
     * @param int $times
     * @return void
     */
    public function assertCalledTimes(string $method, string $path, int $times): void
    {
        $count = 0;
        
        foreach ($this->requests as $request) {
            if ($request['method'] === $method && $request['path'] === $path) {
                $count++;
            }
        }
        
        if ($count !== $times) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected $method $path to be called $times times, but was called $count times"
            );
        }
    }

    /**
     * Assert request was not called
     * 
     * @param string $method
     * @param string $path
     * @return void
     */
    public function assertNotCalled(string $method, string $path): void
    {
        foreach ($this->requests as $request) {
            if ($request['method'] === $method && $request['path'] === $path) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Expected $method $path to NOT be called, but it was"
                );
            }
        }
    }

    /**
     * Get all recorded requests
     * 
     * @return array
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get base URL
     * 
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}

/**
 * Mock Expectation
 */
class MockExpectation
{
    private string $method;
    private string $path;
    private ?array $expectedBody = null;
    private int $statusCode = 200;
    private array $responseBody = [];
    private array $responseHeaders = [];
    private ?int $delay = null;

    public function __construct(string $method, string $path)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
    }

    /**
     * Expect specific request body
     * 
     * @param array $body
     * @return self
     */
    public function withBody(array $body): self
    {
        $this->expectedBody = $body;
        return $this;
    }

    /**
     * Return response
     * 
     * @param int $statusCode
     * @param array $body
     * @param array $headers
     * @return self
     */
    public function thenReturn(int $statusCode, array $body = [], array $headers = []): self
    {
        $this->statusCode = $statusCode;
        $this->responseBody = $body;
        $this->responseHeaders = $headers;
        return $this;
    }

    /**
     * Delay response
     * 
     * @param int $milliseconds
     * @return self
     */
    public function withDelay(int $milliseconds): self
    {
        $this->delay = $milliseconds;
        return $this;
    }

    /**
     * Check if request matches expectation
     * 
     * @param string $method
     * @param string $path
     * @param mixed $body
     * @return bool
     */
    public function matches(string $method, string $path, $body): bool
    {
        if ($this->method !== strtoupper($method)) {
            return false;
        }
        
        if (!$this->matchPath($path)) {
            return false;
        }
        
        if ($this->expectedBody !== null && $body !== $this->expectedBody) {
            return false;
        }
        
        return true;
    }

    /**
     * Match path (with wildcard support)
     * 
     * @param string $path
     * @return bool
     */
    private function matchPath(string $path): bool
    {
        // Exact match
        if ($this->path === $path) {
            return true;
        }
        
        // Wildcard match
        $pattern = str_replace('*', '.*', preg_quote($this->path, '/'));
        return (bool)preg_match("/^$pattern$/", $path);
    }

    /**
     * Get configured response
     * 
     * @return MockResponse
     */
    public function getResponse(): MockResponse
    {
        // Apply delay if configured
        if ($this->delay !== null) {
            usleep($this->delay * 1000);
        }
        
        return new MockResponse(
            $this->statusCode,
            $this->responseBody,
            $this->responseHeaders
        );
    }
}

/**
 * Mock Response
 */
class MockResponse
{
    private int $statusCode;
    private array $body;
    private array $headers;

    public function __construct(int $statusCode, array $body = [], array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->statusCode,
            'body' => $this->body,
            'headers' => $this->headers
        ];
    }
}
