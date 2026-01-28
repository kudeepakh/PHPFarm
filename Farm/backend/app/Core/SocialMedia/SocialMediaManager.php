<?php

namespace PHPFrarm\Core\SocialMedia;

use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Config;
use PHPFrarm\Core\SocialMedia\Ads\AdsFactory;
use PHPFrarm\Core\SocialMedia\Webhooks\WebhookHandler;

/**
 * Social Media Manager
 * 
 * High-level manager for social media operations across platforms.
 * Provides unified interface for content publishing, scheduling,
 * analytics aggregation, and cross-platform management.
 * 
 * Features:
 * - Multi-platform publishing
 * - Content scheduling
 * - Analytics aggregation
 * - Rate limit management
 * - Platform health monitoring
 * 
 * @package PHPFrarm\Core\SocialMedia
 */
class SocialMediaManager
{
    private array $tokens = [];
    private ?WebhookHandler $webhookHandler = null;
    
    /**
     * Set access tokens for platforms
     */
    public function setTokens(array $tokens): self
    {
        $this->tokens = $tokens;
        return $this;
    }
    
    /**
     * Set token for specific platform
     */
    public function setToken(string $platform, string $token): self
    {
        $this->tokens[strtolower($platform)] = $token;
        return $this;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    /**
     * Publish content to multiple platforms
     */
    public function publish(array $platforms, string $content, array $options = []): array
    {
        return SocialPlatformFactory::publishToMultiple($platforms, $this->tokens, $content, $options);
    }
    
    /**
     * Publish text post to a single platform
     */
    public function publishText(string $platform, string $content, array $options = []): array
    {
        $connector = SocialPlatformFactory::getPlatform($platform);
        $token = $this->getToken($platform);
        
        return $connector->publishPost($token, $content, $options);
    }
    
    /**
     * Publish image to a single platform
     */
    public function publishImage(string $platform, string $imageUrl, string $caption = '', array $options = []): array
    {
        $connector = SocialPlatformFactory::getPlatform($platform);
        $token = $this->getToken($platform);
        
        return $connector->publishImage($token, $imageUrl, $caption, $options);
    }
    
    /**
     * Publish video to a single platform
     */
    public function publishVideo(string $platform, string $videoUrl, string $caption = '', array $options = []): array
    {
        $connector = SocialPlatformFactory::getPlatform($platform);
        $token = $this->getToken($platform);
        
        return $connector->publishVideo($token, $videoUrl, $caption, $options);
    }
    
    /**
     * Cross-post content across platforms
     */
    public function crossPost(string $content, array $platformOptions = []): array
    {
        $results = [];
        
        foreach ($platformOptions as $platform => $options) {
            try {
                $connector = SocialPlatformFactory::getPlatform($platform);
                $token = $this->getToken($platform);
                
                // Platform-specific content adaptation
                $adaptedContent = $this->adaptContentForPlatform($platform, $content, $options);
                
                if (!empty($options['video_url'])) {
                    $results[$platform] = $connector->publishVideo($token, $options['video_url'], $adaptedContent, $options);
                } elseif (!empty($options['image_url'])) {
                    $results[$platform] = $connector->publishImage($token, $options['image_url'], $adaptedContent, $options);
                } else {
                    $results[$platform] = $connector->publishPost($token, $adaptedContent, $options);
                }
                
            } catch (\Exception $e) {
                $results[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Adapt content for specific platform constraints
     */
    private function adaptContentForPlatform(string $platform, string $content, array $options = []): string
    {
        $limits = [
            'twitter' => 280,
            'linkedin' => 3000,
            'facebook' => 63206,
            'instagram' => 2200,
            'tiktok' => 2200,
            'pinterest' => 500,
            'youtube' => 5000,
        ];
        
        $limit = $limits[$platform] ?? null;
        
        if ($limit && strlen($content) > $limit) {
            $content = substr($content, 0, $limit - 3) . '...';
        }
        
        // Add platform-specific hashtags
        if (!empty($options['hashtags']) && in_array($platform, ['twitter', 'instagram', 'tiktok'])) {
            $hashtags = array_map(fn($tag) => "#" . ltrim($tag, '#'), $options['hashtags']);
            $hashtagString = implode(' ', $hashtags);
            
            if (strlen($content) + strlen($hashtagString) + 1 <= ($limit ?? PHP_INT_MAX)) {
                $content .= "\n" . $hashtagString;
            }
        }
        
        return $content;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get aggregated analytics across platforms
     */
    public function getAggregatedAnalytics(array $platforms = [], array $dateRange = []): array
    {
        if (empty($platforms)) {
            $platforms = array_keys($this->tokens);
        }
        
        $analytics = SocialPlatformFactory::getMultiPlatformAnalytics($platforms, $this->tokens, $dateRange);
        
        // Aggregate metrics
        $totals = [
            'followers' => 0,
            'engagement' => 0,
            'impressions' => 0,
            'reach' => 0,
        ];
        
        foreach ($analytics as $platform => $data) {
            // Normalize and aggregate (platform-specific parsing needed)
            $totals['followers'] += $data['followers'] ?? $data['subscriber_count'] ?? 0;
            $totals['engagement'] += $data['engagement'] ?? $data['likes'] ?? 0;
            $totals['impressions'] += $data['impressions'] ?? 0;
            $totals['reach'] += $data['reach'] ?? 0;
        }
        
        return [
            'platforms' => $analytics,
            'totals' => $totals,
            'date_range' => $dateRange,
            'generated_at' => date('c'),
        ];
    }
    
    /**
     * Get post insights across platforms
     */
    public function getPostInsights(array $postIds): array
    {
        $insights = [];
        
        foreach ($postIds as $platform => $postId) {
            try {
                $connector = SocialPlatformFactory::getPlatform($platform);
                $token = $this->getToken($platform);
                
                $insights[$platform] = $connector->getPostAnalytics($token, $postId);
                
            } catch (\Exception $e) {
                Logger::warning("Failed to get insights from $platform", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $insights;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Account Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get account info from multiple platforms
     */
    public function getAccountsInfo(array $platforms = []): array
    {
        if (empty($platforms)) {
            $platforms = array_keys($this->tokens);
        }
        
        $accounts = [];
        
        foreach ($platforms as $platform) {
            try {
                $connector = SocialPlatformFactory::getPlatform($platform);
                $token = $this->getToken($platform);
                
                $accounts[$platform] = $connector->getAccountInfo($token);
                
            } catch (\Exception $e) {
                $accounts[$platform] = [
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $accounts;
    }
    
    /**
     * Verify all connected accounts
     */
    public function verifyConnections(): array
    {
        $status = [];
        
        foreach ($this->tokens as $platform => $token) {
            try {
                $connector = SocialPlatformFactory::getPlatform($platform);
                $accountInfo = $connector->getAccountInfo($token);
                
                $status[$platform] = [
                    'connected' => true,
                    'account' => $accountInfo['name'] ?? $accountInfo['username'] ?? 'Unknown',
                ];
                
            } catch (\Exception $e) {
                $status[$platform] = [
                    'connected' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $status;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Scheduling (Placeholder for queue integration)
    |--------------------------------------------------------------------------
    */
    
    /**
     * Schedule a post for later
     */
    public function schedulePost(array $platforms, string $content, \DateTime $scheduledTime, array $options = []): array
    {
        // This would integrate with a job queue system
        // For now, return schedule info
        
        $scheduleId = uniqid('schedule_');
        
        $schedule = [
            'id' => $scheduleId,
            'platforms' => $platforms,
            'content' => $content,
            'options' => $options,
            'scheduled_for' => $scheduledTime->format('c'),
            'status' => 'scheduled',
            'created_at' => date('c'),
        ];
        
        // NOTE: Database persistence and job scheduling required for production
        // Implementation steps:
        // 1. Create 'scheduled_posts' table (see database/mysql/tables/scheduled_posts.sql)
        // 2. Store schedule using CALL sp_create_scheduled_post(...)
        // 3. Create queue job: ProcessScheduledPostJob
        // 4. Use cron or Laravel Scheduler for execution
        Logger::info('Post scheduled', [
            'schedule_id' => $scheduleId,
            'platforms' => $platforms,
            'scheduled_for' => $scheduledTime->format('c'),
        ]);
        
        return $schedule;
    }
    
    /**
     * Cancel a scheduled post
     */
    public function cancelScheduledPost(string $scheduleId): bool
    {
        // NOTE: Database deletion required for production
        // Implementation: CALL sp_delete_scheduled_post($scheduleId)
        // Also cancel associated queue job if exists
        Logger::info('Scheduled post cancelled', ['schedule_id' => $scheduleId]);
        return true;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ads Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get ads platform connector
     */
    public function ads(string $platform): \PHPFrarm\Core\SocialMedia\Ads\BaseAdsConnector
    {
        return AdsFactory::getPlatform($platform);
    }
    
    /**
     * Create ad campaign across platforms
     */
    public function createAdCampaign(array $platforms, array $campaignData): array
    {
        return AdsFactory::createCampaign($platforms, $campaignData, $this->tokens);
    }
    
    /**
     * Get aggregated ad insights
     */
    public function getAdInsights(array $campaignMappings, array $dateRange = []): array
    {
        return AdsFactory::getAggregatedInsights(
            array_keys($campaignMappings),
            $campaignMappings,
            $this->tokens,
            $dateRange
        );
    }
    
    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get webhook handler
     */
    public function webhooks(): WebhookHandler
    {
        if ($this->webhookHandler === null) {
            $this->webhookHandler = new WebhookHandler();
        }
        
        return $this->webhookHandler;
    }
    
    /**
     * Handle incoming webhook
     */
    public function handleWebhook(string $platform, array $headers, string $body): array
    {
        return $this->webhooks()->handle($platform, $headers, $body);
    }
    
    /**
     * Register webhook event handler
     */
    public function onWebhookEvent(string $platform, string $eventType, callable $handler): self
    {
        $this->webhooks()->on($platform, $eventType, $handler);
        return $this;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Utility Methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get access token for platform
     */
    private function getToken(string $platform): string
    {
        $platform = strtolower($platform);
        
        if (!isset($this->tokens[$platform])) {
            throw new \RuntimeException("No access token set for platform: {$platform}");
        }
        
        return $this->tokens[$platform];
    }
    
    /**
     * Check rate limit status for platforms
     */
    public function getRateLimitStatus(array $platforms = []): array
    {
        if (empty($platforms)) {
            $platforms = array_keys($this->tokens);
        }
        
        $status = [];
        
        foreach ($platforms as $platform) {
            try {
                $connector = SocialPlatformFactory::getPlatform($platform);
                $status[$platform] = $connector->getRateLimitStatus();
            } catch (\Exception $e) {
                $status[$platform] = [
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Get list of supported platforms
     */
    public function getSupportedPlatforms(): array
    {
        return SocialPlatformFactory::getAvailablePlatforms();
    }
    
    /**
     * Get configured platforms
     */
    public function getConfiguredPlatforms(): array
    {
        return SocialPlatformFactory::getConfiguredPlatforms();
    }
    
    /**
     * Create a new manager instance with tokens
     */
    public static function withTokens(array $tokens): self
    {
        $manager = new self();
        $manager->setTokens($tokens);
        return $manager;
    }
}
