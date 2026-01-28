<?php

namespace Farm\Backend\App\Core\Documentation\Attributes;

use Attribute;

/**
 * #[ApiResponse] - API response documentation attribute
 * 
 * Documents a possible response for an API endpoint.
 * Multiple attributes can be used to document different status codes.
 * 
 * Usage:
 * ```php
 * #[ApiResponse(
 *     status: 200,
 *     description: "User found",
 *     schema: UserDTO::class,
 *     example: ["id" => "01HQ...", "email" => "john@example.com"]
 * )]
 * #[ApiResponse(
 *     status: 404,
 *     description: "User not found",
 *     schema: ErrorDTO::class
 * )]
 * public function getUser(string $id) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponse
{
    /**
     * @param int $status HTTP status code (200, 201, 400, 404, etc.)
     * @param string $description Response description
     * @param string|null $schema Response schema class name (DTO class)
     * @param mixed $example Example response data
     * @param string $contentType Response content type (default: application/json)
     * @param array<string, string> $headers Response headers
     * @param bool $isArray Whether response is an array of schema
     */
    public function __construct(
        public int $status,
        public string $description,
        public ?string $schema = null,
        public mixed $example = null,
        public string $contentType = 'application/json',
        public array $headers = [],
        public bool $isArray = false,
    ) {
        // Validate status code
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException("Invalid HTTP status code: $status");
        }
    }

    /**
     * Convert to OpenAPI response object
     * 
     * @return array OpenAPI response
     */
    public function toOpenApi(): array
    {
        $response = [
            'description' => $this->description,
        ];

        // Add headers
        if (!empty($this->headers)) {
            $response['headers'] = [];
            foreach ($this->headers as $name => $description) {
                $response['headers'][$name] = [
                    'description' => $description,
                    'schema' => ['type' => 'string'],
                ];
            }
        }

        // Add content (if schema or example provided)
        if ($this->schema !== null || $this->example !== null) {
            $content = [];

            if ($this->schema !== null) {
                $schemaRef = '#/components/schemas/' . $this->getSchemaName($this->schema);
                
                if ($this->isArray) {
                    $content['schema'] = [
                        'type' => 'array',
                        'items' => ['$ref' => $schemaRef],
                    ];
                } else {
                    $content['schema'] = ['$ref' => $schemaRef];
                }
            }

            if ($this->example !== null) {
                $content['example'] = $this->example;
            }

            $response['content'] = [
                $this->contentType => $content,
            ];
        }

        return $response;
    }

    /**
     * Extract schema name from class
     * 
     * @param string $class
     * @return string
     */
    private function getSchemaName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Get status code category
     * 
     * @return string 'success', 'redirect', 'client_error', 'server_error'
     */
    public function getStatusCategory(): string
    {
        return match (true) {
            $this->status >= 200 && $this->status < 300 => 'success',
            $this->status >= 300 && $this->status < 400 => 'redirect',
            $this->status >= 400 && $this->status < 500 => 'client_error',
            $this->status >= 500 && $this->status < 600 => 'server_error',
            default => 'informational',
        };
    }

    /**
     * Create success response (200)
     * 
     * @param string $description
     * @param string|null $schema
     * @param mixed $example
     * @return self
     */
    public static function success(string $description, ?string $schema = null, mixed $example = null): self
    {
        return new self(
            status: 200,
            description: $description,
            schema: $schema,
            example: $example
        );
    }

    /**
     * Create created response (201)
     * 
     * @param string $description
     * @param string|null $schema
     * @return self
     */
    public static function created(string $description, ?string $schema = null): self
    {
        return new self(
            status: 201,
            description: $description,
            schema: $schema
        );
    }

    /**
     * Create no content response (204)
     * 
     * @param string $description
     * @return self
     */
    public static function noContent(string $description = 'No content'): self
    {
        return new self(
            status: 204,
            description: $description
        );
    }

    /**
     * Create bad request response (400)
     * 
     * @param string $description
     * @param string|null $schema
     * @return self
     */
    public static function badRequest(string $description = 'Bad request', ?string $schema = null): self
    {
        return new self(
            status: 400,
            description: $description,
            schema: $schema
        );
    }

    /**
     * Create unauthorized response (401)
     * 
     * @param string $description
     * @return self
     */
    public static function unauthorized(string $description = 'Unauthorized'): self
    {
        return new self(
            status: 401,
            description: $description
        );
    }

    /**
     * Create forbidden response (403)
     * 
     * @param string $description
     * @return self
     */
    public static function forbidden(string $description = 'Forbidden'): self
    {
        return new self(
            status: 403,
            description: $description
        );
    }

    /**
     * Create not found response (404)
     * 
     * @param string $description
     * @param string|null $schema
     * @return self
     */
    public static function notFound(string $description = 'Not found', ?string $schema = null): self
    {
        return new self(
            status: 404,
            description: $description,
            schema: $schema
        );
    }

    /**
     * Create conflict response (409)
     * 
     * @param string $description
     * @return self
     */
    public static function conflict(string $description = 'Conflict'): self
    {
        return new self(
            status: 409,
            description: $description
        );
    }

    /**
     * Create server error response (500)
     * 
     * @param string $description
     * @return self
     */
    public static function serverError(string $description = 'Internal server error'): self
    {
        return new self(
            status: 500,
            description: $description
        );
    }
}
