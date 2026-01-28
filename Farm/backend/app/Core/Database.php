<?php

namespace PHPFrarm\Core;

use PDO;
use PDOException;

/**
 * Database class - Enforces stored procedure-only access
 * 
 * NON-NEGOTIABLE RULE: All MySQL operations MUST use stored procedures
 * Raw SELECT/INSERT/UPDATE/DELETE queries are BLOCKED
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $blockedKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER'];
    private static bool $allowRawQueries = false;

    /**
     * Compatibility singleton for legacy command usage
     */
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * Get database connection
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $host = $_ENV['MYSQL_HOST'] ?? 'mysql';
                $port = $_ENV['MYSQL_PORT'] ?? '3306';
                $dbname = $_ENV['MYSQL_DATABASE'] ?? 'phpfrarm_db';
                $username = $_ENV['MYSQL_USER'] ?? 'root';
                $password = $_ENV['MYSQL_PASSWORD'] ?? '';

                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                
                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                Logger::error('Database connection failed', ['error' => $e->getMessage()]);
                throw new \RuntimeException('Database connection failed');
            }
        }

        return self::$connection;
    }

    /**
     * Enable raw SQL execution for framework-internal operations (migrations, bootstrap).
     */
    public static function enableRawQueries(): void
    {
        self::$allowRawQueries = true;
    }

    /**
     * Disable raw SQL execution.
     */
    public static function disableRawQueries(): void
    {
        self::$allowRawQueries = false;
    }

    /**
     * Execute raw SQL (framework-internal use only).
     * Returns result set for SELECT/SHOW, or affected rows for others.
     */
    public static function execute(string $sql, array $params = []): array|int
    {
        if (!self::$allowRawQueries) {
            throw new \RuntimeException('Raw SQL execution is disabled. Enable it for migrations/bootstrap only.');
        }

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->columnCount() > 0) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $stmt->rowCount();
    }

    /**
     * Instance compatibility wrapper for execute().
     */
    public function executeInstance(string $sql, array $params = []): array|int
    {
        return self::execute($sql, $params);
    }

    /**
     * Call a stored procedure (ONLY ALLOWED METHOD)
     * 
     * @param string $procedureName Name of the stored procedure
     * @param array $params Parameters for the stored procedure
     * @return array|bool Result set or boolean
     */
    public static function callProcedure(string $procedureName, array $params = []): array|bool
    {
        try {
            // Strictly validate procedure name to prevent SQL injection via identifier
            if (!preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$/', $procedureName)) {
                Logger::security('Blocked stored procedure call with invalid name', [
                    'procedure' => $procedureName,
                ]);
                throw new \RuntimeException('Invalid stored procedure name');
            }

            $db = self::getConnection();
            
            // Build placeholders
            $placeholders = rtrim(str_repeat('?,', count($params)), ',');
            $sql = "CALL $procedureName($placeholders)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($params));
            
            // Check if procedure returns results
            if ($stmt->columnCount() > 0) {
                return $stmt->fetchAll();
            }
            
            return true;
        } catch (PDOException $e) {
            Logger::error('Stored procedure execution failed', [
                'procedure' => $procedureName,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Database operation failed');
        }
    }

    /**
     * Execute raw query - BLOCKED BY DEFAULT
     * Only allowed for framework-level migrations
     * 
     * @throws \RuntimeException Always throws exception
     */
    public static function query(string $sql): void
    {
        // Check if query contains blocked keywords
        $upperSql = strtoupper(trim($sql));
        foreach (self::$blockedKeywords as $keyword) {
            if (str_starts_with($upperSql, $keyword)) {
                Logger::security('Attempt to execute raw SQL query blocked', [
                    'query_start' => substr($sql, 0, 50),
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
                ]);
                
                throw new \RuntimeException(
                    'Direct SQL queries are not allowed. Use stored procedures only. ' .
                    'Detected keyword: ' . $keyword
                );
            }
        }
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::getConnection()->rollBack();
    }

    /**
     * Get last insert ID
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Prepare a statement for stored procedure execution
     * 
     * This is a compatibility method for code that uses prepare().
     * It validates that the query is a CALL statement for stored procedures.
     * 
     * @param string $sql The CALL statement
     * @return \PDOStatement The prepared statement
     */
    public static function prepare(string $sql): \PDOStatement
    {
        // Validate that only CALL statements are allowed
        $trimmedSql = strtoupper(trim($sql));
        if (!str_starts_with($trimmedSql, 'CALL ')) {
            Logger::security('Attempt to prepare non-CALL SQL blocked', [
                'query_start' => substr($sql, 0, 50),
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
            ]);
            throw new \RuntimeException(
                'Only CALL statements for stored procedures are allowed. '
                . 'Use Database::callProcedure() for better security and consistency.'
            );
        }
        
        // Log all prepare() usage for security audit
        Logger::debug('Stored procedure prepare() called', [
            'procedure' => substr($sql, 0, 100),
            'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown'
        ]);

        return self::getConnection()->prepare($sql);
    }

    /**
     * Fetch output parameters from a stored procedure
     * 
     * This is ONLY allowed for fetching session variables set by stored procedures.
     * It validates that the query only selects @-prefixed session variables.
     * 
     * @param array $varNames Array of variable names (without @)
     * @return array The fetched values
     */
    public static function fetchOutputParameters(array $varNames): array
    {
        // Build SELECT for session variables only
        $selectParts = [];
        foreach ($varNames as $varName) {
            // Validate variable name (only alphanumeric and underscores)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $varName)) {
                throw new \InvalidArgumentException("Invalid variable name: $varName");
            }
            $selectParts[] = "@$varName as $varName";
        }
        
        $sql = "SELECT " . implode(', ', $selectParts);
        
        try {
            $stmt = self::getConnection()->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            Logger::error('Failed to fetch output parameters', [
                'variables' => $varNames,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
