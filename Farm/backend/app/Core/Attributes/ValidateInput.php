<?php

namespace PHPFrarm\Core\Attributes;

use Attribute;

/**
 * ValidateInput Attribute
 * 
 * Define input validation rules for a route at the method level.
 * Supports validation for headers, query params, path params, and body.
 * 
 * Usage:
 * ```php
 * #[ValidateInput(
 *     query: ['page' => 'integer|min_value:1', 'limit' => 'integer|range:1,100'],
 *     path: ['id' => 'required|uuid'],
 *     headers: ['X-Api-Key' => 'required|min:32'],
 *     body: [
 *         'name' => 'required|string|min:2|max:100',
 *         'email' => 'required|email',
 *         'age' => 'integer|range:18,120'
 *     ]
 * )]
 * public function update(string $id): void { }
 * ```
 * 
 * Validation Rules:
 * - required: Field must be present
 * - string/integer/float/boolean/array: Type validation
 * - min:N / max:N: String length or array count limits
 * - min_value:N / max_value:N: Numeric range
 * - range:min,max: Between two values (numeric or length)
 * - email/url/ip/uuid/ulid: Format validation
 * - pattern:regex: Custom regex pattern
 * - in:val1,val2: Allowed values
 * - date:format: Date format validation
 * 
 * @package PHPFrarm\Core\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ValidateInput
{
    /**
     * Create validation attribute
     * 
     * @param array $query Query parameter validation rules
     * @param array $path Path variable validation rules
     * @param array $headers Header validation rules
     * @param array $body Request body validation rules
     * @param bool $stopOnFirstError Stop validation on first error
     * @param string|null $errorMessage Custom error message
     */
    public function __construct(
        public array $query = [],
        public array $path = [],
        public array $headers = [],
        public array $body = [],
        public bool $stopOnFirstError = false,
        public ?string $errorMessage = null
    ) {}
    
    /**
     * Get all validation rules
     */
    public function getRules(): array
    {
        return [
            'query' => $this->query,
            'path' => $this->path,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }
    
    /**
     * Check if any rules are defined
     */
    public function hasRules(): bool
    {
        return !empty($this->query) 
            || !empty($this->path) 
            || !empty($this->headers) 
            || !empty($this->body);
    }
}
