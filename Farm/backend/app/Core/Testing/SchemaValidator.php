<?php

namespace Farm\Backend\App\Core\Testing;

/**
 * JSON Schema Validator
 * 
 * Validates data against JSON Schema (Draft 7).
 * Used internally by ContractTester.
 * 
 * Supports:
 * - Type validation
 * - Property validation
 * - Array validation
 * - String constraints (pattern, minLength, maxLength)
 * - Number constraints (minimum, maximum)
 * - Required properties
 * - Enum validation
 * - $ref resolution
 */
class SchemaValidator
{
    private array $errors = [];

    /**
     * Validate data against schema
     * 
     * @param mixed $data Data to validate
     * @param array $schema JSON Schema
     * @param array $spec Full OpenAPI spec for $ref resolution
     * @return ValidationResult
     */
    public function validate($data, array $schema, array $spec = []): ValidationResult
    {
        $this->errors = [];
        
        $this->validateSchema($data, $schema, $spec, '');
        
        if (empty($this->errors)) {
            return ValidationResult::pass();
        }
        
        return ValidationResult::failMultiple($this->errors);
    }

    /**
     * Internal validation logic
     * 
     * @param mixed $data
     * @param array $schema
     * @param array $spec
     * @param string $path
     * @return void
     */
    private function validateSchema($data, array $schema, array $spec, string $path): void
    {
        // Resolve $ref
        if (isset($schema['$ref'])) {
            $schema = $this->resolveRef($schema['$ref'], $spec);
            if ($schema === null) {
                $this->errors[] = "Cannot resolve reference: {$schema['$ref']}";
                return;
            }
        }

        // Validate type
        if (isset($schema['type'])) {
            if (!$this->validateType($data, $schema['type'], $path)) {
                return;
            }
        }

        // Validate enum
        if (isset($schema['enum'])) {
            if (!in_array($data, $schema['enum'], true)) {
                $this->errors[] = "$path: Value must be one of: " . implode(', ', $schema['enum']);
            }
        }

        // Type-specific validation
        switch ($schema['type'] ?? null) {
            case 'object':
                $this->validateObject($data, $schema, $spec, $path);
                break;
            
            case 'array':
                $this->validateArray($data, $schema, $spec, $path);
                break;
            
            case 'string':
                $this->validateString($data, $schema, $path);
                break;
            
            case 'number':
            case 'integer':
                $this->validateNumber($data, $schema, $path);
                break;
        }
    }

    /**
     * Validate data type
     * 
     * @param mixed $data
     * @param string $expectedType
     * @param string $path
     * @return bool
     */
    private function validateType($data, string $expectedType, string $path): bool
    {
        $actualType = $this->getType($data);
        
        if ($expectedType === 'integer' && $actualType === 'number') {
            if (!is_int($data)) {
                $this->errors[] = "$path: Expected integer, got float";
                return false;
            }
            return true;
        }
        
        if ($actualType !== $expectedType) {
            $this->errors[] = "$path: Expected $expectedType, got $actualType";
            return false;
        }
        
        return true;
    }

    /**
     * Get JSON type of value
     * 
     * @param mixed $value
     * @return string
     */
    private function getType($value): string
    {
        if (is_null($value)) return 'null';
        if (is_bool($value)) return 'boolean';
        if (is_int($value) || is_float($value)) return 'number';
        if (is_string($value)) return 'string';
        if (is_array($value)) {
            // Check if associative or indexed
            if (empty($value)) return 'array';
            return array_keys($value) === range(0, count($value) - 1) ? 'array' : 'object';
        }
        if (is_object($value)) return 'object';
        
        return 'unknown';
    }

