<?php

namespace Farm\Backend\App\Core\Documentation;

use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Schema Extractor
 * 
 * Extracts OpenAPI schemas from PHP DTO classes by reflecting
 * on class properties and their types/attributes.
 * 
 * Usage:
 * ```php
 * $extractor = new SchemaExtractor();
 * $extractor->extractFromClass(UserDTO::class);
 * $schemas = $extractor->getSchemas();
 * ```
 */
class SchemaExtractor
{
    private array $schemas = [];
    private array $processed = [];

    /**
     * Extract schema from DTO class
     * 
     * @param string $className
     * @return void
     */
    public function extractFromClass(string $className): void
    {
        // Avoid processing same class twice
        if (isset($this->processed[$className])) {
            return;
        }
        
        $this->processed[$className] = true;
        
        if (!class_exists($className)) {
            return;
        }
        
        $reflection = new ReflectionClass($className);
        $schemaName = $reflection->getShortName();
        
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];
        
        $required = [];
        
        // Extract properties
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $propertySchema = $this->extractPropertySchema($property);
            
            $schema['properties'][$propertyName] = $propertySchema;
            
            // Check if required (non-nullable, no default value)
            if (!$property->hasDefaultValue() && !$this->isNullable($property)) {
                $required[] = $propertyName;
            }
        }
        
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        // Add description from docblock
        $docComment = $reflection->getDocComment();
        if ($docComment !== false) {
            $description = $this->extractDescription($docComment);
            if ($description !== null) {
                $schema['description'] = $description;
            }
        }
        
        $this->schemas[$schemaName] = $schema;
    }

    /**
     * Extract schema for a single property
     * 
     * @param ReflectionProperty $property
     * @return array
     */
    private function extractPropertySchema(ReflectionProperty $property): array
    {
        $schema = [];
        
        // Get type
        $type = $property->getType();
        
        if ($type === null) {
            $schema['type'] = 'string';
        } elseif ($type instanceof ReflectionNamedType) {
            $schema = $this->mapPhpTypeToOpenApi($type->getName());
            
            // If it's a class type, extract nested schema
            if (!$type->isBuiltin() && class_exists($type->getName())) {
                $this->extractFromClass($type->getName());
                $schema = ['$ref' => '#/components/schemas/' . basename(str_replace('\\', '/', $type->getName()))];
            }
        } elseif ($type instanceof ReflectionUnionType) {
            // Handle union types (e.g., string|int)
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                if ($unionType->getName() !== 'null') {
                    $types[] = $this->mapPhpTypeToOpenApi($unionType->getName())['type'];
                }
            }
            
            if (count($types) === 1) {
                $schema['type'] = $types[0];
            } else {
                $schema['oneOf'] = array_map(fn($t) => ['type' => $t], $types);
            }
        }
        
        // Extract description from docblock
        $docComment = $property->getDocComment();
        if ($docComment !== false) {
            $description = $this->extractDescription($docComment);
            if ($description !== null) {
                $schema['description'] = $description;
            }
            
            // Extract @example tag
            $example = $this->extractExample($docComment);
            if ($example !== null) {
                $schema['example'] = $example;
            }
            
            // Extract @format tag
            $format = $this->extractFormat($docComment);
            if ($format !== null) {
                $schema['format'] = $format;
            }
        }
        
        // Check for default value
        if ($property->hasDefaultValue()) {
            $schema['default'] = $property->getDefaultValue();
        }
        
        return $schema;
    }

    /**
     * Map PHP type to OpenAPI type
     * 
     * @param string $phpType
     * @return array
     */
    private function mapPhpTypeToOpenApi(string $phpType): array
    {
        return match ($phpType) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'double' => ['type' => 'number', 'format' => 'double'],
            'bool' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            'object' => ['type' => 'object'],
            'mixed' => ['type' => 'string'],
            default => ['type' => 'string'],
        };
    }

    /**
     * Check if property is nullable
     * 
     * @param ReflectionProperty $property
     * @return bool
     */
    private function isNullable(ReflectionProperty $property): bool
    {
        $type = $property->getType();
        
        if ($type === null) {
            return true;
        }
        
        return $type->allowsNull();
    }

    /**
     * Extract description from docblock
     * 
     * @param string $docComment
     * @return string|null
     */
    private function extractDescription(string $docComment): ?string
    {
        // Remove /** and */
        $docComment = trim($docComment, "/* \t\n\r");
        
        // Split into lines
        $lines = explode("\n", $docComment);
        $description = [];
        
        foreach ($lines as $line) {
            $line = trim($line, "* \t");
            
            // Stop at first @tag
            if (str_starts_with($line, '@')) {
                break;
            }
            
            if (!empty($line)) {
                $description[] = $line;
            }
        }
        
        return !empty($description) ? implode(' ', $description) : null;
    }

    /**
     * Extract @example from docblock
     * 
     * @param string $docComment
     * @return mixed
     */
    private function extractExample(string $docComment): mixed
    {
        if (preg_match('/@example\s+(.+)/', $docComment, $matches)) {
            $example = trim($matches[1]);
            
            // Try to parse as JSON
            $decoded = json_decode($example, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // Return as string
            return $example;
        }
        
        return null;
    }

    /**
     * Extract @format from docblock
     * 
     * @param string $docComment
     * @return string|null
     */
    private function extractFormat(string $docComment): ?string
    {
        if (preg_match('/@format\s+(\S+)/', $docComment, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get all extracted schemas
     * 
     * @return array
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Reset extracted schemas
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->schemas = [];
        $this->processed = [];
    }
}
