<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\SocialMedia\SocialPlatformInterface;
use PHPFrarm\Core\Logger;

/**
 * Base Social Platform Connector
 * 
 * Abstract base class with common functionality for all platform connectors.
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
abstract class BasePlatformConnector implements SocialPlatformInterface
{
    protected array $config;
    protected string $platformName;
    protected string $platformType;
    protected array $supportedContentTypes = ['text'];
    
    // Rate limiting
    protected int $rateLimitRemaining = 0;
    protected int $rateLimitReset = 0;
    
    public function __construct()
    {
        $allConfig = require __DIR__ . '/../../../../../config/social.php';
        $this->config = $allConfig[$this->getConfigKey()] ?? [];
    }
    
    /**
     * Get config key for this platform
     */
    abstract protected function getConfigKey(): string;
    
    /**
     * Make HTTP request with error handling and rate limit tracking
     */
    protected function makeRequest(string $method, string $url, array $headers = [], array $data = []): array
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
            $this->parseRateLimitHeaders($header);
            return strlen($header);
        });
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("cURL error: $error");
        }
        
        $result = json_decode($response, true) ?? [];
        
        // Log API call
        Logger::debug("Social API call: {$this->platformName}", [
            'method' => $method,
            'url' => $url,
            'http_code' => $httpCode,
            'rate_limit_remaining' => $this->rateLimitRemaining,
        ]);
        
        if ($httpCode >= 400) {
            $errorMessage = $result['error']['message'] ?? $result['error'] ?? $result['message'] ?? 'API error';
            throw new \Exception($errorMessage, $httpCode);
        }
        
        return $result;
    }
    
    /**
     * Parse rate limit headers
     */
    protected function parseRateLimitHeaders(string $header): void
    {
        if (preg_match('/^X-RateLimit-Remaining:\s*(\d+)/i', $header, $matches)) {
            $this->rateLimitRemaining = (int) $matches[1];
        }
        if (preg_match('/^X-RateLimit-Reset:\s*(\d+)/i', $header, $matches)) {
            $this->rateLimitReset = (int) $matches[1];
        }
    }
    
    /**
     * Generate state parameter for OAuth
     */
    protected function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Build URL with query parameters
     */
    protected function buildUrl(string $baseUrl, array $params): string
    {
        return $baseUrl . '?' . http_build_query($params);
    }
    
    // Default implementations
    
    public function getPlatformName(): string
    {
        return $this->platformName;
    }
    
    public function getPlatformType(): string
    {
        return $this->platformType;
    }
    
    public function getSupportedContentTypes(): array
    {
        return $this->supportedContentTypes;
    }
    
    public function getRateLimits(): array
    {
        return [
            'remaining' => $this->rateLimitRemaining,
            'reset' => $this->rateLimitReset,
        ];
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Default: return empty array (override in platform-specific connectors)
        return [];
    }
    
    public function schedulePost(string $accessToken, string $content, \DateTime $scheduledTime, array $options = []): array
    {
        // Default: not supported
        return [
            'success' => false,
            'error' => 'Scheduling not supported for this platform',
        ];
    }
    
    public function getAudienceInsights(string $accessToken): array
    {
        return [];
    }
    
    public function getMessages(string $accessToken): array
    {
        return [];
    }
    
    public function sendMessage(string $accessToken, string $recipientId, string $message): array
    {
        return [
            'success' => false,
            'error' => 'Direct messaging not supported for this platform',
        ];
    }
}
