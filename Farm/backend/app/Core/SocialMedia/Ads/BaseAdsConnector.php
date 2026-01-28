<?php

namespace PHPFrarm\Core\SocialMedia\Ads;

use PHPFrarm\Core\Logger;

/**
 * Base Ads Platform Connector
 * 
 * Abstract base class for all advertising platform connectors.
 * Provides common functionality for campaign management, ad creation,
 * and analytics across different ad platforms.
 * 
 * @package PHPFrarm\Core\SocialMedia\Ads
 */
abstract class BaseAdsConnector
{
    protected array $config;
    protected string $platformName;
    
    public function __construct()
    {
        $socialConfig = require __DIR__ . '/../../../../config/social.php';
        $this->config = $socialConfig[$this->getConfigKey()] ?? [];
    }
    
    /**
     * Get configuration key for this platform
     */
    abstract protected function getConfigKey(): string;
    
    /**
     * Check if platform is configured
     */
    abstract public function isConfigured(): bool;
    
    /**
     * Get platform name
     */
    public function getPlatformName(): string
    {
        return $this->platformName;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get authorization URL for OAuth
     */
    abstract public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string;
    
    /**
     * Exchange code for access token
     */
    abstract public function getAccessToken(string $code, string $redirectUri): array;
    
    /**
     * Refresh access token
     */
    abstract public function refreshToken(string $refreshToken): array;
    
    /*
    |--------------------------------------------------------------------------
    | Account Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get ad account info
     */
    abstract public function getAdAccount(string $accessToken): array;
    
    /**
     * Get available ad accounts
     */
    abstract public function getAdAccounts(string $accessToken): array;
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get campaigns
     */
    abstract public function getCampaigns(string $accessToken, array $filters = []): array;
    
    /**
     * Create campaign
     */
    abstract public function createCampaign(string $accessToken, array $campaignData): array;
    
    /**
     * Update campaign
     */
    abstract public function updateCampaign(string $accessToken, string $campaignId, array $updates): array;
    
    /**
     * Pause campaign
     */
    abstract public function pauseCampaign(string $accessToken, string $campaignId): bool;
    
    /**
     * Resume campaign
     */
    abstract public function resumeCampaign(string $accessToken, string $campaignId): bool;
    
    /**
     * Delete campaign
     */
    abstract public function deleteCampaign(string $accessToken, string $campaignId): bool;
    
    /*
    |--------------------------------------------------------------------------
    | Ad Set / Ad Group Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get ad sets/groups for a campaign
     */
    abstract public function getAdSets(string $accessToken, string $campaignId): array;
    
    /**
     * Create ad set/group
     */
    abstract public function createAdSet(string $accessToken, string $campaignId, array $adSetData): array;
    
    /**
     * Update ad set
     */
    abstract public function updateAdSet(string $accessToken, string $adSetId, array $updates): array;
    
    /*
    |--------------------------------------------------------------------------
    | Ad Creative Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get ads in an ad set
     */
    abstract public function getAds(string $accessToken, string $adSetId): array;
    
    /**
     * Create ad
     */
    abstract public function createAd(string $accessToken, string $adSetId, array $adData): array;
    
    /**
     * Update ad
     */
    abstract public function updateAd(string $accessToken, string $adId, array $updates): array;
    
    /*
    |--------------------------------------------------------------------------
    | Targeting
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get targeting options
     */
    abstract public function getTargetingOptions(string $accessToken, string $type): array;
    
    /**
     * Search targeting interests/behaviors
     */
    abstract public function searchTargeting(string $accessToken, string $query, string $type = 'interest'): array;
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Reporting
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get campaign insights/analytics
     */
    abstract public function getCampaignInsights(string $accessToken, string $campaignId, array $dateRange = []): array;
    
    /**
     * Get ad set insights
     */
    abstract public function getAdSetInsights(string $accessToken, string $adSetId, array $dateRange = []): array;
    
    /**
     * Get ad insights
     */
    abstract public function getAdInsights(string $accessToken, string $adId, array $dateRange = []): array;
    
    /**
     * Get account-level insights
     */
    abstract public function getAccountInsights(string $accessToken, array $dateRange = []): array;
    
    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * Make HTTP request
     */
    protected function makeRequest(string $method, string $url, array $headers = [], $body = null): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
                }
                break;
            case 'PUT':
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($body) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
                }
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
            Logger::error("Ads API request failed: {$this->platformName}", [
                'error' => $error,
                'url' => $url,
            ]);
            throw new \Exception("Request failed: $error");
        }
        
        $result = json_decode($response, true) ?? [];
        
        if ($httpCode >= 400) {
            Logger::error("Ads API error: {$this->platformName}", [
                'http_code' => $httpCode,
                'response' => $result,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Build URL with query parameters
     */
    protected function buildUrl(string $baseUrl, array $params): string
    {
        if (empty($params)) {
            return $baseUrl;
        }
        
        return $baseUrl . '?' . http_build_query($params);
    }
}
