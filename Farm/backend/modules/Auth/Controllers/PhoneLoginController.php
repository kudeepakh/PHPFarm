<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Request;
use PHPFrarm\Core\Validation\InputValidator;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Attributes\PublicRoute;
use PHPFrarm\Modules\Auth\DAO\OTPDAO;
use PHPFrarm\Modules\User\DAO\UserDAO;
use PHPFrarm\Core\Database;
use PHPFrarm\Core\Exceptions\HttpExceptions\BadRequestHttpException;
use PHPFrarm\Core\Exceptions\HttpExceptions\UnauthorizedHttpException;
use PHPFrarm\Core\Exceptions\HttpExceptions\HttpException;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Modules\Auth\Services\PhoneAuthService;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Utils\OTPGenerator;

/**
 * Phone Login Controller
 * Handles phone-based user login with OTP verification
 */
#[RouteGroup('/api/v1/auth/login', middleware: ['cors', 'rateLimit'])]
class PhoneLoginController
{
    private OTPDAO $otpDAO;
    private UserDAO $userDAO;
    private AuthService $authService;
    private PhoneAuthService $phoneAuthService;
    
    public function __construct()
    {
        $this->otpDAO = new OTPDAO();
        $this->userDAO = new UserDAO();
        $this->authService = new AuthService();
        $this->phoneAuthService = new PhoneAuthService();
    }

    /**
     * Initiate phone login - Step 1
     * POST /api/v1/auth/login/phone
     */
    #[PublicRoute(reason: 'Phone login initiation must be accessible without authentication')]
    #[Route('/phone', method: 'POST', middleware: ['jsonParser'], description: 'Initiate phone login with OTP')]
    public function initiateLogin(): Response
    {
        $correlationId = TraceContext::getCorrelationId();
        $transactionId = TraceContext::getTransactionId();
        
        try {
            // Validate request data
            $data = Request::getJsonBody();
            
            $validator = new InputValidator([
                'phone' => 'required|phone|min:10|max:20'
            ]);
            
            $validatedData = $validator->validate($data);
            
            Logger::info('Phone login initiated', [
                'phone' => substr($validatedData['phone'], 0, 3) . '***',
                'correlation_id' => $correlationId,
                'transaction_id' => $transactionId
            ]);
            
            // Generate OTP
            $otpCode = $this->generateOTP();
            $otpId = $this->generateUUIDv4();
            $otpHash = hash('sha256', $otpCode);
            $expiresAt = (new \DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');
            
            // Call stored procedure to initiate phone login
            $result = Database::callProcedure('sp_initiate_phone_login', [
                $validatedData['phone'],
                $otpId,
                $otpHash,
                $expiresAt,
                $correlationId
            ]);
            
            if (empty($result) || !$result[0]['success']) {
                $message = $result[0]['message'] ?? 'Phone login initiation failed';
                Logger::warning('Phone login initiation failed', [
                    'phone' => substr($validatedData['phone'], 0, 3) . '***',
                    'reason' => $message,
                    'correlation_id' => $correlationId
                ]);
                
                if (strpos($message, 'not registered') !== false) {
                    throw new UnauthorizedHttpException($message);
                }
                
                throw new BadRequestHttpException($message);
            }
            
            // NOTE: SMS integration required for production
            // Integrate with: Twilio, AWS SNS, or MessageBird
            // Configure SMS provider in config/notifications.php
            // For development, OTP is logged for testing purposes
            Logger::info('Login OTP generated', [
                'phone' => substr($validatedData['phone'], 0, 3) . '***',
                'user_id' => $result[0]['user_id'],
                'otp_id' => $otpId,
                'expires_at' => $expiresAt,
                'correlation_id' => $correlationId
            ]);
            
            // Response without exposing OTP (security best practice)
            $responseData = [
                'message' => 'Login OTP sent to phone number',
                'user_id' => $result[0]['user_id'],
                'otp_id' => $result[0]['otp_id'],
                'expires_at' => $expiresAt,
                'next_step' => 'verify_phone_login'
            ];
            
            return Response::success($responseData, 'Login OTP sent successfully');
            
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Logger::error('Phone login initiation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'correlation_id' => $correlationId
            ]);
            
            throw new BadRequestHttpException('Login initiation failed');
        }
    }
    
