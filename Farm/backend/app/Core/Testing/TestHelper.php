<?php

namespace Farm\Backend\App\Core\Testing;

use PHPFrarm\Core\Database;
use Redis;

/**
 * Test Helper
 * 
 * Provides utilities for testing including:
 * - Database seeding helpers
 * - Redis clearing
 * - File system mocking
 * - Time mocking
 * 
 * Usage:
 * ```php
 * TestHelper::clearDatabase();
 * TestHelper::seed('users', $userData);
 * TestHelper::mockTime('2026-01-18 10:00:00');
 * ```
 */
class TestHelper
{
    private static ?Redis $redis = null;

    /**
     * Get Redis instance
     * 
     * @return Redis
     */
    private static function redis(): Redis
    {
        if (self::$redis === null) {
            self::$redis = new Redis();
            self::$redis->connect(
                $_ENV['REDIS_HOST'] ?? 'localhost',
                (int)($_ENV['REDIS_PORT'] ?? 6379)
            );
        }
        
        return self::$redis;
    }

    /**
     * Clear all test data from database
     * 
     * @param array $exceptTables Tables to skip
     * @return void
     */
    public static function clearDatabase(array $exceptTables = []): void
    {
        Database::enableRawQueries();
        try {
            // Disable foreign key checks
            Database::execute('SET FOREIGN_KEY_CHECKS = 0');

            // Get all tables
            $tables = Database::execute('SHOW TABLES');

            foreach ($tables as $table) {
                $tableName = reset($table);

                if (!in_array($tableName, $exceptTables, true)) {
                    Database::execute("TRUNCATE TABLE `$tableName`");
                }
            }

            // Re-enable foreign key checks
            Database::execute('SET FOREIGN_KEY_CHECKS = 1');
        } finally {
            Database::disableRawQueries();
        }
    }

    /**
     * Clear Redis cache
     * 
     * @return void
     */
    public static function clearRedis(): void
    {
        try {
            self::redis()->flushDB();
        } catch (\Exception $e) {
            // Redis not available, skip
        }
    }

    /**
     * Seed table with data
     * 
     * @param string $table
     * @param array $data Single row or array of rows
     * @return void
     */
    public static function seed(string $table, array $data): void
    {
        // Check if single row or multiple rows
        if (isset($data[0]) && is_array($data[0])) {
            // Multiple rows
            foreach ($data as $row) {
                self::insertRow($table, $row);
            }
        } else {
            // Single row
            self::insertRow($table, $data);
        }
    }

    /**
     * Insert single row
     * 
     * @param string $table
     * @param array $row
     * @return void
     */
    private static function insertRow(string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $params = [];
        foreach ($row as $key => $value) {
            $params[":$key"] = $value;
        }
        
        Database::enableRawQueries();
        try {
            Database::execute($sql, $params);
        } finally {
            Database::disableRawQueries();
        }
    }

    /**
     * Mock current time
     * 
     * @param string $datetime
     * @return void
     */
    public static function mockTime(string $datetime): void
    {
        $targetTime = strtotime($datetime);
        $currentTime = time();
        
        putenv("TEST_TIME_OFFSET=" . ($targetTime - $currentTime));
    }

    /**
     * Reset time mock
     * 
     * @return void
     */
    public static function resetTime(): void
    {
        putenv("TEST_TIME_OFFSET=");
    }

    /**
     * Get mocked current time
     * 
     * @return int
     */
    public static function now(): int
    {
        $offset = (int)getenv('TEST_TIME_OFFSET');
        return time() + $offset;
    }

    /**
     * Create temporary file for testing
     * 
     * @param string $content
     * @param string $extension
     * @return string File path
     */
    public static function createTempFile(string $content, string $extension = 'txt'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.' . $extension;
        file_put_contents($tempFile, $content);
        
        return $tempFile;
    }

    /**
     * Clean up temporary files
     * 
     * @param string $filePath
     * @return void
     */
    public static function deleteTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Generate random string
     * 
     * @param int $length
     * @return string
     */
    public static function randomString(int $length = 10): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $string;
    }

    /**
     * Generate random email
     * 
     * @return string
     */
    public static function randomEmail(): string
    {
        return self::randomString(8) . '@example.com';
    }

    /**
     * Generate random phone number
     * 
     * @return string
     */
    public static function randomPhone(): string
    {
        return '+1' . rand(2000000000, 9999999999);
    }

    /**
     * Generate ULID for testing
     * 
     * @return string
     */
    public static function ulid(): string
    {
        return '01HQZK' . strtoupper(bin2hex(random_bytes(10)));
    }

    /**
     * Wait for condition to be true
     * 
     * @param callable $condition
     * @param int $timeoutSeconds
     * @return bool
     */
    public static function waitUntil(callable $condition, int $timeoutSeconds = 5): bool
    {
        $start = time();
        
        while (time() - $start < $timeoutSeconds) {
            if ($condition()) {
                return true;
            }
            
            usleep(100000); // 100ms
        }
        
        return false;
    }
}
