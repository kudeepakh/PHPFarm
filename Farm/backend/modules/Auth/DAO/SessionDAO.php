<?php

namespace PHPFrarm\Modules\Auth\DAO;

use PHPFrarm\Core\Database;

/**
 * Session DAO - manages user session tokens for revocation/rotation
 */
class SessionDAO
{
    public function createSession(
        string $sessionId,
        string $userId,
        string $tokenHash,
        string $refreshTokenHash,
        string $deviceInfo,
        string $ipAddress,
        string $userAgent,
        string $expiresAt,
        string $refreshExpiresAt
    ): array {
        return Database::callProcedure('sp_create_user_session', [
            $sessionId,
            $userId,
            $tokenHash,
            $refreshTokenHash,
            $deviceInfo,
            $ipAddress,
            $userAgent,
            $expiresAt,
            $refreshExpiresAt
        ]);
    }

    public function getActiveSessionByTokenHash(string $tokenHash): ?array
    {
        $sessions = Database::callProcedure('sp_get_user_session_by_token_hash', [$tokenHash]);
        return !empty($sessions) ? $sessions[0] : null;
    }

    public function getActiveSessionByRefreshHash(string $refreshTokenHash): ?array
    {
        $sessions = Database::callProcedure('sp_get_user_session_by_refresh_hash', [$refreshTokenHash]);
        return !empty($sessions) ? $sessions[0] : null;
    }

    public function updateSessionTokens(
        string $sessionId,
        string $tokenHash,
        string $refreshTokenHash,
        string $expiresAt,
        string $refreshExpiresAt
    ): void {
        Database::callProcedure('sp_update_user_session_tokens', [
            $sessionId,
            $tokenHash,
            $refreshTokenHash,
            $expiresAt,
            $refreshExpiresAt
        ]);
    }

    public function revokeSession(string $sessionId): void
    {
        Database::callProcedure('sp_revoke_user_session', [$sessionId]);
    }

    public function revokeAllForUser(string $userId): void
    {
        Database::callProcedure('sp_revoke_user_sessions_by_user', [$userId]);
    }
}
