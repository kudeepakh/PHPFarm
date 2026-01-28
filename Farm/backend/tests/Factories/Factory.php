<?php

namespace Farm\Backend\Tests\Factories;

use PHPFrarm\Core\Database;

/**
 * Base Factory
 * 
 * Provides test data generation with states and relationships.
 * All factories extend this base class.
 * 
 * Usage:
 * ```php
 * $user = UserFactory::new()
 *     ->withState('verified')
 *     ->create();
 * 
 * $users = UserFactory::new()->createMany(10);
 * ```
 */
abstract class Factory
{
    protected array $attributes = [];
    protected array $states = [];

    /**
     * Define default attributes
     * 
     * @return array
     */
    abstract protected function definition(): array;

    /**
     * Get model class name
     * 
     * @return string
     */
    abstract protected function model(): string;

    /**
     * Create new factory instance
     * 
     * @return static
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Set custom attributes
     * 
     * @param array $attributes
     * @return self
     */
    public function with(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Apply named state
     * 
     * @param string $state
     * @return self
     */
    public function withState(string $state): self
    {
        if (method_exists($this, $state)) {
            $this->$state();
        }
        
        return $this;
    }

    /**
     * Create single model instance
     * 
     * @param array $attributes
     * @return array
     */
    public function create(array $attributes = []): array
    {
        $data = $this->make($attributes);
        
        // Insert into database
        $model = $this->model();
        $table = $this->getTableName($model);
        
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        if (!empty($data['id'])) {
            $updateColumns = array_filter($columns, fn($col) => $col !== 'id');
            if (!empty($updateColumns)) {
                $updates = implode(', ', array_map(fn($col) => "`$col` = VALUES(`$col`)", $updateColumns));
                $sql .= " ON DUPLICATE KEY UPDATE $updates";
            }
        }
        
        $params = [];
        foreach ($data as $key => $value) {
            $params[":$key"] = $value;
        }

        Database::enableRawQueries();
        try {
            Database::execute($sql, $params);
            if (empty($data['id'])) {
                $data['id'] = Database::lastInsertId();
            }
        } finally {
            Database::disableRawQueries();
        }
        
        return $data;
    }

    /**
     * Create multiple model instances
     * 
     * @param int $count
     * @param array $attributes
     * @return array
     */
    public function createMany(int $count, array $attributes = []): array
    {
        $models = [];
        
        for ($i = 0; $i < $count; $i++) {
            $models[] = $this->create($attributes);
        }
        
        return $models;
    }

    /**
     * Make model data without persisting
     * 
     * @param array $attributes
     * @return array
     */
    public function make(array $attributes = []): array
    {
        $definition = $this->definition();
        
        // Merge: definition < factory attributes < method attributes
        return array_merge($definition, $this->attributes, $attributes);
    }

    /**
     * Make multiple model instances without persisting
     * 
     * @param int $count
     * @param array $attributes
     * @return array
     */
    public function makeMany(int $count, array $attributes = []): array
    {
        $models = [];
        
        for ($i = 0; $i < $count; $i++) {
            $models[] = $this->make($attributes);
        }
        
        return $models;
    }

    /**
     * Create raw attributes (array representation)
     * 
     * @param array $attributes
     * @return array
     */
    public function raw(array $attributes = []): array
    {
        return $this->make($attributes);
    }

    /**
     * Get table name from model class
     * 
     * @param string $model
     * @return string
     */
    protected function getTableName(string $model): string
    {
        // Extract class name and convert to snake_case plural
        $className = basename(str_replace('\\', '/', $model));
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        
        // Simple pluralization
        if (substr($tableName, -1) === 'y') {
            return substr($tableName, 0, -1) . 'ies';
        }
        
        return $tableName . 's';
    }

    /**
     * Generate ULID
     * 
     * @return string
     */
    protected function ulid(): string
    {
        return '01HQZK' . strtoupper(bin2hex(random_bytes(10)));
    }

    /**
     * Generate UUID v4
     * 
     * @return string
     */
    protected function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate random string
     * 
     * @param int $length
     * @return string
     */
    protected function randomString(int $length = 10): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $string;
    }

    /**
     * Generate fake email
     * 
     * @return string
     */
    protected function fakeEmail(): string
    {
        return $this->randomString(8) . '@example.com';
    }

    /**
     * Generate fake phone number
     * 
     * @return string
     */
    protected function fakePhone(): string
    {
        return '+1' . rand(2000000000, 9999999999);
    }

    /**
     * Get current timestamp
     * 
     * @return string
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
