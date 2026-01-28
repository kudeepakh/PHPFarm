<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * Instagram Connector
 * 
 * Instagram Graph API for Business/Creator accounts.
 * 
 * Features:
 * - Feed posts (image, video, carousel)
 * - Stories
 * - Reels
 * - Insights & analytics
 * - Comment management
 * 
 * Note: Instagram API requires a Facebook Page linked to Instagram Business/Creator account.
 * 
 * API Docs: https://developers.facebook.com/docs/instagram-api
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class InstagramConnector extends BasePlatformConnector
{
    protected string $platformName = 'Instagram';
    protected string $platformType = 'social';
    protected array $supportedContentTypes = ['image', 'video', 'carousel', 'story', 'reel'];
    
    private string $graphApiVersion = 'v18.0';
    private string $graphApiUrl = 'https://graph.facebook.com';
    
    protected function getConfigKey(): string
    {
        return 'instagram';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['app_id']) && !empty($this->config['app_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (via Facebook)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'instagram_basic',
            'instagram_content_publish',
            'instagram_manage_comments',
            'instagram_manage_insights',
            'pages_show_list',
            'pages_read_engagement',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['app_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'state' => $this->generateState(),
        ];
        
        return $this->buildUrl('https://www.facebook.com/' . $this->graphApiVersion . '/dialog/oauth', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/oauth/access_token";
        
        $params = http_build_query([
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $params);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        // Use Facebook's long-lived token exchange
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'fb_exchange_token' => $refreshToken,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    public function revokeToken(string $token): bool
    {
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/me/permissions";
        
        try {
            $this->makeRequest('DELETE', $url, [
                "Authorization: Bearer $token",
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Profile & Account
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        // First get Facebook pages
        $pagesUrl = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/me/accounts", [
            'access_token' => $accessToken,
        ]);
        
        $pages = $this->makeRequest('GET', $pagesUrl);
        
        // Get Instagram Business Account for each page
        $instagramAccounts = [];
        
        foreach ($pages['data'] ?? [] as $page) {
            $igUrl = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$page['id']}", [
                'fields' => 'instagram_business_account{id,username,name,profile_picture_url,followers_count,media_count}',
                'access_token' => $accessToken,
            ]);
            
            $response = $this->makeRequest('GET', $igUrl);
            
            if (!empty($response['instagram_business_account'])) {
                $instagramAccounts[] = array_merge(
                    $response['instagram_business_account'],
                    ['page_id' => $page['id'], 'page_access_token' => $page['access_token']]
                );
            }
        }
        
        return $instagramAccounts[0] ?? [];
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Get all Instagram Business Accounts
        $pagesUrl = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/me/accounts", [
            'fields' => 'id,name,instagram_business_account{id,username,name,profile_picture_url,followers_count}',
            'access_token' => $accessToken,
        ]);
        
        $pages = $this->makeRequest('GET', $pagesUrl);
        
        $accounts = [];
        foreach ($pages['data'] ?? [] as $page) {
            if (!empty($page['instagram_business_account'])) {
                $accounts[] = $page['instagram_business_account'];
            }
        }
        
        return $accounts;
    }
    
    /**
     * Get Instagram Business Account ID
     */
    private function getInstagramAccountId(string $accessToken): ?string
    {
        if (!empty($this->config['instagram_account_id'])) {
            return $this->config['instagram_account_id'];
        }
        
        $profile = $this->getProfile($accessToken);
        return $profile['id'] ?? null;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        // Instagram requires media, can't post text-only
        if (empty($options['image_url']) && empty($options['video_url'])) {
            return [
                'success' => false,
                'error' => 'Instagram requires image or video. Text-only posts not supported.',
            ];
        }
        
        if (!empty($options['video_url'])) {
            return $this->publishVideo($accessToken, $options['video_url'], $content, $options);
        }
        
        return $this->publishImage($accessToken, $options['image_url'], $content, $options);
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array
    {
        $igAccountId = $options['instagram_account_id'] ?? $this->getInstagramAccountId($accessToken);
        
        if (!$igAccountId) {
            return ['success' => false, 'error' => 'Instagram account ID not found'];
        }
        
        // Step 1: Create media container
        $containerUrl = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/media";
        
        $containerData = [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ];
        
        // Add location if provided
        if (!empty($options['location_id'])) {
            $containerData['location_id'] = $options['location_id'];
        }
        
        // Add user tags
        if (!empty($options['user_tags'])) {
            $containerData['user_tags'] = json_encode($options['user_tags']);
        }
        
        $container = $this->makeRequest('POST', $containerUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $containerData);
        
        if (empty($container['id'])) {
            return ['success' => false, 'error' => 'Failed to create media container'];
        }
        
        // Step 2: Publish the container
        return $this->publishContainer($accessToken, $igAccountId, $container['id']);
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array
    {
        $igAccountId = $options['instagram_account_id'] ?? $this->getInstagramAccountId($accessToken);
        
        if (!$igAccountId) {
            return ['success' => false, 'error' => 'Instagram account ID not found'];
        }
        
        // Determine if Reel or Feed video
        $mediaType = $options['is_reel'] ?? false ? 'REELS' : 'VIDEO';
        
        // Step 1: Create media container
        $containerUrl = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/media";
        
        $containerData = [
            'video_url' => $videoUrl,
            'caption' => $caption,
            'media_type' => $mediaType,
        ];
        
        // For Reels
        if ($mediaType === 'REELS') {
            if (!empty($options['cover_url'])) {
                $containerData['cover_url'] = $options['cover_url'];
            }
            if (!empty($options['share_to_feed'])) {
                $containerData['share_to_feed'] = $options['share_to_feed'];
            }
        }
        
        $container = $this->makeRequest('POST', $containerUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $containerData);
        
        if (empty($container['id'])) {
            return ['success' => false, 'error' => 'Failed to create video container'];
        }
        
        // Step 2: Wait for video processing (poll status)
        $containerId = $container['id'];
        $maxAttempts = 30;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $statusUrl = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ]);
            
            $status = $this->makeRequest('GET', $statusUrl);
            
            if ($status['status_code'] === 'FINISHED') {
                break;
            } elseif ($status['status_code'] === 'ERROR') {
                return ['success' => false, 'error' => 'Video processing failed'];
            }
            
            sleep(2);
            $attempt++;
        }
        
        // Step 3: Publish
        return $this->publishContainer($accessToken, $igAccountId, $containerId);
    }
    
    /**
     * Publish carousel (multiple images/videos)
     */
    public function publishCarousel(string $accessToken, array $mediaUrls, string $caption = '', array $options = []): array
    {
        $igAccountId = $options['instagram_account_id'] ?? $this->getInstagramAccountId($accessToken);
        
        if (!$igAccountId) {
            return ['success' => false, 'error' => 'Instagram account ID not found'];
        }
        
        // Step 1: Create container for each media item
        $childContainerIds = [];
        
        foreach ($mediaUrls as $media) {
            $containerUrl = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/media";
            
            $containerData = [
                'is_carousel_item' => true,
            ];
            
            if ($media['type'] === 'video') {
                $containerData['video_url'] = $media['url'];
                $containerData['media_type'] = 'VIDEO';
            } else {
                $containerData['image_url'] = $media['url'];
            }
            
            $container = $this->makeRequest('POST', $containerUrl, [
                "Authorization: Bearer $accessToken",
                'Content-Type: application/json',
            ], $containerData);
            
            if (!empty($container['id'])) {
                $childContainerIds[] = $container['id'];
            }
        }
        
        // Step 2: Create carousel container
        $carouselUrl = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/media";
        
        $carousel = $this->makeRequest('POST', $carouselUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'media_type' => 'CAROUSEL',
            'caption' => $caption,
            'children' => implode(',', $childContainerIds),
        ]);
        
        if (empty($carousel['id'])) {
            return ['success' => false, 'error' => 'Failed to create carousel'];
        }
        
        // Step 3: Publish
        return $this->publishContainer($accessToken, $igAccountId, $carousel['id']);
    }
    
    /**
     * Publish media container
     */
    private function publishContainer(string $accessToken, string $igAccountId, string $containerId): array
    {
        $publishUrl = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/media_publish";
        
        $response = $this->makeRequest('POST', $publishUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'creation_id' => $containerId,
        ]);
        
        Logger::info('Instagram post published', [
            'media_id' => $response['id'] ?? null,
        ]);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'platform' => 'instagram',
        ];
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        // Instagram doesn't allow deletion via API (as of v18.0)
        Logger::warning('Instagram post deletion not supported via API');
        return false;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Insights
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$postId}/insights", [
            'metric' => 'impressions,reach,engagement,saved,video_views',
            'access_token' => $accessToken,
        ]);
        
        $insights = $this->makeRequest('GET', $url);
        
        // Also get basic metrics
        $mediaUrl = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$postId}", [
            'fields' => 'id,caption,media_type,timestamp,like_count,comments_count',
            'access_token' => $accessToken,
        ]);
        
        $media = $this->makeRequest('GET', $mediaUrl);
        
        return [
            'post_id' => $postId,
            'caption' => $media['caption'] ?? '',
            'media_type' => $media['media_type'] ?? '',
            'timestamp' => $media['timestamp'] ?? '',
            'likes' => $media['like_count'] ?? 0,
            'comments' => $media['comments_count'] ?? 0,
            'insights' => $insights['data'] ?? [],
        ];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        $igAccountId = $this->getInstagramAccountId($accessToken);
        
        if (!$igAccountId) {
            return [];
        }
        
        $defaultMetrics = [
            'impressions',
            'reach',
            'profile_views',
            'follower_count',
            'website_clicks',
        ];
        
        $metrics = !empty($metrics) ? $metrics : $defaultMetrics;
        
        $params = [
            'metric' => implode(',', $metrics),
            'period' => $dateRange['period'] ?? 'day',
            'access_token' => $accessToken,
        ];
        
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/insights", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function getAudienceInsights(string $accessToken): array
    {
        $igAccountId = $this->getInstagramAccountId($accessToken);
        
        if (!$igAccountId) {
            return [];
        }
        
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$igAccountId}/insights", [
            'metric' => 'audience_city,audience_country,audience_gender_age',
            'period' => 'lifetime',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Engagement
    |--------------------------------------------------------------------------
    */
    
    public function getComments(string $accessToken, string $postId): array
    {
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$postId}/comments", [
            'fields' => 'id,text,from,timestamp,like_count,replies{id,text,from,timestamp}',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$commentId}/replies";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'message' => $message,
        ]);
        
        return [
            'success' => true,
            'comment_id' => $response['id'] ?? null,
        ];
    }
}
