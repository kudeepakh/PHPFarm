<?php

namespace Farm\Backend\Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use PHPFrarm\Core\Database;
use Farm\Backend\Tests\Factories\FactoryRegistry;

/**
 * Base Test Case
 * 
 * Foundation for all test cases with common utilities:
 * - Database setup/teardown
 * - Factory access
 * - Mock helpers
 * - Assertion helpers
 * 
 * Usage:
 * ```php
 * class UserTest extends TestCase
 * {
 *     public function testExample()
 *     {
 *         $user = $this->factory(UserFactory::class)->create();
 *         $this->assertDatabaseHas('users', ['id' => $user['id']]);
 *     }
 * }
 * ```
 */
abstract class TestCase extends BaseTestCase
{
    protected PDO $db;
    protected FactoryRegistry $factoryRegistry;

    /**
     * Set up test environment before each test
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db = Database::getConnection();
        $this->factoryRegistry = FactoryRegistry::getInstance();
        
        // Begin transaction for database isolation
        $this->beginDatabaseTransaction();
    }

    /**
     * Clean up after each test
     * 
     * @return void
     */
    protected function tearDown(): void
    {
        // Rollback transaction to clean database state
        $this->rollbackDatabaseTransaction();
        
        parent::tearDown();
    }

    /**
     * Begin database transaction
     * 
     * @return void
     */
    protected function beginDatabaseTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Rollback database transaction
     * 
     * @return void
     */
    protected function rollbackDatabaseTransaction(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Get factory instance
     * 
     * @param string $factoryClass
     * @return mixed Factory instance
     */
    protected function factory(string $factoryClass): mixed
    {
        return $this->factoryRegistry->get($factoryClass);
    }

    /**
     * Assert database has record matching criteria
     * 
     * @param string $table
     * @param array $criteria
     * @return void
     */
    protected function assertDatabaseHas(string $table, array $criteria): void
    {
        $where = [];
        $params = [];
        
        foreach ($criteria as $key => $value) {
            $where[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }
        
        $sql = "SELECT COUNT(*) as count FROM `$table` WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0];
        
        $this->assertGreaterThan(
            0,
            $result['count'],
            "Failed asserting that table [$table] contains matching record."
        );
    }

    /**
     * Assert database does not have record matching criteria
     * 
     * @param string $table
     * @param array $criteria
     * @return void
     */
    protected function assertDatabaseMissing(string $table, array $criteria): void
    {
        $where = [];
        $params = [];
        
        foreach ($criteria as $key => $value) {
            $where[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }
        
        $sql = "SELECT COUNT(*) as count FROM `$table` WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0];
        
        $this->assertEquals(
            0,
            $result['count'],
            "Failed asserting that table [$table] does not contain matching record."
        );
    }

    /**
     * Assert database table has count of records
     * 
     * @param string $table
     * @param int $count
     * @return void
     */
    protected function assertDatabaseCount(string $table, int $count): void
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM `$table`");
        $result = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0]) : ['count' => 0];
        
        $this->assertEquals(
            $count,
            $result['count'],
            "Failed asserting that table [$table] has [$count] records."
        );
    }

    /**
     * Seed database with test data
     * 
     * @param string $seederClass
     * @return void
     */
    protected function seed(string $seederClass): void
    {
        $seeder = new $seederClass($this->db);
        $seeder->run();
    }

    /**
     * Mock time for testing
     * 
     * @param string $datetime
     * @return void
     */
    protected function travelTo(string $datetime): void
    {
        // Store current time offset
        $targetTime = strtotime($datetime);
        $currentTime = time();
        
        putenv("TEST_TIME_OFFSET=" . ($targetTime - $currentTime));
    }

    /**
     * Reset time mock
     * 
     * @return void
     */
    protected function travelBack(): void
    {
        putenv("TEST_TIME_OFFSET=");
    }

    /**
     * Create mock object with expectations
     * 
     * @param string $class
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function mockClass(string $class): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock($class);
    }

    /**
     * Assert array has subset
     * 
     * @param array $subset
     * @param array $array
     * @return void
     */
    protected function assertArraySubset(array $subset, array $array): void
    {
        foreach ($subset as $key => $value) {
            $this->assertArrayHasKey($key, $array);
            
            if (is_array($value)) {
                $this->assertArraySubset($value, $array[$key]);
            } else {
                $this->assertEquals($value, $array[$key]);
            }
        }
    }

    /**
     * Assert JSON structure matches expected
     * 
     * @param array $structure
     * @param array $json
     * @return void
     */
    protected function assertJsonStructure(array $structure, array $json): void
    {
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    // Wildcard array element
                    $this->assertIsArray($json);
                    if (!empty($json)) {
                        $this->assertJsonStructure($value, reset($json));
                    }
                } else {
                    // Named key with nested structure
                    $this->assertArrayHasKey($key, $json);
                    $this->assertJsonStructure($value, $json[$key]);
                }
            } else {
                // Simple key
                $this->assertArrayHasKey($value, $json);
            }
        }
    }

    /**
     * Dump variable for debugging
     * 
     * @param mixed $var
     * @return void
     */
    protected function dump(mixed $var): void
    {
        var_dump($var);
    }

    /**
     * Dump and die for debugging
     * 
     * @param mixed $var
     * @return never
     * @throws \RuntimeException
     */
    protected function dd(mixed $var): never
    {
        var_dump($var);
        throw new \RuntimeException('dd() called - test stopped for debugging');
    }
}