    /**
     * Validate object
     * 
     * @param mixed $data
     * @param array $schema
     * @param array $spec
     * @param string $path
     * @return void
     */
    private function validateObject($data, array $schema, array $spec, string $path): void
    {
        if (!is_array($data) || !$this->isAssociative($data)) {
            $this->errors[] = "$path: Expected object";
            return;
        }

        // Required properties
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requiredProp) {
                if (!isset($data[$requiredProp])) {
                    $this->errors[] = "$path: Required property missing: $requiredProp";
                }
            }
        }

        // Validate properties
        if (isset($schema['properties'])) {
            foreach ($data as $key => $value) {
                if (isset($schema['properties'][$key])) {
                    $propPath = $path ? "$path.$key" : $key;
                    $this->validateSchema($value, $schema['properties'][$key], $spec, $propPath);
                } elseif (isset($schema['additionalProperties'])) {
                    if ($schema['additionalProperties'] === false) {
                        $this->errors[] = "$path: Unexpected property: $key";
                    }
                }
            }
        }
    }

    /**
     * Validate array
     * 
     * @param mixed $data
     * @param array $schema
     * @param array $spec
     * @param string $path
     * @return void
     */
    private function validateArray($data, array $schema, array $spec, string $path): void
    {
        if (!is_array($data) || $this->isAssociative($data)) {
            $this->errors[] = "$path: Expected array";
            return;
        }

        // Min/max items
        if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
            $this->errors[] = "$path: Array must have at least {$schema['minItems']} items";
        }
        
        if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
            $this->errors[] = "$path: Array must have at most {$schema['maxItems']} items";
        }

        // Validate items
        if (isset($schema['items'])) {
            foreach ($data as $index => $item) {
                $itemPath = "$path[$index]";
                $this->validateSchema($item, $schema['items'], $spec, $itemPath);
            }
        }
    }

    /**
     * Validate string
     * 
     * @param mixed $data
     * @param array $schema
     * @param string $path
     * @return void
     */
    private function validateString($data, array $schema, string $path): void
    {
        if (!is_string($data)) {
            return; // Type error already reported
        }

        // Min/max length
        if (isset($schema['minLength']) && strlen($data) < $schema['minLength']) {
            $this->errors[] = "$path: String must be at least {$schema['minLength']} characters";
        }
        
        if (isset($schema['maxLength']) && strlen($data) > $schema['maxLength']) {
            $this->errors[] = "$path: String must be at most {$schema['maxLength']} characters";
        }

        // Pattern
        if (isset($schema['pattern'])) {
            if (!preg_match("/{$schema['pattern']}/", $data)) {
                $this->errors[] = "$path: String does not match pattern: {$schema['pattern']}";
            }
        }

        // Format (basic validation)
        if (isset($schema['format'])) {
            $this->validateFormat($data, $schema['format'], $path);
        }
    }

    /**
     * Validate number
     * 
     * @param mixed $data
     * @param array $schema
     * @param string $path
     * @return void
     */
    private function validateNumber($data, array $schema, string $path): void
    {
        if (!is_numeric($data)) {
            return; // Type error already reported
        }

        // Minimum
        if (isset($schema['minimum'])) {
            if ($data < $schema['minimum']) {
                $this->errors[] = "$path: Number must be >= {$schema['minimum']}";
            }
        }

        // Maximum
        if (isset($schema['maximum'])) {
            if ($data > $schema['maximum']) {
                $this->errors[] = "$path: Number must be <= {$schema['maximum']}";
            }
        }
    }

    /**
     * Validate string format
     * 
     * @param string $data
     * @param string $format
     * @param string $path
     * @return void
     */
    private function validateFormat(string $data, string $format, string $path): void
    {
        switch ($format) {
            case 'email':
                if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[] = "$path: Invalid email format";
                }
                break;
            
            case 'uuid':
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $data)) {
                    $this->errors[] = "$path: Invalid UUID format";
                }
                break;
            
            case 'date-time':
                if (strtotime($data) === false) {
                    $this->errors[] = "$path: Invalid date-time format";
                }
                break;
        }
    }

    /**
     * Resolve $ref reference
     * 
     * @param string $ref
     * @param array $spec
     * @return array|null
     */
    private function resolveRef(string $ref, array $spec): ?array
    {
        // Remove leading #/
        $ref = ltrim($ref, '#/');
        
        // Split path
        $parts = explode('/', $ref);
        
        // Traverse spec
        $current = $spec;
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }
        
        return $current;
    }

    /**
     * Check if array is associative
     * 
     * @param array $array
     * @return bool
     */
    private function isAssociative(array $array): bool
    {
        if (empty($array)) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