    /**
     * Verify phone login OTP - Step 2 
     * POST /api/v1/auth/login/phone/verify
     */
    #[PublicRoute(reason: 'Phone login OTP verification must be accessible without authentication')]
    #[Route('/phone/verify', method: 'POST', middleware: ['jsonParser'], description: 'Verify phone login OTP and return JWT tokens')]
    public function verifyLogin(): Response
    {
        $correlationId = TraceContext::getCorrelationId();
        $transactionId = TraceContext::getTransactionId();
        
        try {
            // Validate request data
            $data = Request::getJsonBody();
            
            $validator = new InputValidator([
                'phone' => 'required|phone|min:10|max:20',
                'otp' => 'required|string|min:4|max:10'
            ]);
            
            $validatedData = $validator->validate($data);
            
            Logger::info('Phone login verification started', [
                'phone' => substr($validatedData['phone'], 0, 3) . '***',
                'correlation_id' => $correlationId,
                'transaction_id' => $transactionId
            ]);
            
            $otpHash = hash('sha256', $validatedData['otp']);
            $maxRetries = 3;
            $ipAddress = Request::getClientIp();
            $userAgent = Request::getHeader('User-Agent');
            
            // Verify login OTP via stored procedure
            $result = Database::callProcedure('sp_login_with_phone_otp', [
                $validatedData['phone'],
                $otpHash,
                $maxRetries,
                $ipAddress,
                $userAgent,
                $correlationId
            ]);
            
            if (empty($result) || !$result[0]['success']) {
                $message = $result[0]['message'] ?? 'OTP verification failed';
                Logger::warning('Phone login verification failed', [
                    'phone' => substr($validatedData['phone'], 0, 3) . '***',
                    'reason' => $message,
                    'ip' => $ipAddress,
                    'correlation_id' => $correlationId
                ]);
                
                throw new UnauthorizedHttpException($message);
            }
            
            // Parse user data from stored procedure result
            $userData = json_decode($result[0]['user_data'], true);
            
            if (!$userData) {
                Logger::error('Invalid user data returned from phone login verification', [
                    'phone' => substr($validatedData['phone'], 0, 3) . '***',
                    'raw_data' => $result[0]['user_data'],
                    'correlation_id' => $correlationId
                ]);
                
                throw new BadRequestHttpException('Login verification failed');
            }
            
            // Generate JWT tokens using AuthService approach
            $userId = $userData['user_id'];
            $email = $userData['email'];
            
            // Get user roles and permissions for JWT claims
            try {
                // For now, use default role - proper role system can be enhanced later
                $role = 'user'; 
                $tokenVersion = 0; // Default token version
                $sessionId = UuidGenerator::v4();
                
                // Create JWT tokens using PhoneAuthService
                $tokens = $this->phoneAuthService->generateJWTTokens([
                    'user_id' => $userId,
                    'email' => $email,
                    'phone' => $userData['phone'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'status' => $userData['status'],
                    'role' => $role,
                    'token_version' => $tokenVersion,
                    'session_id' => $sessionId,
                    'login_method' => 'phone_otp'
                ]);
                
                // Create session record using PhoneAuthService
                $this->phoneAuthService->createUserSession([
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'device_info' => $userAgent,
                    'ip_address' => $ipAddress,
                    'access_expires_at' => $tokens['access_expires_at'],
                    'refresh_expires_at' => $tokens['refresh_expires_at']
                ]);
                
            } catch (\Exception $e) {
                Logger::error('JWT token generation failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'correlation_id' => $correlationId
                ]);
                
                throw new BadRequestHttpException('Login processing failed');
            }
            
            Logger::info('Phone login completed successfully', [
                'user_id' => $userData['user_id'],
                'phone' => substr($validatedData['phone'], 0, 3) . '***',
                'session_id' => $sessionId,
                'ip' => $ipAddress,
                'correlation_id' => $correlationId
            ]);
            
            return Response::success([
                'message' => 'Login successful',
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => 'Bearer',
                'expires_in' => $tokens['expires_in'],
                'user' => [
                    'user_id' => $userData['user_id'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'status' => $userData['status']
                ]
            ], 'Phone login completed');
            
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Logger::error('Phone login verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'correlation_id' => $correlationId
            ]);
            
            throw new BadRequestHttpException('Login verification failed');
        }
    }
}