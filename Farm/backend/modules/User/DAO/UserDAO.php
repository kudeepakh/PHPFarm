<?php

namespace PHPFrarm\Modules\User\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\DAO\BaseDAO;
use PHPFrarm\Core\DAO\Traits\OptimisticLockingTrait;

/**
 * User DAO - Data Access Object for User operations
 * Enhanced with correlation ID tracking, universal soft delete support,
 * and optimistic locking for concurrent update protection
 */
class UserDAO extends BaseDAO
{
    use OptimisticLockingTrait;
    
    public function __construct()
    {
        parent::__construct('users');
        $this->setPrimaryKeyColumn('id');
        $this->setSoftDeleteSupport(true);
        $this->setDeletedAtColumn('deleted_at');
    }
    
    /**
     * Get table name for optimistic locking trait
     */
    protected function getTableName(): string
    {
        return 'users';
    }
    
    /**
     * Update user with optimistic locking protection
     * 
     * @param string $userId User ID
     * @param int $expectedVersion Expected version number
     * @param array $data Update data
     * @return array Update result
     * @throws \PHPFrarm\Core\Exceptions\HttpExceptions\ConflictHttpException
     */
    public function updateUserWithLocking(string $userId, int $expectedVersion, array $data): array
    {
        return $this->updateWithOptimisticLocking($userId, $expectedVersion, $data);
    }
    public function getUserById(string $userId): ?array
    {
        $users = Database::callProcedure('sp_get_user_by_id', [$userId]);
        return !empty($users) ? $users[0] : null;
    }

    public function getAllUsers(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return Database::callProcedure('sp_get_all_users', [$perPage, $offset]);
    }

    public function countUsers(): int
    {
        $result = Database::callProcedure('sp_count_users', []);
        return (int)($result[0]['total'] ?? 0);
    }

    public function softDeleteUser(string $userId): void
    {
        Database::callProcedure('sp_soft_delete_user', [$userId]);
    }

    public function getUserByEmail(string $email): ?array
    {
        $users = Database::callProcedure('sp_get_user_by_email', [$email]);
        return !empty($users) ? $users[0] : null;
    }

    public function createUser(array $data): string
    {
        $userId = $this->generateUserId();
        
        Database::callProcedure('sp_create_user', [
            $userId,
            $data['email'],
            $data['password_hash'],
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['phone'] ?? null,
            $data['status'] ?? 'active'
        ]);

        return $userId;
    }

    public function updateUser(string $userId, array $data): void
    {
        Database::callProcedure('sp_update_user', [
            $userId,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['phone'] ?? null,
            $data['status'] ?? null
        ]);
    }

    private function generateUserId(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
