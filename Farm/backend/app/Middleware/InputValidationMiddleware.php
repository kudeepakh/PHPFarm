<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Validation\InputValidator;

/**
 * Input Validation Middleware
 * 
 * Validates all incoming request data: headers, query params, path variables.
 * Works in conjunction with route-level validation attributes.
 * 
 * Features:
 * - Content-Type validation
 * - Accept header validation
 * - Required headers enforcement
 * - Query parameter type checking
 * - Path variable format validation
 * - Request body size limits
 * 
 * @package PHPFrarm\Middleware
 */
class InputValidationMiddleware
{
    private array $config;
    private InputValidator $validator;
    
    /**
     * Allowed Content-Types for request body
     */
    private const ALLOWED_CONTENT_TYPES = [
        'application/json',
        'application/x-www-form-urlencoded',
        'multipart/form-data',
    ];
    
    /**
     * Required headers for all requests
     */
    private const REQUIRED_HEADERS = [
        // 'X-Correlation-Id', // Usually auto-generated if missing
    ];
    
    /**
     * Headers that should be validated if present
     */
    private const HEADER_RULES = [
        'X-Correlation-Id' => ['format' => 'ulid', 'max' => 36],
        'X-Transaction-Id' => ['format' => 'ulid', 'max' => 36],
        'X-Request-Id' => ['format' => 'ulid', 'max' => 36],
        'Authorization' => ['pattern' => '/^Bearer\s+[\w\-\.]+$/'],
        'Accept' => ['pattern' => '/(^|,)\s*(application\/json|\*\/\*|application\/\*)(\s*;\s*q=[0-9.]+)?\s*(,|$)/i'],
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->validator = new InputValidator();
    }
    
