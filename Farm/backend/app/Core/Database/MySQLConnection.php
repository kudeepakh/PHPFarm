<?php

declare(strict_types=1);

namespace PHPFrarm\Core\Database;

use PHPFrarm\Core\Database as BaseDatabase;
use PDO;

/**
 * Lightweight PDO wrapper for schema/version management.
 */
class MySQLConnection
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = BaseDatabase::getConnection();
    }

    /**
     * Execute SQL and return result set for SELECT/SHOW, or affected rows for others.
     */
    public function execute(string $sql, array $params = []): array|int
    {
        BaseDatabase::enableRawQueries();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->columnCount() > 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $stmt->rowCount();
        } finally {
            BaseDatabase::disableRawQueries();
        }
    }

    /**
     * Query alias for execute
     */
    public function query(string $sql, array $params = []): array
    {
        $result = $this->execute($sql, $params);
        return is_array($result) ? $result : [];
    }
}
