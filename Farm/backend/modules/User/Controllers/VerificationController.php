<?php

namespace PHPFrarm\Modules\User\Controllers;

use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\UserManagement\Services\EmailVerificationService;
use PHPFrarm\Modules\UserManagement\Services\IdentifierService;
use PHPFrarm\Core\Logger;
use PHPFrarm\Modules\Auth\Services\OTPService;

/**
 * Verification Controller
 * Email and phone verification endpoints
 */
#[RouteGroup('/api/v1/users', middleware: ['cors'])]
class VerificationController
{
    /**
     * Verify email with token (POST - for API clients)
     */
    #[Route('/verify-email', method: 'POST')]
    public function verifyEmail(array $request): void
    {
        try {
            $data = $request['body'] ?? [];
            $token = $data['token'] ?? null;
            
            if (!$token) {
                Response::badRequest('verification.token_required');
            }
            
            $result = EmailVerificationService::verifyToken($token);
            
            Response::success([
                'message' => 'verification.email.success',
                'user_id' => $result['user_id'],
                'email' => $result['email']
            ]);
            
        } catch (\Exception $e) {
            Logger::warning('Email verification failed', [
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 400;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
            }
            Response::error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Verify email with token (GET - for email links, redirects to frontend)
     */
    #[Route('/verify-email', method: 'GET')]
    public function verifyEmailGet(array $request): void
    {
        $token = $request['query']['token'] ?? null;
        $frontendUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'http://localhost:3900', '/');
        
        if (!$token) {
            // Redirect to frontend error page
            header('Location: ' . $frontendUrl . '/verify-email?error=missing_token');
            exit;
        }
        
        try {
            $result = EmailVerificationService::verifyToken($token);
            
            // Redirect to frontend success page
            header('Location: ' . $frontendUrl . '/verify-email?success=true&email=' . urlencode($result['email']));
            exit;
            
        } catch (\Exception $e) {
            Logger::warning('Email verification failed (GET)', [
                'error' => $e->getMessage()
            ]);
            
            // Redirect to frontend error page
            header('Location: ' . $frontendUrl . '/verify-email?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /**
     * Resend email verification
     */
    #[Route('/resend-verification', method: 'POST', middleware: ['auth'])]
    public function resendVerification(array $request): void
    {
        try {
            $user = $request['user'] ?? [];
            $userId = $user['user_id'];
            $email = $user['email'];
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            $token = EmailVerificationService::resendVerification($userId, $email, $ipAddress);
            
            Response::success([
                'message' => 'verification.email.resent'
            ]);
            
        } catch (\Exception $e) {
            Logger::warning('Failed to resend verification', [
                'error' => $e->getMessage()
            ]);
            
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
            }
            Response::serverError($e->getMessage());
        }
    }
    
    /**
     * Check email verification status
     */
    #[Route('/verification-status', method: 'GET', middleware: ['auth'])]
    public function getVerificationStatus(array $request): void
    {
        try {
            $userId = $request['user']['user_id'] ?? '';
            
            $isVerified = EmailVerificationService::isEmailVerified($userId);
            $pending = null;
            
            if (!$isVerified) {
                $pending = EmailVerificationService::getPendingVerification($userId);
            }
            
            Response::success([
                'email_verified' => $isVerified,
                'pending_verification' => $pending
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to get verification status', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('verification.status.failed');
        }
    }
    
    /**
     * Request phone verification OTP
     */
    #[Route('/verify-phone/send-otp', method: 'POST', middleware: ['auth'])]
    public function sendPhoneVerificationOtp(array $request): void
    {
        try {
            $data = $request['body'] ?? [];
            $phone = $data['phone'] ?? null;
            
            if (!$phone) {
                Response::badRequest('verification.phone.required');
            }
            
            $userId = $request['user']['user_id'] ?? '';
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            $otpService = new OTPService();
            $otpResult = $otpService->requestOTP(
                $phone,
                'phone',
                'verification',
                $userId
            );
            
            Response::success(array_merge([
                'message' => 'verification.phone.otp_sent',
                'phone' => $phone
            ], $otpResult));
            
        } catch (\Exception $e) {
            Logger::error('Failed to send phone OTP', [
                'error' => $e->getMessage()
            ]);
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
            }
            if ($statusCode === 429) {
                Response::tooManyRequests($e->getMessage());
            }
            Response::serverError('verification.phone.otp_failed');
        }
    }
    
    /**
     * Verify phone with OTP
     */
    #[Route('/verify-phone', method: 'POST', middleware: ['auth'])]
    public function verifyPhone(array $request): void
    {
        try {
            $data = $request['body'] ?? [];
            $phone = $data['phone'] ?? null;
            $otp = $data['otp'] ?? null;
            
            if (!$phone || !$otp) {
                Response::badRequest('verification.phone.otp_required');
            }
            
            $userId = $request['user']['user_id'] ?? '';
            
            $otpService = new OTPService();
            $isValid = $otpService->verifyOTP($phone, $otp, 'verification', $userId);

            if (!$isValid) {
                Response::forbidden('otp.invalid');
            }

            IdentifierService::verifyIdentifier($userId, 'phone', $phone);
            
            Response::success([
                'message' => 'verification.phone.success',
                'phone' => $phone
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to verify phone', [
                'error' => $e->getMessage()
            ]);
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
            }
            if ($statusCode === 429) {
                Response::tooManyRequests($e->getMessage());
            }
            Response::serverError('verification.phone.verify_failed');
        }
    }
}
