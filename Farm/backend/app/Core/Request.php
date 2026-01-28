<?php

namespace PHPFrarm\Core;

/**
 * HTTP Request wrapper class
 * Provides object-oriented access to request data
 */
class Request
{
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Create Request from array (legacy format)
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get query parameter
     */
    public function query(string $key, $default = null)
    {
        return $this->data['query'][$key] ?? $default;
    }

    /**
     * Get all query parameters
     */
    public function queryAll(): array
    {
        return $this->data['query'] ?? [];
    }

    /**
     * Get request body
     */
    public function body(): array
    {
        return $this->data['body'] ?? [];
    }

    /**
     * Get specific body parameter
     */
    public function input(string $key, $default = null)
    {
        return $this->data['body'][$key] ?? $default;
    }

    /**
     * Get path parameter
     */
    public function param(string $key, $default = null)
    {
        return $this->data['params'][$key] ?? $default;
    }

    /**
     * Get all path parameters
     */
    public function params(): array
    {
        return $this->data['params'] ?? [];
    }

    /**
     * Get header value
     */
    public function header(string $key, $default = null)
    {
        return $this->data['headers'][$key] ?? $default;
    }

    /**
     * Get all headers
     */
    public function headers(): array
    {
        return $this->data['headers'] ?? [];
    }

    /**
     * Get HTTP method
     */
    public function method(): string
    {
        return $this->data['method'] ?? 'GET';
    }

    /**
     * Get request path
     */
    public function path(): string
    {
        return $this->data['path'] ?? '/';
    }

    /**
     * Get all request data as array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Check if key exists in body
     */
    public function has(string $key): bool
    {
        return isset($this->data['body'][$key]);
    }

    /**
     * Get user from request (if authenticated)
     */
    public function user(): ?array
    {
        return $this->data['user'] ?? null;
    }
}
