<?php

namespace Farm\Backend\App\Core\Security;

use Farm\Backend\App\Core\Logging\LogManager;
use Farm\Backend\App\Core\Observability\TraceContext;

/**
 * WafEngine - Web Application Firewall for attack detection
 * 
 * Detects common attack vectors:
 * - SQL Injection
 * - Cross-Site Scripting (XSS)
 * - Path Traversal
 * - Command Injection
 * - LDAP Injection
 * - XML External Entity (XXE)
 * - Server-Side Request Forgery (SSRF)
 * 
 * Rule-based detection with configurable sensitivity.
 * Can be integrated with external WAF services (Cloudflare, AWS WAF, etc.)
 */
class WafEngine
{
    private LogManager $logger;
    private array $config;
    
    // SQL Injection patterns
    private const SQL_INJECTION_PATTERNS = [
        '/(\bunion\b.*\bselect\b|\bselect\b.*\bunion\b)/i',
        '/\b(select|insert|update|delete|drop|create|alter)\b.*\b(from|into|table|database)\b/i',
        '/(\'|\")\s*(or|and)\s*(\d+|\'|\")?\s*=\s*(\d+|\'|\")/i',
        '/\/\*.*\*\//i',                                    // SQL comments
        '/(-{2}|#).*$/m',                                   // SQL comments
        '/\bexec\b.*\(/i',                                  // Stored procedure execution
        '/\bxp_cmdshell\b/i',                               // SQL Server command execution
        '/;\s*(drop|delete|truncate|alter)/i',              // Statement chaining
    ];
    
    // XSS patterns
    private const XSS_PATTERNS = [
        '/<script[^>]*>.*<\/script>/is',
        '/javascript:/i',
        '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',              // Event handlers (onclick, onerror, etc.)
        '/<iframe[^>]*>/i',
        '/<object[^>]*>/i',
        '/<embed[^>]*>/i',
        '/<img[^>]*src\s*=\s*["\']?javascript:/i',
        '/alert\s*\(/i',
        '/document\.cookie/i',
        '/eval\s*\(/i',
    ];
    
    // Path Traversal patterns
    private const PATH_TRAVERSAL_PATTERNS = [
        '/\.\.\//',                                         // ../
        '/\.\.\\//',                                        // ..\
        '/%2e%2e%2f/i',                                     // URL encoded ../
        '/%252e%252e%252f/i',                               // Double URL encoded
        '/\.\.\x00/i',                                      // Null byte injection
        '/\/etc\/passwd/i',
        '/\/windows\/system32\//i',
    ];
    
    // Command Injection patterns
    private const COMMAND_INJECTION_PATTERNS = [
        '/[;&|`$\(\){}]/i',                                 // Shell metacharacters
        '/\b(cat|ls|pwd|whoami|wget|curl|nc|netcat)\b/i',
        '/\$\{.*\}/i',                                      // Variable substitution
        '/\$\(.*\)/i',                                      // Command substitution
    ];
    
    // LDAP Injection patterns
    private const LDAP_INJECTION_PATTERNS = [
        '/[*()|\&]/i',                                      // LDAP special chars
        '/\(\|/i',                                          // LDAP OR
        '/\(&/i',                                           // LDAP AND
    ];
    
    // XXE patterns
    private const XXE_PATTERNS = [
        '/<!DOCTYPE[^>]*<!ENTITY/is',
        '/<!ENTITY[^>]*SYSTEM/is',
        '/<!ENTITY[^>]*PUBLIC/is',
    ];
    
    // SSRF patterns
    private const SSRF_PATTERNS = [
        '/(localhost|127\.0\.0\.1|0\.0\.0\.0)/i',
        '/(169\.254\.169\.254)/i',                          // AWS metadata
        '/(metadata\.google\.internal)/i',                  // GCP metadata
    ];

