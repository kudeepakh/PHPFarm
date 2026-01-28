<?php

declare(strict_types=1);

namespace App\Core\Data;

/**
 * Data Integrity Validator
 * 
 * Validates data integrity constraints across the application.
 * Ensures data consistency, referential integrity, and business rule compliance.
 * 
 * @package PHPFrarm
 * @module Data Standards (Module 13)
 */
class DataIntegrityValidator
{
    /**
     * Validation rules by entity type
     */
    private array $entityRules = [];
    
    /**
     * Custom validators
     */
    private array $customValidators = [];
    
    /**
     * Validation errors
     */
    private array $errors = [];

    /**
     * Register validation rules for an entity type
     */
    public function registerEntityRules(string $entityType, array $rules): void
    {
        $this->entityRules[$entityType] = array_merge(
            $this->entityRules[$entityType] ?? [],
            $rules
        );
    }

    /**
     * Register a custom validator
     */
    public function registerValidator(string $name, callable $validator): void
    {
        $this->customValidators[$name] = $validator;
    }

    /**
     * Validate entity data
     */
    public function validate(string $entityType, array $data, array $context = []): bool
    {
        $this->errors = [];
        
        $rules = $this->entityRules[$entityType] ?? [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ((array) $fieldRules as $rule => $config) {
                if (is_int($rule)) {
                    $rule = $config;
                    $config = true;
                }
                
                $result = $this->validateRule($rule, $field, $value, $config, $data, $context);
                
                if ($result !== true) {
                    $this->errors[$field][] = $result;
                }
            }
        }
        
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validate a single rule
     */
    private function validateRule(
        string $rule,
        string $field,
        mixed $value,
        mixed $config,
        array $data,
        array $context
    ): bool|string {
        return match ($rule) {
            'required' => $this->validateRequired($field, $value),
            'uuid' => $this->validateUuid($field, $value),
            'ulid' => $this->validateUlid($field, $value),
            'utc_timestamp' => $this->validateUtcTimestamp($field, $value),
            'soft_delete_valid' => $this->validateSoftDelete($field, $value, $data),
            'optimistic_lock' => $this->validateOptimisticLock($field, $value, $config, $context),
            'referential_integrity' => $this->validateReferentialIntegrity($field, $value, $config, $context),
            'unique' => $this->validateUniqueness($field, $value, $config, $context),
            'immutable' => $this->validateImmutable($field, $value, $config, $context),
            'enum' => $this->validateEnum($field, $value, $config),
            'range' => $this->validateRange($field, $value, $config),
            'pattern' => $this->validatePattern($field, $value, $config),
            'consistency' => $this->validateConsistency($field, $value, $config, $data),
            'custom' => $this->validateCustom($field, $value, $config, $data, $context),
            default => $this->runCustomValidator($rule, $field, $value, $config, $data, $context)
        };
    }

    /**
     * Validate required field
     */
    private function validateRequired(string $field, mixed $value): bool|string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "{$field} is required";
        }
        return true;
    }

    /**
     * Validate UUID format
     */
    private function validateUuid(string $field, mixed $value): bool|string
    {
        if ($value === null) {
            return true;
        }
        
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        
        if (!preg_match($pattern, $value)) {
            return "{$field} must be a valid UUID";
        }
        
        return true;
    }

    /**
     * Validate ULID format
     */
    private function validateUlid(string $field, mixed $value): bool|string
    {
        if ($value === null) {
            return true;
        }
        
        // ULID is 26 characters, Crockford's Base32
        $pattern = '/^[0-9A-HJKMNP-TV-Z]{26}$/';
        
        if (!preg_match($pattern, strtoupper($value))) {
            return "{$field} must be a valid ULID";
        }
        
        return true;
    }

    /**
     * Validate UTC timestamp
     */
    private function validateUtcTimestamp(string $field, mixed $value): bool|string
    {
        if ($value === null) {
            return true;
        }
        
        // Check if it's a valid ISO 8601 UTC timestamp
        if ($value instanceof \DateTimeInterface) {
            $tz = $value->getTimezone()->getName();
            if ($tz !== 'UTC' && $tz !== '+00:00' && $tz !== 'Z') {
                return "{$field} must be in UTC timezone";
            }
            return true;
        }
        
        // String format validation
        if (is_string($value)) {
            // Must end with Z or +00:00
            if (!preg_match('/[Zz]$|[+-]00:00$/', $value)) {
                return "{$field} must be a UTC timestamp (ending with Z or +00:00)";
            }
            
            // Try to parse it
            try {
                new \DateTimeImmutable($value);
            } catch (\Exception $e) {
                return "{$field} must be a valid timestamp";
            }
        }
        
        return true;
    }

    /**
     * Validate soft delete constraints
     */
    private function validateSoftDelete(string $field, mixed $value, array $data): bool|string
    {
        if (!isset($data['deleted_at'])) {
            return true;
        }
        
        // If deleted_at is set, ensure deleted_by is also set
        if ($data['deleted_at'] !== null && empty($data['deleted_by'])) {
            return "deleted_by is required when soft deleting";
        }
        
        // Ensure deleted_at is not in the future
        if ($data['deleted_at'] !== null) {
            $deletedAt = new \DateTimeImmutable($data['deleted_at']);
            if ($deletedAt > new \DateTimeImmutable()) {
                return "deleted_at cannot be in the future";
            }
        }
        
        return true;
    }

    /**
     * Validate optimistic locking
     */
    private function validateOptimisticLock(
        string $field,
        mixed $value,
        mixed $config,
        array $context
    ): bool|string {
        if (!isset($context['expected_version'])) {
            return true; // New entity, no version check needed
        }
        
        $expectedVersion = $context['expected_version'];
        $currentVersion = $context['current_version'] ?? null;
        
        if ($currentVersion !== null && $currentVersion !== $expectedVersion) {
            return "Optimistic lock conflict: expected version {$expectedVersion}, current version is {$currentVersion}";
        }
        
        return true;
    }

    /**
     * Validate referential integrity
     */
    private function validateReferentialIntegrity(
        string $field,
        mixed $value,
        mixed $config,
        array $context
    ): bool|string {
        if ($value === null) {
            return true;
        }
        
        // Config should specify table and column to check
        // This would normally do a DB lookup
        $referencedTable = $config['table'] ?? null;
        $referencedColumn = $config['column'] ?? 'id';
        
        if (!$referencedTable) {
            return true;
        }
        
        // Check if the integrity checker is provided in context
        $checker = $context['integrity_checker'] ?? null;
        
        if ($checker && is_callable($checker)) {
            $exists = $checker($referencedTable, $referencedColumn, $value);
            if (!$exists) {
                return "{$field} references non-existent {$referencedTable}.{$referencedColumn} = {$value}";
            }
        }
        
        return true;
    }

    /**
     * Validate uniqueness
     */
    private function validateUniqueness(
        string $field,
        mixed $value,
        mixed $config,
        array $context
    ): bool|string {
        if ($value === null) {
            return true;
        }
        
        $uniqueChecker = $context['unique_checker'] ?? null;
        $currentId = $context['current_id'] ?? null;
        
        if ($uniqueChecker && is_callable($uniqueChecker)) {
            $isDuplicate = $uniqueChecker($field, $value, $currentId);
            if ($isDuplicate) {
                return "{$field} must be unique";
            }
        }
        
        return true;
    }

    /**
     * Validate immutable fields
     */
    private function validateImmutable(
        string $field,
        mixed $value,
        mixed $config,
        array $context
    ): bool|string {
        $originalValue = $context['original'][$field] ?? null;
        
        // If this is an update and the original value exists
        if ($originalValue !== null && $value !== $originalValue) {
            return "{$field} is immutable and cannot be changed";
        }
        
        return true;
    }

    /**
     * Validate enum values
     */
    private function validateEnum(string $field, mixed $value, mixed $config): bool|string
    {
        if ($value === null) {
            return true;
        }
        
        $allowedValues = is_array($config) ? $config : [$config];
        
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            return "{$field} must be one of: {$allowed}";
        }
        
        return true;
    }

    /**
     * Validate numeric range
     */
    private function validateRange(string $field, mixed $value, mixed $config): bool|string
    {
        if ($value === null || !is_numeric($value)) {
            return true;
        }
        
        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;
        
        if ($min !== null && $value < $min) {
            return "{$field} must be at least {$min}";
        }
        
        if ($max !== null && $value > $max) {
            return "{$field} must be at most {$max}";
        }
        
        return true;
    }

    /**
     * Validate pattern/regex
     */
    private function validatePattern(string $field, mixed $value, mixed $config): bool|string
    {
        if ($value === null || !is_string($value)) {
            return true;
        }
        
        $pattern = is_string($config) ? $config : ($config['pattern'] ?? null);
        $message = is_array($config) ? ($config['message'] ?? null) : null;
        
        if ($pattern && !preg_match($pattern, $value)) {
            return $message ?? "{$field} format is invalid";
        }
        
        return true;
    }

    /**
     * Validate data consistency between fields
     */
    private function validateConsistency(
        string $field,
        mixed $value,
        mixed $config,
        array $data
    ): bool|string {
        if (!is_array($config)) {
            return true;
        }
        
        // Check dependent field values
        foreach ($config as $rule) {
            $dependsOn = $rule['depends_on'] ?? null;
            $condition = $rule['condition'] ?? null;
            $message = $rule['message'] ?? null;
            
            if (!$dependsOn || !$condition) {
                continue;
            }
            
            $dependentValue = $data[$dependsOn] ?? null;
            
            $result = match ($condition) {
                'not_null_if' => ($dependentValue !== null && $value === null)
                    ? ($message ?? "{$field} is required when {$dependsOn} is set")
                    : true,
                'null_if' => ($dependentValue !== null && $value !== null)
                    ? ($message ?? "{$field} must be null when {$dependsOn} is set")
                    : true,
                'greater_than' => ($dependentValue !== null && $value !== null && $value <= $dependentValue)
                    ? ($message ?? "{$field} must be greater than {$dependsOn}")
                    : true,
                'less_than' => ($dependentValue !== null && $value !== null && $value >= $dependentValue)
                    ? ($message ?? "{$field} must be less than {$dependsOn}")
                    : true,
                default => true
            };
            
            if ($result !== true) {
                return $result;
            }
        }
        
        return true;
    }

    /**
     * Validate using a custom inline function
     */
    private function validateCustom(
        string $field,
        mixed $value,
        mixed $config,
        array $data,
        array $context
    ): bool|string {
        if (!is_callable($config)) {
            return true;
        }
        
        return $config($value, $field, $data, $context);
    }

    /**
     * Run a registered custom validator
     */
    private function runCustomValidator(
        string $rule,
        string $field,
        mixed $value,
        mixed $config,
        array $data,
        array $context
    ): bool|string {
        $validator = $this->customValidators[$rule] ?? null;
        
        if (!$validator) {
            return true; // Unknown rule, skip
        }
        
        return $validator($value, $field, $config, $data, $context);
    }

    /**
     * Validate a batch of entities
     */
    public function validateBatch(string $entityType, array $entities, array $context = []): array
    {
        $results = [];
        
        foreach ($entities as $index => $entity) {
            $isValid = $this->validate($entityType, $entity, $context);
            
            $results[$index] = [
                'valid' => $isValid,
                'errors' => $isValid ? [] : $this->getErrors()
            ];
        }
        
        return $results;
    }

    /**
     * Get pre-configured entity rules for common PHPFrarm entities
     */
    public static function getDefaultEntityRules(): array
    {
        return [
            'base_entity' => [
                'id' => ['required', 'uuid'],
                'created_at' => ['required', 'utc_timestamp'],
                'updated_at' => ['utc_timestamp'],
                'deleted_at' => ['utc_timestamp', 'soft_delete_valid'],
                'version' => ['optimistic_lock']
            ],
            'user' => [
                'id' => ['required', 'uuid'],
                'email' => ['required', 'unique'],
                'status' => ['required', 'enum' => ['active', 'inactive', 'suspended', 'locked', 'pending_verification']],
                'created_at' => ['required', 'utc_timestamp'],
                'email_verified_at' => ['utc_timestamp']
            ],
            'audit_log' => [
                'id' => ['required', 'ulid'],
                'user_id' => ['uuid', 'referential_integrity' => ['table' => 'users']],
                'action' => ['required'],
                'timestamp' => ['required', 'utc_timestamp'],
                'correlation_id' => ['uuid']
            ]
        ];
    }
}
