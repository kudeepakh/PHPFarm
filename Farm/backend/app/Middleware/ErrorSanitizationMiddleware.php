<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;

/**
 * Error Sanitization Middleware
 * 
 * Catches all exceptions and ensures no internal details are exposed to clients
 * Logs full details for debugging while returning sanitized error responses
 */
class ErrorSanitizationMiddleware
{
    /**
     * Known error codes that are safe to expose
     */
    private static array $safeDomainCodes = [
        'validation.failed',
        'auth.invalid_credentials', 
        'auth.token_expired',
        'auth.token_invalid',
        'error.not_found',
        'error.forbidden',
        'error.unauthorized',
        'error.too_many_requests',
        'error.conflict',
        'error.bad_request',
        'otp.invalid',
        'otp.expired',
        'otp.already_used',
        'password.too_weak',
        'file.too_large',
        'file.invalid_type'
    ];
    
    /**
     * Handle request with comprehensive error sanitization
     */
    public static function handle(array $request, callable $next): mixed
    {
        try {
            return $next($request);
        } catch (\InvalidArgumentException $e) {
            // Client-side validation errors - safe to show message
            self::logError($e, $request, 'validation_error');
            Response::badRequest('validation.failed', [
                'message' => self::sanitizeMessage($e->getMessage())
            ]);
        } catch (\UnauthorizedHttpException $e) {
            self::logError($e, $request, 'unauthorized');
            Response::unauthorized('error.unauthorized');
        } catch (\ForbiddenHttpException $e) {
            self::logError($e, $request, 'forbidden');
            Response::forbidden('error.forbidden');
        } catch (\NotFoundHttpException $e) {
            self::logError($e, $request, 'not_found');
            Response::notFound('error.not_found');
        } catch (\TooManyRequestsHttpException $e) {
            self::logError($e, $request, 'rate_limit');
            Response::tooManyRequests('error.too_many_requests');
        } catch (\PDOException $e) {
            // Database errors - NEVER expose to client
            self::logError($e, $request, 'database_error');
            Response::serverError('error.database_unavailable', 503, 'ERR_DATABASE_UNAVAILABLE');
        } catch (\RuntimeException $e) {
            // Runtime errors - check if it's a known domain error
            $message = $e->getMessage();
            
            if (self::isSafeDomainError($message)) {
                self::logError($e, $request, 'domain_error');
                Response::serverError($message, 400, 'ERR_DOMAIN');
            } else {
                // Unknown runtime error - sanitize
                self::logError($e, $request, 'runtime_error');
                Response::serverError('error.unexpected', 500, 'ERR_INTERNAL_SERVER');
            }
        } catch (\Exception $e) {
            // All other exceptions - NEVER expose details
            self::logError($e, $request, 'unhandled_exception');
            Response::serverError('error.unexpected', 500, 'ERR_INTERNAL_SERVER');
        } catch (\Error $e) {
            // Fatal errors - log and return generic error
            self::logError($e, $request, 'fatal_error');
            Response::serverError('error.system_error', 500, 'ERR_SYSTEM_ERROR');
        }
    }
    
    /**
     * Check if error message is a safe domain error code
     */
    private static function isSafeDomainError(string $message): bool
    {
        return in_array($message, self::$safeDomainCodes, true);
    }
    
    /**
     * Sanitize error message for client consumption
     */
    private static function sanitizeMessage(string $message): string
    {
        // Remove any sensitive information patterns
        $sanitized = preg_replace([
            '/password[:\s]*[^\s]*/i',
            '/token[:\s]*[^\s]*/i',
            '/api[_\s]?key[:\s]*[^\s]*/i',
            '/secret[:\s]*[^\s]*/i',
            '/connection[:\s]*[^\s]*/i',
            '/database[:\s]*[^\s]*/i',
            '/sql[:\s]*[^\s]*/i',
            '/query[:\s]*[^\s]*/i',
        ], '[REDACTED]', $message);
        
        // Truncate very long messages
        if (strlen($sanitized) > 200) {
            $sanitized = substr($sanitized, 0, 200) . '...';
        }
        
        return $sanitized;
    }
    
    /**
     * Log error with full context and trace information
     */
    private static function logError(\Throwable $error, array $request, string $errorType): void
    {
        $context = [
            'error_type' => $errorType,
            'error_class' => get_class($error),
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'stack_trace' => $error->getTraceAsString(),
            'request_method' => $request['method'] ?? 'UNKNOWN',
            'request_uri' => $request['uri'] ?? 'UNKNOWN',
            'request_headers' => $request['headers'] ?? [],
            'user_agent' => $request['headers']['User-Agent'] ?? 'UNKNOWN',
            'ip_address' => $request['headers']['X-Forwarded-For'] ?? 
                           $request['headers']['X-Real-IP'] ?? 
                           $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'correlation_id' => TraceContext::getCorrelationId(),
            'transaction_id' => TraceContext::getTransactionId(),
            'request_id' => TraceContext::getRequestId(),
        ];
        
        // Log different severity levels based on error type
        match($errorType) {
            'fatal_error', 'unhandled_exception' => Logger::error('CRITICAL: Unhandled error occurred', $context),
            'database_error' => Logger::error('Database error occurred', $context),
            'runtime_error' => Logger::error('Runtime error occurred', $context),
            'validation_error' => Logger::warning('Validation error occurred', $context),
            default => Logger::error('Error occurred', $context)
        };
        
        // Also log to security log for suspicious patterns
        if (in_array($errorType, ['database_error', 'fatal_error'], true)) {
            Logger::security('Potential security issue detected', [
                'error_type' => $errorType,
                'ip_address' => $context['ip_address'],
                'user_agent' => $context['user_agent'],
                'correlation_id' => $context['correlation_id']
            ]);
        }
    }
}