    public function __construct(LogManager $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'enabled' => true,
            'sensitivity' => 'medium',      // 'low', 'medium', 'high'
            'log_detections' => true,
            'block_on_detection' => true,
            'custom_rules' => [],           // User-defined regex patterns
        ], $config);
    }

    /**
     * Scan input for all attack types
     * 
     * @param mixed $input Input to scan (string, array, etc.)
     * @param string $context Context (query, body, header, path)
     * @return array Detection result
     */
    public function scan($input, string $context = 'unknown'): array
    {
        if (!$this->config['enabled']) {
            return ['detected' => false, 'attacks' => []];
        }
        
        $attacks = [];
        
        // Convert input to string for scanning
        $inputString = $this->normalizeInput($input);
        
        // Run all detections
        if ($this->detectSqlInjection($inputString)) {
            $attacks[] = 'sql_injection';
        }
        
        if ($this->detectXss($inputString)) {
            $attacks[] = 'xss';
        }
        
        if ($this->detectPathTraversal($inputString)) {
            $attacks[] = 'path_traversal';
        }
        
        if ($this->detectCommandInjection($inputString)) {
            $attacks[] = 'command_injection';
        }
        
        if ($this->detectLdapInjection($inputString)) {
            $attacks[] = 'ldap_injection';
        }
        
        if ($this->detectXxe($inputString)) {
            $attacks[] = 'xxe';
        }
        
        if ($this->detectSsrf($inputString)) {
            $attacks[] = 'ssrf';
        }
        
        // Check custom rules
        foreach ($this->config['custom_rules'] as $ruleName => $pattern) {
            if (preg_match($pattern, $inputString)) {
                $attacks[] = "custom_rule:{$ruleName}";
            }
        }
        
        // Log if attacks detected
        if (!empty($attacks) && $this->config['log_detections']) {
            $this->logDetection($attacks, $inputString, $context);
        }
        
        return [
            'detected' => !empty($attacks),
            'attacks' => $attacks,
            'severity' => $this->calculateSeverity($attacks),
            'should_block' => $this->config['block_on_detection'] && !empty($attacks),
        ];
    }

    /**
     * Detect SQL Injection
     * 
     * @param string $input
     * @return bool
     */
    public function detectSqlInjection(string $input): bool
    {
        return $this->matchPatterns($input, self::SQL_INJECTION_PATTERNS);
    }

    /**
     * Detect Cross-Site Scripting (XSS)
     * 
     * @param string $input
     * @return bool
     */
    public function detectXss(string $input): bool
    {
        return $this->matchPatterns($input, self::XSS_PATTERNS);
    }

    /**
     * Detect Path Traversal
     * 
     * @param string $input
     * @return bool
     */
    public function detectPathTraversal(string $input): bool
    {
        return $this->matchPatterns($input, self::PATH_TRAVERSAL_PATTERNS);
    }

    /**
     * Detect Command Injection
     * 
     * @param string $input
     * @return bool
     */
    public function detectCommandInjection(string $input): bool
    {
        return $this->matchPatterns($input, self::COMMAND_INJECTION_PATTERNS);
    }

    /**
     * Detect LDAP Injection
     * 
     * @param string $input
     * @return bool
     */
    public function detectLdapInjection(string $input): bool
    {
        return $this->matchPatterns($input, self::LDAP_INJECTION_PATTERNS);
    }

    /**
     * Detect XXE (XML External Entity)
     * 
     * @param string $input
     * @return bool
     */
    public function detectXxe(string $input): bool
    {
        return $this->matchPatterns($input, self::XXE_PATTERNS);
    }

    /**
     * Detect SSRF (Server-Side Request Forgery)
     * 
     * @param string $input
     * @return bool
     */
    public function detectSsrf(string $input): bool
    {
        return $this->matchPatterns($input, self::SSRF_PATTERNS);
    }

    /**
     * Match input against pattern list
     * 
     * @param string $input
     * @param array $patterns Regex patterns
     * @return bool True if any pattern matches
     */
    private function matchPatterns(string $input, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Normalize input for scanning
     * 
     * @param mixed $input
     * @return string
     */
    private function normalizeInput($input): string
    {
        if (is_string($input)) {
            return $input;
        }
        
        if (is_array($input)) {
            return json_encode($input);
        }
        
        if (is_object($input)) {
            return json_encode($input);
        }
        
        return (string) $input;
    }

    /**
     * Calculate severity (1-10)
     * 
     * @param array $attacks List of detected attacks
     * @return int Severity score
     */
    private function calculateSeverity(array $attacks): int
    {
        if (empty($attacks)) {
            return 0;
        }
        
        $weights = [
            'sql_injection' => 10,
            'command_injection' => 10,
            'xxe' => 9,
            'ssrf' => 8,
            'xss' => 7,
            'path_traversal' => 6,
            'ldap_injection' => 6,
        ];
        
        $maxSeverity = 0;
        foreach ($attacks as $attack) {
            $severity = $weights[$attack] ?? 5;
            $maxSeverity = max($maxSeverity, $severity);
        }
        
        return $maxSeverity;
    }

    /**
     * Log attack detection
     * 
     * @param array $attacks
     * @param string $input
     * @param string $context
     */
    private function logDetection(array $attacks, string $input, string $context): void
    {
        $this->logger->security('waf_attack_detected', [
            'attacks' => $attacks,
            'input' => substr($input, 0, 500), // Truncate long inputs
            'context' => $context,
            'severity' => $this->calculateSeverity($attacks),
            'correlation_id' => TraceContext::getCorrelationId(),
            'timestamp' => time(),
        ]);
    }

    /**
     * Add custom detection rule
     * 
     * @param string $name Rule name
     * @param string $pattern Regex pattern
     */
    public function addCustomRule(string $name, string $pattern): void
    {
        $this->config['custom_rules'][$name] = $pattern;
    }

    /**
     * Sanitize input (remove detected attacks)
     * 
     * WARNING: Sanitization is NOT recommended. Block requests instead.
     * 
     * @param string $input
     * @return string Sanitized input
     */
    public function sanitize(string $input): string
    {
        // Remove SQL injection patterns
        foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        // Remove XSS patterns
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        // Remove path traversal
        $input = str_replace(['../', '..\\'], '', $input);
        
        return $input;
    }

    /**
     * Get statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'enabled' => $this->config['enabled'],
            'sensitivity' => $this->config['sensitivity'],
            'block_on_detection' => $this->config['block_on_detection'],
            'custom_rules_count' => count($this->config['custom_rules']),
            'detection_types' => [
                'sql_injection',
                'xss',
                'path_traversal',
                'command_injection',
                'ldap_injection',
                'xxe',
                'ssrf',
            ],
        ];
    }
}
