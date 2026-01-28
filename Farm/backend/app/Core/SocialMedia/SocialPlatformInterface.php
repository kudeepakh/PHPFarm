<?php

namespace PHPFrarm\Core\SocialMedia;

/**
 * Social Platform Connector Interface
 * 
 * Base interface for all social media platform connectors.
 * Provides standard methods for authentication, content publishing,
 * analytics, and engagement tracking.
 * 
 * @package PHPFrarm\Core\SocialMedia
 */
interface SocialPlatformInterface
{
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string;
    
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code, string $redirectUri): array;
    
    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array;
    
    /**
     * Revoke access token
     */
    public function revokeToken(string $token): bool;
    
    /**
     * Check if platform is configured
     */
    public function isConfigured(): bool;
    
    /*
    |--------------------------------------------------------------------------
    | Profile & Account
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get user/page profile information
     */
    public function getProfile(string $accessToken): array;
    
    /**
     * Get connected accounts/pages (for business accounts)
     */
    public function getConnectedAccounts(string $accessToken): array;
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    /**
     * Publish text content
     */
    public function publishPost(string $accessToken, string $content, array $options = []): array;
    
    /**
     * Publish image with caption
     */
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array;
    
    /**
     * Publish video
     */
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array;
    
    /**
     * Schedule post for later
     */
    public function schedulePost(string $accessToken, string $content, \DateTime $scheduledTime, array $options = []): array;
    
    /**
     * Delete a post
     */
    public function deletePost(string $accessToken, string $postId): bool;
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Insights
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get post analytics (likes, shares, comments, reach)
     */
    public function getPostAnalytics(string $accessToken, string $postId): array;
    
    /**
     * Get account/page analytics
     */
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array;
    
    /**
     * Get audience demographics
     */
    public function getAudienceInsights(string $accessToken): array;
    
    /*
    |--------------------------------------------------------------------------
    | Engagement
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get comments on a post
     */
    public function getComments(string $accessToken, string $postId): array;
    
    /**
     * Reply to a comment
     */
    public function replyToComment(string $accessToken, string $commentId, string $message): array;
    
    /**
     * Get direct messages (if supported)
     */
    public function getMessages(string $accessToken): array;
    
    /**
     * Send direct message (if supported)
     */
    public function sendMessage(string $accessToken, string $recipientId, string $message): array;
    
    /*
    |--------------------------------------------------------------------------
    | Platform Info
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get platform name
     */
    public function getPlatformName(): string;
    
    /**
     * Get platform type (social, video, photo, messaging)
     */
    public function getPlatformType(): string;
    
    /**
     * Get supported content types
     */
    public function getSupportedContentTypes(): array;
    
    /**
     * Get rate limits
     */
    public function getRateLimits(): array;
}
