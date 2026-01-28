<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Attributes\PublicRoute;
use PHPFrarm\Modules\Auth\Services\OTPService;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Modules\Auth\DTO\OTPRequestDTO;
use PHPFrarm\Modules\Auth\DTO\OTPVerifyDTO;
use PHPFrarm\Modules\UserManagement\Services\IdentifierService;
use PHPFrarm\Modules\Auth\DAO\UserDAO;
use PHPFrarm\Modules\Auth\DAO\SessionDAO;

/**
 * OTP Controller - Handles HTTP requests for OTP operations
 * 
 * Routes are automatically discovered via attributes
 */
#[RouteGroup('/api/v1/auth', middleware: ['cors', 'rateLimit'])]
class OTPController
{
    private OTPService $otpService;
    private AuthService $authService;

    public function __construct()
    {
        $this->otpService = new OTPService();
        $this->authService = new AuthService();
    }

    /**
     * Request OTP for email or phone
     * 
     * @route POST /api/auth/request-otp
     * @middleware jsonParser - Parse and validate JSON body
     */
    #[PublicRoute(reason: 'OTP request for login/registration must be accessible without authentication')]
    #[Route('/otp/request', method: 'POST', middleware: ['jsonParser'], description: 'Request OTP')]
    public function requestOTP(array $request): void
    {
        $dto = new OTPRequestDTO($request['body']);

        // Validate DTO
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('validation.failed', $errors);
            return;
        }

        try {
            $result = $this->otpService->requestOTP(
                $dto->identifier,
                $dto->type,
                $dto->purpose
            );

            Response::success($result, 'otp.sent');

        } catch (\Exception $e) {
            Logger::error('OTP request failed', [
                'identifier' => $dto->identifier,
                'error' => $e->getMessage()
            ]);
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
                return;
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
                return;
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
                return;
            }
            if ($statusCode === 429) {
                Response::tooManyRequests($e->getMessage());
                return;
            }
            Response::serverError('otp.send_failed');
        }
    }

    /**
     * Verify OTP code
     * 
     * @route POST /api/auth/verify-otp
     * @middleware jsonParser - Parse and validate JSON body
     */
    #[PublicRoute(reason: 'OTP verification for login must be accessible without authentication')]
    #[Route('/otp/verify', method: 'POST', middleware: ['jsonParser'], description: 'Verify OTP')]
    public function verifyOTP(array $request): void
    {
        $dto = new OTPVerifyDTO($request['body']);

        // Validate DTO
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('validation.failed', $errors);
            return;
        }

        try {
            $isValid = $this->otpService->verifyOTP(
                $dto->identifier,
                $dto->otp,
                $dto->purpose
            );

            if ($isValid) {
                if ($dto->purpose === 'login') {
                    $loginResult = $this->authService->loginWithOtpIdentifier($dto->identifier);
                    Response::success($loginResult, 'otp.verified');
                    return;
                }

                Response::success(['verified' => true], 'otp.verified');
            } else {
                Response::badRequest('otp.invalid');
            }

        } catch (\Exception $e) {
            Logger::error('OTP verification failed', [
                'identifier' => $dto->identifier,
                'error' => $e->getMessage()
            ]);
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
                return;
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
                return;
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
                return;
            }
            if ($statusCode === 429) {
                Response::tooManyRequests($e->getMessage());
                return;
            }
            Response::serverError('otp.verify_failed');
        }
    }

    /**
     * Request password reset OTP
     *
     * @route POST /api/v1/auth/password/forgot
     * @middleware jsonParser
     */
    #[PublicRoute(reason: 'Password reset must be accessible without authentication')]
    #[Route('/password/forgot', method: 'POST', middleware: ['jsonParser'], description: 'Request password reset OTP')]
    public function requestPasswordReset(array $request): void
    {
        $identifier = $request['body']['identifier'] ?? '';
        $type = $request['body']['type'] ?? 'email';
        if ($type === 'sms') {
            $type = 'phone';
        }

        if ($identifier === '' || !in_array($type, ['email', 'phone'], true)) {
            Response::badRequest('validation.failed', ['identifier' => 'Identifier is required', 'type' => 'Type must be email, phone, or sms']);
            return;
        }

        try {
            $user = IdentifierService::findUserByIdentifier($identifier);
            if (!$user) {
                Logger::info('Password reset requested for non-existent identifier', [
                    'identifier' => $identifier,
                    'type' => $type
                ]);

                Response::success(['otp_sent' => true], 'otp.sent');
                return;
            }

            $result = $this->otpService->requestOTP(
                $identifier,
                $type,
                'password_reset'
            );

            Response::success($result, 'otp.sent');

        } catch (\Exception $e) {
            Logger::error('Password reset OTP request failed', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
                return;
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
                return;
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
                return;
            }
            if ($statusCode === 429) {
                Response::tooManyRequests($e->getMessage());
                return;
            }
            Response::serverError('otp.send_failed');
        }
    }

    /**
     * Reset password using OTP
     *
     * @route POST /api/v1/auth/password/reset
     * @middleware jsonParser
     */
    #[PublicRoute(reason: 'Password reset must be accessible without authentication')]
    #[Route('/password/reset', method: 'POST', middleware: ['jsonParser'], description: 'Reset password')]
    public function resetPassword(array $request): void
    {
        $identifier = $request['body']['identifier'] ?? '';
        $type = $request['body']['type'] ?? 'email';
        if ($type === 'sms') {
            $type = 'phone';
        }
        $otp = $request['body']['otp'] ?? '';
        $newPassword = $request['body']['new_password'] ?? '';

        if ($identifier === '' || $otp === '' || $newPassword === '' || !in_array($type, ['email', 'phone'], true)) {
            Response::badRequest('validation.failed', ['identifier' => 'Identifier is required', 'otp' => 'OTP is required', 'new_password' => 'New password is required', 'type' => 'Type must be email, phone, or sms']);
            return;
        }

        if (strlen($newPassword) < 8) {
            Response::badRequest('validation.failed', ['new_password' => 'Password must be at least 8 characters']);
            return;
        }

        try {
            $isValid = $this->otpService->verifyOTP(
                $identifier,
                $otp,
                'password_reset'
            );

            if (!$isValid) {
                Response::badRequest('otp.invalid');
                return;
            }

            $user = IdentifierService::findUserByIdentifier($identifier);
            if (!$user) {
                Logger::info('Password reset attempted for non-existent identifier', [
                    'identifier' => $identifier,
                    'type' => $type
                ]);

                Response::success(['password_reset' => true], 'auth.password.reset.success');
                return;
            }

            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $userDAO = new UserDAO();
            $userDAO->updatePassword($user['id'], $passwordHash, true);

            $sessionDAO = new SessionDAO();
            $sessionDAO->revokeAllForUser($user['id']);

            Response::success(['password_reset' => true], 'auth.password.reset.success');

        } catch (\Exception $e) {
            Logger::error('Password reset failed', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            $statusCode = $e->getCode() ?: 500;
            if ($statusCode === 400) {
                Response::badRequest($e->getMessage());
                return;
            }
            if ($statusCode === 401) {
                Response::unauthorized($e->getMessage());
                return;
            }
            if ($statusCode === 403) {
                Response::forbidden($e->getMessage());
                return;
            }
            if ($statusCode === 429) {
                Response::tooManyRequests($e->getMessage());
                return;
            }
            Response::serverError('auth.password.reset.failed');
        }
    }
}
