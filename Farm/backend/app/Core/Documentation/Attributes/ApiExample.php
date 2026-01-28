<?php

namespace Farm\Backend\App\Core\Documentation\Attributes;

use Attribute;

/**
 * #[ApiExample] - API request/response example attribute
 * 
 * Provides concrete examples for API documentation.
 * Multiple examples can be provided for different scenarios.
 * 
 * Usage:
 * ```php
 * #[ApiExample(
 *     name: "Basic user creation",
 *     summary: "Create user with minimal fields",
 *     request: [
 *         "email" => "john@example.com",
 *         "password" => "SecurePass123!"
 *     ],
 *     response: [
 *         "id" => "01HQZK1234567890",
 *         "email" => "john@example.com",
 *         "created_at" => "2026-01-18T10:30:00Z"
 *     ]
 * )]
 * public function createUser(array $body) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiExample
{
    /**
     * @param string $name Example name (unique identifier)
     * @param string|null $summary Short description
     * @param mixed $request Example request data
     * @param mixed $response Example response data
     * @param int $responseStatus HTTP status code for response example
     * @param string|null $description Detailed description (markdown)
     */
    public function __construct(
        public string $name,
        public ?string $summary = null,
        public mixed $request = null,
        public mixed $response = null,
        public int $responseStatus = 200,
        public ?string $description = null,
    ) {
    }

    /**
     * Convert to OpenAPI example object (for request)
     * 
     * @return array OpenAPI example
     */
    public function toOpenApiRequestExample(): array
    {
        $example = [
            'value' => $this->request,
        ];

        if ($this->summary !== null) {
            $example['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $example['description'] = $this->description;
        }

        return $example;
    }

    /**
     * Convert to OpenAPI example object (for response)
     * 
     * @return array OpenAPI example
     */
    public function toOpenApiResponseExample(): array
    {
        $example = [
            'value' => $this->response,
        ];

        if ($this->summary !== null) {
            $example['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $example['description'] = $this->description;
        }

        return $example;
    }

    /**
     * Has request example
     * 
     * @return bool
     */
    public function hasRequest(): bool
    {
        return $this->request !== null;
    }

    /**
     * Has response example
     * 
     * @return bool
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    /**
     * Create request-only example
     * 
     * @param string $name
     * @param mixed $request
     * @param string|null $summary
     * @return self
     */
    public static function request(string $name, mixed $request, ?string $summary = null): self
    {
        return new self(
            name: $name,
            summary: $summary,
            request: $request
        );
    }

    /**
     * Create response-only example
     * 
     * @param string $name
     * @param mixed $response
     * @param int $status
     * @param string|null $summary
     * @return self
     */
    public static function response(string $name, mixed $response, int $status = 200, ?string $summary = null): self
    {
        return new self(
            name: $name,
            summary: $summary,
            response: $response,
            responseStatus: $status
        );
    }

    /**
     * Create full example (request + response)
     * 
     * @param string $name
     * @param mixed $request
     * @param mixed $response
     * @param string|null $summary
     * @return self
     */
    public static function full(string $name, mixed $request, mixed $response, ?string $summary = null): self
    {
        return new self(
            name: $name,
            summary: $summary,
            request: $request,
            response: $response
        );
    }
}
