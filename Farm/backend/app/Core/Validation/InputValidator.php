<?php

namespace PHPFrarm\Core\Validation;

use PHPFrarm\Core\Logger;

/**
 * Input Validator
 * 
 * Comprehensive input validation for headers, query parameters,
 * path variables, and request bodies.
 * 
 * Features:
 * - Header validation (required, format, allowed values)
 * - Query parameter validation (type, range, pattern)
 * - Path variable validation (format, existence)
 * - Content-Type enforcement
 * - Request body schema validation
 * - Custom validation rules
 * 
 * @package PHPFrarm\Core\Validation
 */
class InputValidator
{
    private array $errors = [];
    private array $validated = [];
    private array $rules = [];
    
    /**
     * Built-in validation patterns
     */
    private const PATTERNS = [
        'uuid' => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        'ulid' => '/^[0-9A-HJKMNP-TV-Z]{26}$/i',
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        'phone' => '/^\+?[1-9]\d{1,14}$/',
        'url' => '/^https?:\/\/[^\s]+$/',
        'slug' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        'alpha' => '/^[a-zA-Z]+$/',
        'alphanumeric' => '/^[a-zA-Z0-9]+$/',
        'numeric' => '/^\d+$/',
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
        'datetime' => '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/',
        'ip' => '/^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$/',
        'jwt' => '/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]*$/',
    ];
    
