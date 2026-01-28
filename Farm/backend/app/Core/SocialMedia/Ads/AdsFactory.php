<?php

namespace PHPFrarm\Core\SocialMedia\Ads;

use PHPFrarm\Core\Config;
use PHPFrarm\Core\Logger;

/**
 * Ads Platform Factory
 * 
 * Factory for instantiating advertising platform connectors.
 * Provides unified interface for managing ads across multiple platforms.
 * 
 * @package PHPFrarm\Core\SocialMedia\Ads
 */
class AdsFactory
{
    /**
     * Platform connector instances
     */
    private static array $instances = [];
    
    /**
     * Supported platforms
     */
    private static array $platforms = [
        'meta' => MetaAdsConnector::class,
        'facebook' => MetaAdsConnector::class,
        'instagram' => MetaAdsConnector::class,
        'google' => GoogleAdsConnector::class,
        'google_ads' => GoogleAdsConnector::class,
        'tiktok' => TikTokAdsConnector::class,
        'linkedin' => LinkedInAdsConnector::class,
    ];
    
    /**
     * Get ads platform connector
     */
    public static function getPlatform(string $platform): BaseAdsConnector
    {
        $platform = strtolower($platform);
        
        if (!isset(self::$platforms[$platform])) {
            throw new \InvalidArgumentException("Unsupported ads platform: {$platform}");
        }
        
        if (!isset(self::$instances[$platform])) {
            $class = self::$platforms[$platform];
            self::$instances[$platform] = new $class();
            
            Logger::info('Ads platform connector initialized', [
                'platform' => $platform,
                'class' => $class,
            ]);
        }
        
        return self::$instances[$platform];
    }
    
    /**
     * Get all configured platforms
     */
    public static function getConfiguredPlatforms(): array
    {
        $configured = [];
        
        foreach (array_unique(self::$platforms) as $platform => $class) {
            try {
                $connector = self::getPlatform($platform);
                if ($connector->isConfigured()) {
                    $configured[] = $platform;
                }
            } catch (\Exception $e) {
                // Skip unconfigured
            }
        }
        
        return $configured;
    }
    
    /**
     * Create campaign across multiple platforms
     */
    public static function createCampaign(array $platforms, array $campaignData, array $accessTokens): array
    {
        $results = [];
        
        foreach ($platforms as $platform) {
            try {
                $connector = self::getPlatform($platform);
                $token = $accessTokens[$platform] ?? null;
                
                if (!$token) {
                    $results[$platform] = [
                        'success' => false,
                        'error' => 'Missing access token',
                    ];
                    continue;
                }
                
                $result = $connector->createCampaign($token, $campaignData);
                
                $results[$platform] = [
                    'success' => true,
                    'data' => $result,
                ];
                
                Logger::info('Campaign created on ads platform', [
                    'platform' => $platform,
                    'campaign_name' => $campaignData['name'],
                ]);
                
            } catch (\Exception $e) {
                $results[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                Logger::error('Failed to create campaign', [
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Get aggregated insights across platforms
     */
    public static function getAggregatedInsights(array $platforms, array $campaignMappings, array $accessTokens, array $dateRange = []): array
    {
        $insights = [];
        $totals = [
            'impressions' => 0,
            'clicks' => 0,
            'spend' => 0,
            'conversions' => 0,
        ];
        
        foreach ($platforms as $platform) {
            $campaignId = $campaignMappings[$platform] ?? null;
            $token = $accessTokens[$platform] ?? null;
            
            if (!$campaignId || !$token) {
                continue;
            }
            
            try {
                $connector = self::getPlatform($platform);
                $platformInsights = $connector->getCampaignInsights($token, $campaignId, $dateRange);
                
                $insights[$platform] = $platformInsights;
                
                // Aggregate totals (simplified - actual implementation would parse platform-specific responses)
                // This is a placeholder for aggregation logic
                
            } catch (\Exception $e) {
                Logger::error('Failed to get insights', [
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return [
            'platforms' => $insights,
            'totals' => $totals,
        ];
    }
    
    /**
     * Pause campaigns across platforms
     */
    public static function pauseCampaigns(array $campaignMappings, array $accessTokens): array
    {
        $results = [];
        
        foreach ($campaignMappings as $platform => $campaignId) {
            $token = $accessTokens[$platform] ?? null;
            
            if (!$token) {
                $results[$platform] = false;
                continue;
            }
            
            try {
                $connector = self::getPlatform($platform);
                $results[$platform] = $connector->pauseCampaign($token, $campaignId);
            } catch (\Exception $e) {
                $results[$platform] = false;
                Logger::error('Failed to pause campaign', [
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Resume campaigns across platforms
     */
    public static function resumeCampaigns(array $campaignMappings, array $accessTokens): array
    {
        $results = [];
        
        foreach ($campaignMappings as $platform => $campaignId) {
            $token = $accessTokens[$platform] ?? null;
            
            if (!$token) {
                $results[$platform] = false;
                continue;
            }
            
            try {
                $connector = self::getPlatform($platform);
                $results[$platform] = $connector->resumeCampaign($token, $campaignId);
            } catch (\Exception $e) {
                $results[$platform] = false;
                Logger::error('Failed to resume campaign', [
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Register custom ads platform
     */
    public static function registerPlatform(string $name, string $connectorClass): void
    {
        if (!is_subclass_of($connectorClass, BaseAdsConnector::class)) {
            throw new \InvalidArgumentException(
                "Connector class must extend BaseAdsConnector"
            );
        }
        
        self::$platforms[strtolower($name)] = $connectorClass;
    }
    
    /**
     * Clear cached instances (for testing)
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }
}