    /**
     * Load configuration
     */
    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/validation.php';
        return file_exists($configPath) ? require $configPath : [
            'enforce_content_type' => true,
            'enforce_accept_header' => false,
            'validate_headers' => true,
            'validate_query_params' => true,
            'max_query_param_length' => 1000,
            'max_header_length' => 8192,
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
        ];
    }
    
    /**
     * Handle the request
     * 
     * @param callable $next Next middleware
     * @return mixed
     */
    public function handle(callable $next): mixed
    {
        // Validate HTTP method
        if (!$this->validateMethod()) {
            return null;
        }
        
        // Validate Content-Type for requests with body
        if (!$this->validateContentType()) {
            return null;
        }
        
        // Validate Accept header
        if (!$this->validateAcceptHeader()) {
            return null;
        }
        
        // Validate required headers
        if (!$this->validateRequiredHeaders()) {
            return null;
        }
        
        // Validate header formats
        if (!$this->validateHeaderFormats()) {
            return null;
        }
        
        // Validate query parameters
        if (!$this->validateQueryParameters()) {
            return null;
        }
        
        // All validations passed, continue
        return $next();
    }
    
    /**
     * Validate HTTP method
     */
    private function validateMethod(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowed = $this->config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        
        if (!in_array($method, $allowed, true)) {
            Logger::warning('Invalid HTTP method', ['method' => $method]);
            Response::error(
                "Method {$method} not allowed",
                405,
                'METHOD_NOT_ALLOWED',
                ['allowed_methods' => $allowed]
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate Content-Type header
     */
    private function validateContentType(): bool
    {
        if (!($this->config['enforce_content_type'] ?? true)) {
            return true;
        }
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Only validate for methods that typically have a body
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return true;
        }
        
        // Check if there's a body
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ((int) $contentLength === 0) {
            return true;
        }
        
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $contentType = strtolower(explode(';', $contentType)[0]); // Remove charset etc.
        
        // Enforce JSON for specific path prefixes
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $jsonPaths = $this->config['require_json_paths'] ?? [];
        foreach ($jsonPaths as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                if ($contentType !== 'application/json') {
                    Logger::warning('JSON Content-Type required', [
                        'path' => $path,
                        'content_type' => $contentType
                    ]);
                    Response::error(
                        'Unsupported Content-Type: application/json required',
                        415,
                        'UNSUPPORTED_MEDIA_TYPE'
                    );
                    return false;
                }
                break;
            }
        }

        $allowed = $this->config['allowed_content_types'] ?? self::ALLOWED_CONTENT_TYPES;
        
        if (!in_array($contentType, $allowed, true)) {
            Logger::warning('Invalid Content-Type', [
                'content_type' => $contentType,
                'allowed' => $allowed,
            ]);
            Response::error(
                "Unsupported Content-Type: {$contentType}",
                415,
                'UNSUPPORTED_MEDIA_TYPE',
                ['allowed_content_types' => $allowed]
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate Accept header
     */
    private function validateAcceptHeader(): bool
    {
        if (!($this->config['enforce_accept_header'] ?? false)) {
            return true;
        }
        
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
        
        // Check if client accepts JSON
        $acceptable = ['application/json', '*/*', 'application/*'];
        $accepts = array_map('trim', explode(',', $accept));
        
        $found = false;
        foreach ($accepts as $type) {
            // Remove quality value
            $type = explode(';', $type)[0];
            
            if (in_array($type, $acceptable, true)) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            Logger::warning('Unacceptable Accept header', ['accept' => $accept]);
            Response::error(
                'This API only returns application/json',
                406,
                'NOT_ACCEPTABLE',
                ['supported_types' => ['application/json']]
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate required headers
     */
    private function validateRequiredHeaders(): bool
    {
        if (!($this->config['validate_headers'] ?? true)) {
            return true;
        }
        
        $required = $this->config['required_headers'] ?? self::REQUIRED_HEADERS;
        $missing = [];
        
        foreach ($required as $header) {
            $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
            
            if (empty($_SERVER[$normalized])) {
                $missing[] = $header;
            }
        }
        
        if (!empty($missing)) {
            Logger::warning('Missing required headers', ['headers' => $missing]);
            Response::error(
                'Missing required headers: ' . implode(', ', $missing),
                400,
                'MISSING_HEADERS',
                ['missing_headers' => $missing]
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate header formats
     */
    private function validateHeaderFormats(): bool
    {
        if (!($this->config['validate_headers'] ?? true)) {
            return true;
        }
        
        $rules = $this->config['header_rules'] ?? self::HEADER_RULES;
        $maxLength = $this->config['max_header_length'] ?? 8192;
        
        foreach ($rules as $header => $rule) {
            $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
            $value = $_SERVER[$normalized] ?? null;
            
            if ($value === null) {
                continue; // Skip if not present
            }
            
            // Check length
            if (strlen($value) > $maxLength) {
                Logger::warning('Header too long', [
                    'header' => $header,
                    'length' => strlen($value),
                    'max' => $maxLength,
                ]);
                Response::error(
                    "Header {$header} exceeds maximum length",
                    400,
                    'HEADER_TOO_LONG'
                );
                return false;
            }
            
            // Validate format if specified
            if (isset($rule['pattern'])) {
                if (!preg_match($rule['pattern'], $value)) {
                    Logger::warning('Invalid header format', [
                        'header' => $header,
                        'value' => $this->maskValue($value),
                    ]);
                    Response::error(
                        "Invalid format for header {$header}",
                        400,
                        'INVALID_HEADER_FORMAT'
                    );
                    return false;
                }
            }
            
            // Check allowed values
            if (isset($rule['in'])) {
                if (strtolower($header) === 'accept') {
                    $allowed = (array) $rule['in'];
                    $accepts = array_map('trim', explode(',', $value));
                    $matched = false;

                    foreach ($accepts as $type) {
                        $type = explode(';', $type)[0];
                        if (in_array($type, $allowed, true)) {
                            $matched = true;
                            break;
                        }
                    }

                    if ($matched) {
                        continue;
                    }
                }

                if (!in_array($value, (array) $rule['in'], true)) {
                    Logger::warning('Invalid header value', [
                        'header' => $header,
                        'value' => $value,
                        'allowed' => $rule['in'],
                    ]);
                    Response::error(
                        "Invalid value for header {$header}",
                        400,
                        'INVALID_HEADER_VALUE'
                    );
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate query parameters
     */
    private function validateQueryParameters(): bool
    {
        if (!($this->config['validate_query_params'] ?? true)) {
            return true;
        }
        
        $maxLength = $this->config['max_query_param_length'] ?? 1000;
        $maxParams = $this->config['max_query_params'] ?? 50;
        
        // Check total number of parameters
        if (count($_GET) > $maxParams) {
            Logger::warning('Too many query parameters', [
                'count' => count($_GET),
                'max' => $maxParams,
            ]);
            Response::error(
                "Too many query parameters. Maximum allowed: {$maxParams}",
                400,
                'TOO_MANY_QUERY_PARAMS'
            );
            return false;
        }
        
        // Validate each parameter
        foreach ($_GET as $key => $value) {
            // Validate key format
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\[\]\.]*$/', $key)) {
                Logger::warning('Invalid query parameter name', ['key' => $key]);
                Response::error(
                    "Invalid query parameter name: {$key}",
                    400,
                    'INVALID_QUERY_PARAM_NAME'
                );
                return false;
            }
            
            // Check value length
            $valueStr = is_array($value) ? json_encode($value) : (string) $value;
            if (strlen($valueStr) > $maxLength) {
                Logger::warning('Query parameter too long', [
                    'key' => $key,
                    'length' => strlen($valueStr),
                    'max' => $maxLength,
                ]);
                Response::error(
                    "Query parameter '{$key}' exceeds maximum length",
                    400,
                    'QUERY_PARAM_TOO_LONG'
                );
                return false;
            }
            
            // Check for dangerous patterns
            if ($this->containsDangerousPatterns($valueStr)) {
                Logger::security('Suspicious query parameter', [
                    'key' => $key,
                    'value' => $this->maskValue($valueStr),
                ]);
                Response::error(
                    'Invalid characters in query parameter',
                    400,
                    'SUSPICIOUS_INPUT'
                );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check for dangerous patterns
     */
    private function containsDangerousPatterns(string $value): bool
    {
        $patterns = [
            // SQL Injection attempts
            '/(\bunion\b.*\bselect\b|\bselect\b.*\bfrom\b.*\bwhere\b)/i',
            '/(\bdrop\b.*\btable\b|\bdelete\b.*\bfrom\b)/i',
            '/(\bor\b\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+)/i',
            
            // Script injection
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            
            // Path traversal
            '/\.\.\/|\.\.\\\\/',
            
            // Null bytes
            '/\x00/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mask sensitive value for logging
     */
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }
        
        return substr($value, 0, 4) . str_repeat('*', 4) . substr($value, -4);
    }
    
    /**
     * Validate path parameters using route-specific rules
     * 
     * @param array $params Extracted path parameters
     * @param array $rules Validation rules from route
     * @return bool
     */
    public function validatePathParams(array $params, array $rules): bool
    {
        $this->validator->reset();
        
        if (!$this->validator->validatePathParams($params, $rules)) {
            Response::validationError($this->validator->getFirstErrors());
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate request body using provided rules
     * 
     * @param array $rules Validation rules
     * @return bool
     */
    public function validateRequestBody(array $rules): bool
    {
        $this->validator->reset();
        
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        
        if (!$this->validator->validateBody($body, $rules)) {
            Response::validationError($this->validator->getFirstErrors());
            return false;
        }
        
        return true;
    }
    
    /**
     * Get validated data
     */
    public function getValidated(): array
    {
        return $this->validator->getValidated();
    }
}
