<?php

namespace Farm\Backend\App\Core\Documentation\Attributes;

use Attribute;

/**
 * #[ApiDoc] - API endpoint documentation attribute
 * 
 * Documents an API endpoint with summary, description, tags, and security requirements.
 * Used by OpenAPI generator to create API documentation.
 * 
 * Usage:
 * ```php
 * #[ApiDoc(
 *     summary: "Get user by ID",
 *     description: "Retrieves a single user by their unique identifier",
 *     tags: ["Users", "Public"],
 *     security: ["bearerAuth"],
 *     deprecated: false
 * )]
 * public function getUser(string $id) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiDoc
{
    /**
     * @param string $summary Short summary (max 120 chars)
     * @param string|null $description Detailed description (markdown supported)
     * @param array<string> $tags Tags for grouping endpoints (e.g., ["Users", "Admin"])
     * @param array<string> $security Security schemes (e.g., ["bearerAuth", "apiKey"])
     * @param bool $deprecated Mark endpoint as deprecated
     * @param string|null $operationId Unique operation ID (auto-generated if null)
     * @param array<string, mixed> $extensions Custom OpenAPI extensions (x-*)
     */
    public function __construct(
        public string $summary,
        public ?string $description = null,
        public array $tags = [],
        public array $security = [],
        public bool $deprecated = false,
        public ?string $operationId = null,
        public array $extensions = [],
    ) {
        // Validate summary length
        if (strlen($summary) > 120) {
            throw new \InvalidArgumentException("Summary must be max 120 characters");
        }
    }

    /**
     * Convert to OpenAPI operation object
     * 
     * @return array OpenAPI operation
     */
    public function toOpenApi(): array
    {
        $operation = [
            'summary' => $this->summary,
        ];

        if ($this->description !== null) {
            $operation['description'] = $this->description;
        }

        if (!empty($this->tags)) {
            $operation['tags'] = $this->tags;
        }

        if (!empty($this->security)) {
            $operation['security'] = array_map(fn($scheme) => [$scheme => []], $this->security);
        }

        if ($this->deprecated) {
            $operation['deprecated'] = true;
        }

        if ($this->operationId !== null) {
            $operation['operationId'] = $this->operationId;
        }

        // Add custom extensions
        foreach ($this->extensions as $key => $value) {
            if (!str_starts_with($key, 'x-')) {
                $key = 'x-' . $key;
            }
            $operation[$key] = $value;
        }

        return $operation;
    }

    /**
     * Create minimal documentation (summary only)
     * 
     * @param string $summary
     * @return self
     */
    public static function minimal(string $summary): self
    {
        return new self(summary: $summary);
    }

    /**
     * Create standard documentation (summary + description + tags)
     * 
     * @param string $summary
     * @param string $description
     * @param array $tags
     * @return self
     */
    public static function standard(string $summary, string $description, array $tags): self
    {
        return new self(
            summary: $summary,
            description: $description,
            tags: $tags
        );
    }

    /**
     * Create secured endpoint documentation
     * 
     * @param string $summary
     * @param array $security
     * @param array $tags
     * @return self
     */
    public static function secured(string $summary, array $security, array $tags = []): self
    {
        return new self(
            summary: $summary,
            tags: $tags,
            security: $security
        );
    }
}