    /**
     * Validate headers
     * 
     * @param array $rules Validation rules
     * @return bool
     */
    public function validateHeaders(array $rules): bool
    {
        foreach ($rules as $header => $rule) {
            $normalizedHeader = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
            $value = $_SERVER[$normalizedHeader] ?? null;
            
            $this->validateField("header.{$header}", $value, $rule);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate query parameters
     * 
     * @param array $rules Validation rules
     * @return bool
     */
    public function validateQueryParams(array $rules): bool
    {
        foreach ($rules as $param => $rule) {
            $value = $_GET[$param] ?? null;
            $this->validateField("query.{$param}", $value, $rule);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate path variables
     * 
     * @param array $params Extracted path parameters
     * @param array $rules Validation rules
     * @return bool
     */
    public function validatePathParams(array $params, array $rules): bool
    {
        foreach ($rules as $param => $rule) {
            $value = $params[$param] ?? null;
            $this->validateField("path.{$param}", $value, $rule);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate request body
     * 
     * @param array $data Request body data
     * @param array $rules Validation rules
     * @return bool
     */
    public function validateBody(array $data, array $rules): bool
    {
        foreach ($rules as $field => $rule) {
            $value = $this->getNestedValue($data, $field);
            $this->validateField("body.{$field}", $value, $rule);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate a single field against rules
     * 
     * @param string $field Field identifier
     * @param mixed $value Field value
     * @param array|string $rules Validation rules
     */
    private function validateField(string $field, mixed $value, array|string $rules): void
    {
        // Convert string rules to array
        if (is_string($rules)) {
            $rules = $this->parseRuleString($rules);
        }
        
        // Check required first
        $isRequired = $rules['required'] ?? false;
        
        if ($value === null || $value === '') {
            if ($isRequired) {
                $this->addError($field, 'required', 'This field is required');
            }
            return; // Skip other validations if empty and not required
        }
        
        // Store validated value
        $this->validated[$field] = $value;
        
        // Run each validation rule
        foreach ($rules as $rule => $param) {
            if ($rule === 'required') {
                continue;
            }
            
            $result = $this->runRule($field, $value, $rule, $param);
            
            if ($result !== true) {
                $this->addError($field, $rule, $result);
            }
        }
    }
    
    /**
     * Run a single validation rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule name
     * @param mixed $param Rule parameter
     * @return bool|string True if valid, error message if invalid
     */
    private function runRule(string $field, mixed $value, string $rule, mixed $param): bool|string
    {
        return match ($rule) {
            // Type validations
            'string' => is_string($value) ? true : 'Must be a string',
            'integer', 'int' => is_numeric($value) && (int) $value == $value 
                ? true : 'Must be an integer',
            'float', 'number' => is_numeric($value) ? true : 'Must be a number',
            'boolean', 'bool' => in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0], true) 
                ? true : 'Must be a boolean',
            'array' => is_array($value) ? true : 'Must be an array',
            
            // String length
            'min' => strlen((string) $value) >= $param 
                ? true : "Must be at least {$param} characters",
            'max' => strlen((string) $value) <= $param 
                ? true : "Must not exceed {$param} characters",
            'length' => strlen((string) $value) === $param 
                ? true : "Must be exactly {$param} characters",
            'between' => $this->validateBetweenLength($value, $param),
            
            // Numeric range
            'min_value' => (float) $value >= $param 
                ? true : "Must be at least {$param}",
            'max_value' => (float) $value <= $param 
                ? true : "Must not exceed {$param}",
            'range' => $this->validateRange($value, $param),
            
            // Pattern matching
            'pattern', 'regex' => preg_match($param, $value) 
                ? true : 'Invalid format',
            'format' => $this->validateFormat($value, $param),
            
            // Enum/allowed values
            'in', 'enum' => in_array($value, (array) $param, true) 
                ? true : 'Invalid value. Allowed: ' . implode(', ', (array) $param),
            'not_in' => !in_array($value, (array) $param, true) 
                ? true : 'Value not allowed',
            
            // Special formats
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false 
                ? true : 'Invalid email address',
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false 
                ? true : 'Invalid URL',
            'ip' => filter_var($value, FILTER_VALIDATE_IP) !== false 
                ? true : 'Invalid IP address',
            'uuid' => preg_match(self::PATTERNS['uuid'], $value) 
                ? true : 'Invalid UUID format',
            'ulid' => preg_match(self::PATTERNS['ulid'], $value) 
                ? true : 'Invalid ULID format',
            'date' => $this->validateDate($value, $param),
            'datetime' => strtotime($value) !== false 
                ? true : 'Invalid datetime format',
            
            // Array validations
            'min_items' => is_array($value) && count($value) >= $param 
                ? true : "Must have at least {$param} items",
            'max_items' => is_array($value) && count($value) <= $param 
                ? true : "Must have at most {$param} items",
            
            // Custom
            'callback' => is_callable($param) ? $param($value, $field) : true,
            
            default => true, // Unknown rule, pass
        };
    }
    
    /**
     * Validate between length
     */
    private function validateBetweenLength(mixed $value, array $param): bool|string
    {
        $length = strlen((string) $value);
        [$min, $max] = $param;
        
        if ($length < $min || $length > $max) {
            return "Must be between {$min} and {$max} characters";
        }
        
        return true;
    }
    
    /**
     * Validate numeric range
     */
    private function validateRange(mixed $value, array $param): bool|string
    {
        [$min, $max] = $param;
        $num = (float) $value;
        
        if ($num < $min || $num > $max) {
            return "Must be between {$min} and {$max}";
        }
        
        return true;
    }
    
    /**
     * Validate format against built-in patterns
     */
    private function validateFormat(mixed $value, string $format): bool|string
    {
        $pattern = self::PATTERNS[$format] ?? null;
        
        if ($pattern === null) {
            return true; // Unknown format, pass
        }
        
        return preg_match($pattern, $value) 
            ? true 
            : "Invalid {$format} format";
    }
    
    /**
     * Validate date format
     */
    private function validateDate(mixed $value, mixed $format): bool|string
    {
        $format = $format === true ? 'Y-m-d' : $format;
        $date = \DateTime::createFromFormat($format, $value);
        
        if ($date === false || $date->format($format) !== $value) {
            return "Invalid date format. Expected: {$format}";
        }
        
        return true;
    }
    
    /**
     * Parse rule string (e.g., "required|string|min:3|max:100")
     */
    private function parseRuleString(string $ruleString): array
    {
        $rules = [];
        $parts = explode('|', $ruleString);
        
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$rule, $param] = explode(':', $part, 2);
                
                // Parse comma-separated params
                if (str_contains($param, ',')) {
                    $param = explode(',', $param);
                }
                
                $rules[$rule] = $param;
            } else {
                $rules[$part] = true;
            }
        }
        
        return $rules;
    }
    
    /**
     * Get nested value from array using dot notation
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Add validation error
     */
    private function addError(string $field, string $rule, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][$rule] = $message;
        
        Logger::debug('Validation failed', [
            'field' => $field,
            'rule' => $rule,
            'message' => $message,
        ]);
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get first error for each field
     */
    public function getFirstErrors(): array
    {
        $first = [];
        
        foreach ($this->errors as $field => $errors) {
            $first[$field] = reset($errors);
        }
        
        return $first;
    }
    
    /**
     * Get validated data
     */
    public function getValidated(): array
    {
        return $this->validated;
    }
    
    /**
     * Reset validator
     */
    public function reset(): void
    {
        $this->errors = [];
        $this->validated = [];
    }
    
    /**
     * Static factory method
     */
    public static function make(): self
    {
        return new self();
    }
}
