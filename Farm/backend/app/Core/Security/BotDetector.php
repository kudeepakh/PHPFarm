<?php

namespace Farm\Backend\App\Core\Security;

use Farm\Backend\App\Core\Logging\LogManager;
use Farm\Backend\App\Core\Observability\TraceContext;

/**
 * BotDetector - Identifies and classifies bot traffic
 * 
 * Detects automated bot traffic using:
 * - User-Agent analysis (pattern matching)
 * - Browser fingerprinting (headers validation)
 * - Request patterns (missing headers, suspicious behavior)
 * - Known bot databases
 * 
 * Thread-safe, stateless detection logic.
 */
class BotDetector
{
    private LogManager $logger;
    private array $config;
    
    // Known bot patterns (User-Agent matching)
    private const KNOWN_BOTS = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver',
        'scrapy', 'python-requests', 'curl', 'wget', 'httpclient',
        'bot', 'crawler', 'spider', 'scraper', 'scanner'
    ];
    
    // Good bots (search engines, monitoring services)
    private const WHITELISTED_BOTS = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot',
        'uptimerobot', 'pingdom', 'statuscake'
    ];
    
    // Suspicious User-Agent patterns
    private const SUSPICIOUS_PATTERNS = [
        '/^$/i',                           // Empty User-Agent
        '/^mozilla\/4\.0$/i',              // Very old browser
        '/python|java|perl|ruby|go\-http/i', // Programming languages
        '/curl|wget|httpclient/i',         // CLI tools
        '/scraper|harvester|extractor/i',  // Scraping tools
        '/nikto|nmap|masscan|nessus/i',    // Security scanners
    ];
    
    // Required browser headers (legitimate browsers send these)
    private const REQUIRED_BROWSER_HEADERS = [
        'Accept',
        'Accept-Language',
        'Accept-Encoding',
    ];

    public function __construct(LogManager $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'strict_mode' => false,          // Strict browser validation
            'allow_good_bots' => true,       // Allow search engine bots
            'check_fingerprint' => true,     // Browser fingerprint validation
            'log_detections' => true,        // Log bot detections
        ], $config);
    }

    /**
     * Check if the request is from a bot
     * 
     * @param array $headers Request headers (case-insensitive)
     * @param string $ip Client IP address
     * @return bool True if bot detected
     */
    public function isBot(array $headers, string $ip): bool
    {
        $headers = $this->normalizeHeaders($headers);
        $userAgent = $headers['user-agent'] ?? '';
        
        // Check if it's a known bot
        $botType = $this->detectBotType($userAgent);
        
        if ($botType) {
            // Allow whitelisted bots (search engines)
            if ($this->config['allow_good_bots'] && $this->isWhitelistedBot($botType)) {
                $this->logDetection('good_bot', $botType, $userAgent, $ip);
                return false;
            }
            
            $this->logDetection('known_bot', $botType, $userAgent, $ip);
            return true;
        }
        
        // Check suspicious patterns
        if ($this->hasSuspiciousPattern($userAgent)) {
            $this->logDetection('suspicious_pattern', 'unknown', $userAgent, $ip);
            return true;
        }
        
        // Browser fingerprint validation
        if ($this->config['check_fingerprint'] && !$this->hasValidBrowserFingerprint($headers)) {
            $this->logDetection('invalid_fingerprint', 'unknown', $userAgent, $ip);
            return true;
        }
        
        return false;
    }

    /**
     * Detect specific bot type from User-Agent
     * 
     * @param string $userAgent
     * @return string|null Bot type or null if not detected
     */
    public function detectBotType(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);
        
        foreach (self::KNOWN_BOTS as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return $bot;
            }
        }
        
        return null;
    }

    /**
     * Check if bot is whitelisted (good bot)
     * 
     * @param string $botType
     * @return bool
     */
    public function isWhitelistedBot(string $botType): bool
    {
        return in_array(strtolower($botType), self::WHITELISTED_BOTS, true);
    }

    /**
     * Check for suspicious User-Agent patterns
     * 
     * @param string $userAgent
     * @return bool
     */
    private function hasSuspiciousPattern(string $userAgent): bool
    {
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate browser fingerprint (required headers)
     * 
     * Legitimate browsers send specific headers.
     * Missing headers indicate bot or scripted client.
     * 
     * @param array $headers Normalized headers
     * @return bool True if valid browser fingerprint
     */
    private function hasValidBrowserFingerprint(array $headers): bool
    {
        // Check required headers
        foreach (self::REQUIRED_BROWSER_HEADERS as $required) {
            $key = strtolower($required);
            if (!isset($headers[$key]) || empty($headers[$key])) {
                return false;
            }
        }
        
        // Strict mode: Additional validation
        if ($this->config['strict_mode']) {
            // Check for realistic Accept header
            if (!$this->hasRealisticAcceptHeader($headers['accept'] ?? '')) {
                return false;
            }
            
            // Check for Accept-Language
            if (!$this->hasRealisticLanguageHeader($headers['accept-language'] ?? '')) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate Accept header (browsers send specific formats)
     * 
     * @param string $accept
     * @return bool
     */
    private function hasRealisticAcceptHeader(string $accept): bool
    {
        // Browsers typically accept text/html
        return (
            strpos($accept, 'text/html') !== false ||
            strpos($accept, '*/*') !== false
        );
    }

    /**
     * Validate Accept-Language header
     * 
     * @param string $language
     * @return bool
     */
    private function hasRealisticLanguageHeader(string $language): bool
    {
        // Should match format like "en-US,en;q=0.9"
        return preg_match('/^[a-z]{2}(-[A-Z]{2})?(,[a-z]{2}.*)?$/i', $language) === 1;
    }

    /**
     * Get detailed bot analysis
     * 
     * @param array $headers Request headers
     * @param string $ip Client IP
     * @return array Analysis result
     */
    public function analyzeBotTraffic(array $headers, string $ip): array
    {
        $headers = $this->normalizeHeaders($headers);
        $userAgent = $headers['user-agent'] ?? '';
        
        $botType = $this->detectBotType($userAgent);
        $isWhitelisted = $botType && $this->isWhitelistedBot($botType);
        $hasSuspicious = $this->hasSuspiciousPattern($userAgent);
        $validFingerprint = $this->hasValidBrowserFingerprint($headers);
        
        return [
            'is_bot' => $this->isBot($headers, $ip),
            'bot_type' => $botType,
            'is_whitelisted' => $isWhitelisted,
            'suspicious_pattern' => $hasSuspicious,
            'valid_fingerprint' => $validFingerprint,
            'user_agent' => $userAgent,
            'missing_headers' => $this->getMissingHeaders($headers),
            'confidence' => $this->calculateConfidence($botType, $hasSuspicious, $validFingerprint),
        ];
    }

    /**
     * Get list of missing required headers
     * 
     * @param array $headers Normalized headers
     * @return array Missing header names
     */
    private function getMissingHeaders(array $headers): array
    {
        $missing = [];
        foreach (self::REQUIRED_BROWSER_HEADERS as $required) {
            $key = strtolower($required);
            if (!isset($headers[$key]) || empty($headers[$key])) {
                $missing[] = $required;
            }
        }
        return $missing;
    }

    /**
     * Calculate bot detection confidence (0-100)
     * 
     * @param string|null $botType
     * @param bool $hasSuspicious
     * @param bool $validFingerprint
     * @return int Confidence percentage
     */
    private function calculateConfidence(?string $botType, bool $hasSuspicious, bool $validFingerprint): int
    {
        $confidence = 0;
        
        if ($botType) {
            $confidence += 60; // Known bot = high confidence
        }
        
        if ($hasSuspicious) {
            $confidence += 30; // Suspicious pattern
        }
        
        if (!$validFingerprint) {
            $confidence += 20; // Invalid fingerprint
        }
        
        return min(100, $confidence);
    }

    /**
     * Normalize headers to lowercase keys
     * 
     * @param array $headers
     * @return array
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_change_key_case($headers, CASE_LOWER);
    }

    /**
     * Log bot detection to MongoDB
     * 
     * @param string $detectionType
     * @param string $botType
     * @param string $userAgent
     * @param string $ip
     */
    private function logDetection(string $detectionType, string $botType, string $userAgent, string $ip): void
    {
        if (!$this->config['log_detections']) {
            return;
        }
        
        $this->logger->security('bot_detected', [
            'detection_type' => $detectionType,
            'bot_type' => $botType,
            'user_agent' => $userAgent,
            'ip' => $ip,
            'correlation_id' => TraceContext::getCorrelationId(),
            'timestamp' => time(),
        ]);
    }

    /**
     * Get known bot patterns (for testing/debugging)
     * 
     * @return array
     */
    public function getKnownBots(): array
    {
        return self::KNOWN_BOTS;
    }

    /**
     * Get whitelisted bots (for testing/debugging)
     * 
     * @return array
     */
    public function getWhitelistedBots(): array
    {
        return self::WHITELISTED_BOTS;
    }
}
