<?php

namespace Farm\Backend\App\Core\Documentation\Attributes;

use Attribute;

/**
 * #[ApiParam] - API parameter documentation attribute
 * 
 * Documents a single parameter (path, query, header, body field).
 * Multiple attributes can be used on the same method.
 * 
 * Usage:
 * ```php
 * #[ApiParam(
 *     name: "id",
 *     in: "path",
 *     type: "string",
 *     required: true,
 *     description: "User ID",
 *     example: "01HQZK1234567890"
 * )]
 * #[ApiParam(
 *     name: "include",
 *     in: "query",
 *     type: "string",
 *     required: false,
 *     description: "Related resources to include",
 *     example: "profile,posts"
 * )]
 * public function getUser(string $id) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiParam
{
    /**
     * @param string $name Parameter name
     * @param string $in Location: 'path', 'query', 'header', 'cookie', 'body'
     * @param string $type Data type: 'string', 'integer', 'number', 'boolean', 'array', 'object'
     * @param bool $required Whether parameter is required
     * @param string|null $description Parameter description
     * @param mixed $example Example value
     * @param string|null $format Format hint (email, uuid, date-time, etc.)
     * @param mixed $default Default value
     * @param array|null $enum Allowed values (for enum)
     * @param int|null $minimum Minimum value (for numbers)
     * @param int|null $maximum Maximum value (for numbers)
     * @param int|null $minLength Minimum length (for strings)
     * @param int|null $maxLength Maximum length (for strings)
     * @param string|null $pattern Regex pattern (for strings)
     * @param string|null $schema Schema class name (for objects)
     */
    public function __construct(
        public string $name,
        public string $in,
        public string $type = 'string',
        public bool $required = false,
        public ?string $description = null,
        public mixed $example = null,
        public ?string $format = null,
        public mixed $default = null,
        public ?array $enum = null,
        public ?int $minimum = null,
        public ?int $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?string $schema = null,
    ) {
        // Validate location
        $validLocations = ['path', 'query', 'header', 'cookie', 'body'];
        if (!in_array($in, $validLocations, true)) {
            throw new \InvalidArgumentException("Invalid location '$in'. Must be one of: " . implode(', ', $validLocations));
        }

        // Validate type
        $validTypes = ['string', 'integer', 'number', 'boolean', 'array', 'object'];
        if (!in_array($type, $validTypes, true)) {
            throw new \InvalidArgumentException("Invalid type '$type'. Must be one of: " . implode(', ', $validTypes));
        }

        // Path parameters are always required
        if ($in === 'path') {
            $this->required = true;
        }
    }

    /**
     * Convert to OpenAPI parameter object
     * 
     * @return array OpenAPI parameter
     */
    public function toOpenApi(): array
    {
        $param = [
            'name' => $this->name,
            'in' => $this->in,
            'required' => $this->required,
        ];

        if ($this->description !== null) {
            $param['description'] = $this->description;
        }

        if ($this->deprecated ?? false) {
            $param['deprecated'] = true;
        }

        // Build schema
        $schema = ['type' => $this->type];

        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        if ($this->example !== null) {
            $schema['example'] = $this->example;
        }

        // Reference schema class
        if ($this->schema !== null) {
            $schema = ['$ref' => '#/components/schemas/' . $this->getSchemaName($this->schema)];
        }

        $param['schema'] = $schema;

        return $param;
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
     * Create path parameter
     * 
     * @param string $name
     * @param string $description
     * @param string $example
     * @return self
     */
    public static function path(string $name, string $description, string $example): self
    {
        return new self(
            name: $name,
            in: 'path',
            type: 'string',
            required: true,
            description: $description,
            example: $example
        );
    }

    /**
     * Create query parameter
     * 
     * @param string $name
     * @param string $type
     * @param bool $required
     * @param string|null $description
     * @return self
     */
    public static function query(string $name, string $type = 'string', bool $required = false, ?string $description = null): self
    {
        return new self(
            name: $name,
            in: 'query',
            type: $type,
            required: $required,
            description: $description
        );
    }

    /**
     * Create header parameter
     * 
     * @param string $name
     * @param string $description
     * @param bool $required
     * @return self
     */
    public static function header(string $name, string $description, bool $required = false): self
    {
        return new self(
            name: $name,
            in: 'header',
            type: 'string',
            required: $required,
            description: $description
        );
    }
}
