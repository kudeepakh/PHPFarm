<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * Facebook/Meta Connector
 * 
 * Full integration with Facebook Graph API for:
 * - Page management
 * - Post publishing (text, image, video)
 * - Analytics & insights
 * - Comment management
 * - Messenger integration
 * 
 * API Version: v18.0
 * Docs: https://developers.facebook.com/docs/graph-api
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class FacebookConnector extends BasePlatformConnector
{
    protected string $platformName = 'Facebook';
    protected string $platformType = 'social';
    protected array $supportedContentTypes = ['text', 'image', 'video', 'link', 'story'];
    
    private string $graphApiVersion = 'v18.0';
    private string $graphApiUrl = 'https://graph.facebook.com';
    
    protected function getConfigKey(): string
    {
        return 'facebook';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['app_id']) && !empty($this->config['app_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_read_user_content',
            'read_insights',
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
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], [
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        
        // Exchange for long-lived token
        if (!empty($response['access_token'])) {
            return $this->getLongLivedToken($response['access_token']);
        }
        
        return $response;
    }
    
    /**
     * Exchange short-lived token for long-lived token (60 days)
     */
    public function getLongLivedToken(string $shortLivedToken): array
    {
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'fb_exchange_token' => $shortLivedToken,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        // Facebook doesn't use refresh tokens, use getLongLivedToken instead
        return $this->getLongLivedToken($refreshToken);
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
    | Profile & Pages
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/me", [
            'fields' => 'id,name,email,picture,link',
            'access_token' => $accessToken,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Get pages managed by user
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/me/accounts", [
            'fields' => 'id,name,access_token,category,picture,fan_count',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        return $response['data'] ?? [];
    }
    
    /**
     * Get page access token (needed for posting to pages)
     */
    public function getPageAccessToken(string $userToken, string $pageId): ?string
    {
        $pages = $this->getConnectedAccounts($userToken);
        
        foreach ($pages as $page) {
            if ($page['id'] === $pageId) {
                return $page['access_token'];
            }
        }
        
        return null;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        $pageId = $options['page_id'] ?? 'me';
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/feed";
        
        $data = ['message' => $content];
        
        // Add link if provided
        if (!empty($options['link'])) {
            $data['link'] = $options['link'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        Logger::info('Facebook post published', [
            'post_id' => $response['id'] ?? null,
            'page_id' => $pageId,
        ]);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'platform' => 'facebook',
        ];
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array
    {
        $pageId = $options['page_id'] ?? 'me';
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/photos";
        
        $data = [
            'url' => $imageUrl,
            'caption' => $caption,
        ];
        
        // Multiple images
        if (!empty($options['images']) && is_array($options['images'])) {
            return $this->publishMultipleImages($accessToken, $options['images'], $caption, $options);
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'post_id' => $response['post_id'] ?? $response['id'] ?? null,
            'platform' => 'facebook',
        ];
    }
    
    /**
     * Publish multiple images as carousel
     */
    public function publishMultipleImages(string $accessToken, array $imageUrls, string $caption = '', array $options = []): array
    {
        $pageId = $options['page_id'] ?? 'me';
        $photoIds = [];
        
        // Upload each image as unpublished
        foreach ($imageUrls as $imageUrl) {
            $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/photos";
            $response = $this->makeRequest('POST', $url, [
                "Authorization: Bearer $accessToken",
                'Content-Type: application/json',
            ], [
                'url' => $imageUrl,
                'published' => false,
            ]);
            
            if (!empty($response['id'])) {
                $photoIds[] = ['media_fbid' => $response['id']];
            }
        }
        
        // Create post with all images
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/feed";
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'message' => $caption,
            'attached_media' => $photoIds,
        ]);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'platform' => 'facebook',
        ];
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array
    {
        $pageId = $options['page_id'] ?? 'me';
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/videos";
        
        $data = [
            'file_url' => $videoUrl,
            'description' => $caption,
        ];
        
        if (!empty($options['title'])) {
            $data['title'] = $options['title'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'platform' => 'facebook',
        ];
    }
    
    public function schedulePost(string $accessToken, string $content, \DateTime $scheduledTime, array $options = []): array
    {
        $pageId = $options['page_id'] ?? 'me';
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/feed";
        
        $data = [
            'message' => $content,
            'published' => false,
            'scheduled_publish_time' => $scheduledTime->getTimestamp(),
        ];
        
        if (!empty($options['link'])) {
            $data['link'] = $options['link'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'scheduled_time' => $scheduledTime->format('c'),
            'platform' => 'facebook',
        ];
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$postId}";
        
        try {
            $this->makeRequest('DELETE', $url, [
                "Authorization: Bearer $accessToken",
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Insights
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$postId}", [
            'fields' => 'id,message,created_time,shares,reactions.summary(true),comments.summary(true),insights.metric(post_impressions,post_reach,post_engaged_users)',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return [
            'post_id' => $response['id'] ?? $postId,
            'message' => $response['message'] ?? '',
            'created_time' => $response['created_time'] ?? '',
            'shares' => $response['shares']['count'] ?? 0,
            'reactions' => $response['reactions']['summary']['total_count'] ?? 0,
            'comments' => $response['comments']['summary']['total_count'] ?? 0,
            'insights' => $response['insights']['data'] ?? [],
        ];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        $pageId = $this->config['page_id'] ?? 'me';
        
        $defaultMetrics = [
            'page_impressions',
            'page_engaged_users',
            'page_post_engagements',
            'page_fan_adds',
            'page_views_total',
        ];
        
        $metrics = !empty($metrics) ? $metrics : $defaultMetrics;
        
        $params = [
            'metric' => implode(',', $metrics),
            'access_token' => $accessToken,
        ];
        
        if (!empty($dateRange['since'])) {
            $params['since'] = $dateRange['since'];
        }
        if (!empty($dateRange['until'])) {
            $params['until'] = $dateRange['until'];
        }
        
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/insights", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function getAudienceInsights(string $accessToken): array
    {
        $pageId = $this->config['page_id'] ?? 'me';
        
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/insights", [
            'metric' => 'page_fans_city,page_fans_country,page_fans_gender_age,page_fans_locale',
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
            'fields' => 'id,message,from,created_time,like_count,comment_count',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$commentId}/comments";
        
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
    
    public function getMessages(string $accessToken): array
    {
        // Requires pages_messaging permission
        $pageId = $this->config['page_id'] ?? 'me';
        
        $url = $this->buildUrl("{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/conversations", [
            'fields' => 'id,participants,messages{message,from,created_time}',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function sendMessage(string $accessToken, string $recipientId, string $message): array
    {
        $pageId = $this->config['page_id'] ?? 'me';
        
        $url = "{$this->graphApiUrl}/{$this->graphApiVersion}/{$pageId}/messages";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
        ]);
        
        return [
            'success' => true,
            'message_id' => $response['message_id'] ?? null,
        ];
    }
}
