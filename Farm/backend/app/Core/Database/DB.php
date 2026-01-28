<?php

declare(strict_types=1);

namespace PHPFrarm\Core\Database;

use PHPFrarm\Core\Database as BaseDatabase;
use PDO;

/**
 * DB shim for legacy App namespace.
 * Allows read-only queries for views and stored procedure calls.
 */
class DB
{
    /**
     * Execute a read-only SELECT query (views only).
     */
    public static function select(string $sql, array $params = []): array
    {
        self::assertReadOnlyQuery($sql);
        $pdo = BaseDatabase::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Call a stored procedure (delegates to BaseDatabase).
     */
    public static function callProcedure(string $procedureName, array $params = []): array|bool
    {
        return BaseDatabase::callProcedure($procedureName, $params);
    }

    /**
     * Execute raw SQL (migrations only).
     */
    public static function execute(string $sql, array $params = []): array|int
    {
        return BaseDatabase::execute($sql, $params);
    }

    private static function assertReadOnlyQuery(string $sql): void
    {
        $normalized = strtoupper(trim($sql));
        if (!str_starts_with($normalized, 'SELECT')) {
            throw new \RuntimeException('Only SELECT queries are allowed via DB::select');
        }

        // Enforce views-only access to avoid direct table queries.
        if (!preg_match('/\bFROM\s+`?vw_/i', $sql)) {
            throw new \RuntimeException('Only view-based SELECT queries are allowed');
        }
    }
}
