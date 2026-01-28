<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Validation\InputValidator;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Attributes\PublicRoute;
use PHPFrarm\Core\Database;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Modules\Auth\DAO\UserDAO;

/**
 * Phone Registration Controller
 * Handles phone-based user registration with OTP verification
 * 
 * Uses User Identifiers pattern:
 * - Creates user with phone as primary identifier
 * - Requires OTP verification to activate account
 */
#[RouteGroup('/api/v1/auth/register', middleware: ['cors', 'rateLimit'])]
class PhoneRegistrationController
{
    private AuthService $authService;
    private UserDAO $userDAO;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userDAO = new UserDAO();
    }

    /**
     * Initiate phone registration - Step 1
     * POST /api/v1/auth/register/phone
     */
    #[PublicRoute(reason: 'Phone registration must be accessible without authentication')]
    #[Route('/phone', method: 'POST', middleware: ['jsonParser'], description: 'Initiate phone registration with OTP')]
    public function initiateRegistration(array $request): void
    {
        $correlationId = TraceContext::getCorrelationId();
        $transactionId = TraceContext::getTransactionId();
        
        try {
            $data = $request['body'] ?? [];
            
            $validator = new InputValidator();
            $rules = [
                'phone' => 'required|phone|min:10|max:20',
                'first_name' => 'required|string|min:1|max:100',
                'last_name' => 'required|string|min:1|max:100', 
                'password' => 'required|string|min:8|max:255'
            ];
            
            if (!$validator->validateBody($data, $rules)) {
                Response::badRequest('validation.failed', $validator->getErrors());
                return;
            }
            
            Logger::info('Phone registration initiated', [
                'phone' => substr($data['phone'], 0, 4) . '***',
                'correlation_id' => $correlationId,
                'transaction_id' => $transactionId
            ]);
            
            // Generate OTP
            $otpCode = OTPGenerator::generate();
            $otpHash = hash('sha256', $otpCode);
            $otpExpiresAt = new \DateTime('+15 minutes');
            
            // Register user with phone using new service method
            $result = $this->authService->registerWithPhone(
                $data['phone'],
                $data['password'],
                $data['first_name'],
                $data['last_name'],
                $otpHash,
                $otpExpiresAt
            );
            
            Logger::info('Phone registration OTP generated', [
                'phone' => substr($data['phone'], 0, 4) . '***',
                'otp_id' => $result['otp_id'] ?? 'N/A',
                'expires_at' => $otpExpiresAt->format('Y-m-d H:i:s'),
                'correlation_id' => $correlationId
            ]);
            
            // Build response
            $responseData = [
                'message' => 'Registration OTP sent to phone number',
                'user_id' => $result['user_id'],
                'expires_at' => $otpExpiresAt->format('Y-m-d H:i:s'),
                'next_step' => 'verify_phone_registration'
            ];
            
            // In development/testing, include OTP in response (REMOVE IN PRODUCTION)
            if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                $responseData['dev_otp'] = $otpCode;
            }
            
            Response::success($responseData, 'Registration initiated successfully', 201);
            
        } catch (\Exception $e) {
            Logger::error('Phone registration error', [
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            if (str_contains($e->getMessage(), 'already registered')) {
                Response::badRequest('auth.register.phone_exists', ['phone' => 'Phone number already registered']);
            } else {
                Response::serverError('auth.register.failed');
            }
        }
    }
    
    /**
     * Verify phone registration OTP - Step 2 
     * POST /api/v1/auth/register/phone/verify
     */
    #[PublicRoute(reason: 'Phone registration OTP verification must be accessible without authentication')]
    #[Route('/phone/verify', method: 'POST', middleware: ['jsonParser'], description: 'Verify phone registration OTP')]
    public function verifyRegistration(array $request): void
    {
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            $data = $request['body'] ?? [];
            
            $validator = new InputValidator();
            $rules = [
                'phone' => 'required|phone|min:10|max:20',
                'otp' => 'required|string|min:4|max:10'
            ];
            
            if (!$validator->validateBody($data, $rules)) {
                Response::badRequest('validation.failed', $validator->getErrors());
                return;
            }
            
            Logger::info('Phone registration verification started', [
                'phone' => substr($data['phone'], 0, 4) . '***',
                'correlation_id' => $correlationId
            ]);
            
            $otpHash = hash('sha256', $data['otp']);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Verify OTP using stored procedure
            // Parameters: p_identifier, p_otp_hash, p_purpose, p_max_retries, p_ip_address, p_user_agent
            $result = Database::callProcedure('sp_verify_otp_with_retry', [
                $data['phone'],
                $otpHash,
                'phone_registration',
                3, // max attempts
                $ipAddress,
                $userAgent
            ]);
            
            if (empty($result) || empty($result[0]['is_valid'])) {
                $message = $result[0]['message'] ?? 'OTP verification failed';
                Logger::warning('Phone verification failed', [
                    'phone' => substr($data['phone'], 0, 4) . '***',
                    'reason' => $message,
                    'correlation_id' => $correlationId
                ]);
                Response::badRequest('auth.otp.invalid', ['otp' => $message]);
                return;
            }
            
            // Mark phone identifier as verified and activate user
            $verifyResult = $this->userDAO->verifyIdentifier('phone', $data['phone']);
            
            if (empty($verifyResult['success'])) {
                Logger::error('Failed to verify phone identifier', [
                    'phone' => substr($data['phone'], 0, 4) . '***',
                    'correlation_id' => $correlationId
                ]);
                Response::serverError('auth.verify.failed');
                return;
            }
            
            Logger::info('Phone registration completed', [
                'user_id' => $verifyResult['user_id'] ?? 'unknown',
                'phone' => substr($data['phone'], 0, 4) . '***',
                'correlation_id' => $correlationId
            ]);
            
            Response::success([
                'message' => 'Registration completed successfully',
                'user_id' => $verifyResult['user_id'] ?? null,
                'status' => 'verified',
                'next_step' => 'login'
            ], 'Phone verified successfully');
            
        } catch (\Exception $e) {
            Logger::error('Phone verification error', [
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            Response::serverError('auth.verify.failed');
        }
    }
    
}