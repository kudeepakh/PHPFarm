<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\Auth\DAO\OTPHistoryDAO;
use PHPFrarm\Modules\Auth\DAO\OTPBlacklistDAO;
use PHPFrarm\Modules\Auth\DAO\OTPDAO;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\I18n\Translator;

/**
 * OTP Admin Controller
 * Admin APIs for OTP monitoring and management
 */
#[RouteGroup('/api/v1', middleware: ['cors'])]
class OTPAdminController
{
    /**
     * Get OTP history
     */
    #[Route('/api/v1/system/otp/history', method: 'GET', middleware: ['auth', 'permission:otp:read'])]
    public function getHistory(array $request): void
    {
        try {
            $userId = $request['query']['user_id'] ?? null;
            $identifier = $request['query']['identifier'] ?? null;
            $identifierType = $request['query']['identifier_type'] ?? 'phone';
            $limit = (int)($request['query']['limit'] ?? 50);
            
            if ($userId) {
                $history = OTPHistoryDAO::getHistoryByUser($userId, $limit);
            } elseif ($identifier) {
                $history = OTPHistoryDAO::getHistoryByIdentifier($identifierType, $identifier, $limit);
            } else {
                $history = OTPHistoryDAO::getRecentActivity($limit);
            }
            
            Response::success([
                'history' => $history,
                'count' => count($history)
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to get OTP history', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.history.failed');
        }
    }
    
    /**
     * Get OTP statistics
     */
    #[Route('/api/v1/system/otp/statistics', method: 'GET', middleware: ['auth', 'permission:otp:read'])]
    public function getStatistics(array $request): void
    {
        try {
            $hours = (int)($request['query']['hours'] ?? 24);
            
            $statistics = OTPHistoryDAO::getStatistics($hours);
            
            Response::success([
                'time_window_hours' => $hours,
                'statistics' => $statistics
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to get OTP statistics', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.statistics.failed');
        }
    }
    
    /**
     * Get blacklist entries
     */
    #[Route('/api/v1/system/otp/blacklist', method: 'GET', middleware: ['auth', 'permission:otp:read'])]
    public function getBlacklist(array $request): void
    {
        try {
            $limit = (int)($request['query']['limit'] ?? 50);
            $offset = (int)($request['query']['offset'] ?? 0);
            
            $blacklist = OTPBlacklistDAO::getBlacklist($limit, $offset);
            
            Response::success([
                'blacklist' => $blacklist,
                'count' => count($blacklist),
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to get blacklist', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.blacklist.failed');
        }
    }
    
    /**
     * Add to blacklist
     */
    #[Route('/api/v1/system/otp/blacklist', method: 'POST', middleware: ['auth', 'permission:otp:manage'])]
    public function addToBlacklist(array $request): void
    {
        try {
            $data = $request['body'] ?? [];
            $identifierType = $data['identifier_type'] ?? null;
            $identifierValue = $data['identifier_value'] ?? null;
            $reason = $data['reason'] ?? Translator::translate('otp.admin.blacklist.reason.default');
            $durationHours = (int)($data['duration_hours'] ?? 24);
            $isPermanent = (bool)($data['is_permanent'] ?? false);
            
            if (!$identifierType || !$identifierValue) {
                Response::badRequest('otp.admin.blacklist.identifier_required');
            }
            
            $adminId = $request['user']['user_id'] ?? '';
            $blacklistId = UuidGenerator::v4();
            
            OTPBlacklistDAO::addToBlacklist(
                $blacklistId,
                $identifierType,
                $identifierValue,
                $reason,
                $adminId,
                $durationHours,
                $isPermanent,
                false
            );
            
            Response::success([
                'message' => 'otp.admin.blacklist.added',
                'blacklist_id' => $blacklistId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to add to blacklist', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.blacklist.add_failed');
        }
    }
    
    /**
     * Remove from blacklist
     */
    #[Route('/api/v1/system/otp/blacklist/{blacklistId}', method: 'DELETE', middleware: ['auth', 'permission:otp:manage'])]
    public function removeFromBlacklist(array $request, string $blacklistId): void
    {
        try {
            OTPBlacklistDAO::removeFromBlacklist($blacklistId);
            
            Response::success([
                'message' => 'otp.admin.blacklist.removed'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to remove from blacklist', [
                'blacklist_id' => $blacklistId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.blacklist.remove_failed');
        }
    }
    
    /**
     * Check OTP status for identifier
     */
    #[Route('/api/v1/system/otp/check-status', method: 'POST', middleware: ['auth', 'permission:otp:read'])]
    public function checkStatus(array $request): void
    {
        try {
            $data = $request['body'] ?? [];
            $identifier = $data['identifier'] ?? null;
            $identifierType = $data['identifier_type'] ?? 'phone';
            
            if (!$identifier) {
                Response::badRequest('otp.admin.status.identifier_required');
            }
            
            $otpDAO = new OTPDAO();
            $validity = $otpDAO->checkValidity($identifier);
            $retryInfo = $otpDAO->getRetryCount($identifier);
            $blacklistCheck = OTPBlacklistDAO::checkBlacklist($identifierType, $identifier);
            
            Response::success([
                'identifier' => $identifier,
                'validity' => $validity,
                'retry_info' => $retryInfo,
                'blacklist' => $blacklistCheck
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to check OTP status', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.status.failed');
        }
    }
    
    /**
     * Cleanup expired data
     */
    #[Route('/api/v1/system/otp/cleanup', method: 'POST', middleware: ['auth', 'permission:otp:manage'])]
    public function cleanup(array $request): void
    {
        try {
            $otpDAO = new OTPDAO();
            $expiredOtps = $otpDAO->cleanupExpired();
            $expiredBlacklist = OTPBlacklistDAO::cleanupExpired();
            $oldHistory = OTPHistoryDAO::cleanupOldHistory(90);
            
            Response::success([
                'message' => 'otp.admin.cleanup.success',
                'expired_otps_deleted' => $expiredOtps,
                'expired_blacklist_deleted' => $expiredBlacklist,
                'old_history_deleted' => $oldHistory
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to cleanup OTP data', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('otp.admin.cleanup.failed');
        }
    }
}
