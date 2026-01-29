<?php

namespace PHPFrarm\Core;

use PHPFrarm\Core\I18n\Translator;

/**
 * Response class - Standard response envelope for all APIs
 */
class Response
{
    private static bool $testMode = false;
    private static ?array $lastResponse = null;

    /**
     * Send success response
     */
    public static function success(
        mixed $data = null, 
        string $message = 'success', 
        int $statusCode = 200,
        array $meta = []
    ): void {
        $message = Translator::has($message) ? Translator::translate($message) : $message;
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge($meta, [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'api_version' => ApiVersion::current(),
                'locale' => Translator::getLocale(),
            ]),
            'trace' => TraceContext::getAll(),
        ], $statusCode);
    }

    /**
     * Send error response
     */
    public static function error(
        string $message = 'error.generic',
        int $statusCode = 500,
        ?string $errorCode = null,
        array $errors = []
    ): void {
        $message = Translator::has($message) ? Translator::translate($message) : $message;
        self::send([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode ?? 'ERR_' . $statusCode,
            'errors' => $errors,
            'trace' => TraceContext::getAll(),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $statusCode);
    }

    /**
     * Send paginated response
     */
    public static function paginated(
        array $data,
        int $total,
        int $page,
        int $perPage,
        string $message = 'success'
    ): void {
        self::success($data, $message, 200, [
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ]
        ]);
    }

    /**
     * Send 207 Multi-Status
     */
    public static function multiStatus(mixed $data = null, string $message = 'success'): void
    {
        self::success($data, $message, 207);
    }

    /**
     * Send response
     */
    private static function send(array $response, int $statusCode): void
    {
        if (self::$testMode) {
            self::$lastResponse = [
                'status' => $statusCode,
                'body' => $response,
            ];
            return;
        }

        http_response_code($statusCode);
        TraceContext::setResponseHeaders();
        header('Content-Language: ' . Translator::getLocale());
        header('Content-Type: application/json');
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Enable a non-exiting test mode so responses can be asserted.
     */
    public static function enableTestMode(): void
    {
        self::$testMode = true;
        self::$lastResponse = null;
    }

    /**
     * Disable test mode and clear captured response.
     */
    public static function disableTestMode(): void
    {
        self::$testMode = false;
        self::$lastResponse = null;
    }

    /**
     * Retrieve the last captured response in test mode.
     */
    public static function getLastResponse(): ?array
    {
        return self::$lastResponse;
    }

    /**
     * Send 400 Bad Request
     */
    public static function badRequest(string $message = 'Bad Request', array $errors = []): void
    {
        // Use translated message if it's a translation key, otherwise use the message as-is
        $translatedMessage = Translator::has($message) ? Translator::translate($message) : $message;
        self::error($translatedMessage, 400, 'ERR_BAD_REQUEST', $errors);
    }

    /**
     * Send 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Unauthorized', array $errors = []): void
    {
        $translatedMessage = Translator::has($message) ? Translator::translate($message) : $message;
        self::error($translatedMessage, 401, 'ERR_UNAUTHORIZED', $errors);
    }

    /**
     * Send 403 Forbidden
     */
    public static function forbidden(string $message = 'Forbidden', array $errors = []): void
    {
        self::error(Translator::has($message) ? $message : 'error.forbidden', 403, 'ERR_FORBIDDEN', $errors);
    }

    /**
     * Send 404 Not Found
     */
    public static function notFound(string $message = 'Resource not found', array $errors = []): void
    {
        self::error(Translator::has($message) ? $message : 'error.not_found', 404, 'ERR_NOT_FOUND', $errors);
    }

    /**
     * Send 429 Too Many Requests
     */
    public static function tooManyRequests(string $message = 'Too many requests', array $errors = []): void
    {
        self::error(Translator::has($message) ? $message : 'error.too_many_requests', 429, 'ERR_RATE_LIMIT_EXCEEDED', $errors);
    }

    /**
     * Send 500 Internal Server Error
     */
    public static function serverError(
        string $message = 'Internal server error',
        int $statusCode = 500,
        ?string $errorCode = null,
        array $errors = []
    ): void {
        $translatedMessage = Translator::has($message) ? Translator::translate($message) : $message;
        self::error(
            $translatedMessage,
            $statusCode,
            $errorCode ?? 'ERR_INTERNAL_SERVER',
            $errors
        );
    }
}
