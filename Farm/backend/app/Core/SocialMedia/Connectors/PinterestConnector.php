<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * Pinterest Connector
 * 
 * Pinterest API integration for pins, boards, and analytics.
 * 
 * Features:
 * - Pin creation (image, video, idea pins)
 * - Board management
 * - Analytics and insights
 * - Shopping catalogs (optional)
 * 
 * API Docs: https://developers.pinterest.com/docs/api/v5/
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class PinterestConnector extends BasePlatformConnector
{
    protected string $platformName = 'Pinterest';
    protected string $platformType = 'social';
    protected array $supportedContentTypes = ['image', 'video', 'link', 'product'];
    
    private string $apiUrl = 'https://api.pinterest.com/v5';
    
    protected function getConfigKey(): string
    {
        return 'pinterest';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['app_id']) && !empty($this->config['app_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (OAuth 2.0)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'boards:read',
            'boards:write',
            'pins:read',
            'pins:write',
            'user_accounts:read',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['app_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $this->generateState(),
        ];
        
        return $this->buildUrl('https://www.pinterest.com/oauth/', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = "{$this->apiUrl}/oauth/token";
        
        $credentials = base64_encode($this->config['app_id'] . ':' . $this->config['app_secret']);
        
        $data = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
        
        return $this->makeRequest('POST', $url, [
            "Authorization: Basic $credentials",
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = "{$this->apiUrl}/oauth/token";
        
        $credentials = base64_encode($this->config['app_id'] . ':' . $this->config['app_secret']);
        
        $data = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
        
        return $this->makeRequest('POST', $url, [
            "Authorization: Basic $credentials",
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function revokeToken(string $token): bool
    {
        // Pinterest doesn't have token revocation endpoint
        return true;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        $url = "{$this->apiUrl}/user_account";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Get boards
        return $this->getBoards($accessToken);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Board Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get user's boards
     */
    public function getBoards(string $accessToken, int $pageSize = 25): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/boards", [
            'page_size' => min($pageSize, 100),
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response['items'] ?? [];
    }
    
    /**
     * Create a board
     */
    public function createBoard(string $accessToken, string $name, string $description = '', array $options = []): array
    {
        $url = "{$this->apiUrl}/boards";
        
        $data = [
            'name' => $name,
            'description' => $description,
            'privacy' => $options['privacy'] ?? 'PUBLIC', // PUBLIC, PROTECTED, SECRET
        ];
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'board_id' => $response['id'] ?? null,
        ];
    }
    
    /**
     * Get board sections
     */
    public function getBoardSections(string $accessToken, string $boardId): array
    {
        $url = "{$this->apiUrl}/boards/{$boardId}/sections";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response['items'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Pin Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        if (!empty($options['image_url'])) {
            return $this->publishImage($accessToken, $options['image_url'], $content, $options);
        }
        
        if (!empty($options['video_url'])) {
            return $this->publishVideo($accessToken, $options['video_url'], $content, $options);
        }
        
        return ['success' => false, 'error' => 'Pinterest requires image or video'];
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $description = '', array $options = []): array
    {
        $url = "{$this->apiUrl}/pins";
        
        $boardId = $options['board_id'] ?? $this->config['default_board_id'] ?? null;
        
        if (!$boardId) {
            return ['success' => false, 'error' => 'Board ID required'];
        }
        
        $data = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $imageUrl,
            ],
        ];
        
        if (!empty($description)) {
            $data['description'] = $description;
        }
        
        if (!empty($options['title'])) {
            $data['title'] = $options['title'];
        }
        
        if (!empty($options['link'])) {
            $data['link'] = $options['link'];
        }
        
        if (!empty($options['alt_text'])) {
            $data['alt_text'] = $options['alt_text'];
        }
        
        // Board section
        if (!empty($options['section_id'])) {
            $data['board_section_id'] = $options['section_id'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        Logger::info('Pinterest pin created', [
            'pin_id' => $response['id'] ?? null,
        ]);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'platform' => 'pinterest',
        ];
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $description = '', array $options = []): array
    {
        $url = "{$this->apiUrl}/pins";
        
        $boardId = $options['board_id'] ?? $this->config['default_board_id'] ?? null;
        
        if (!$boardId) {
            return ['success' => false, 'error' => 'Board ID required'];
        }
        
        // Video pins require upload first
        // Step 1: Register media
        $registerUrl = "{$this->apiUrl}/media";
        
        $registerResponse = $this->makeRequest('POST', $registerUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'media_type' => 'video',
        ]);
        
        $mediaId = $registerResponse['media_id'] ?? null;
        $uploadUrl = $registerResponse['upload_url'] ?? null;
        
        if (!$mediaId || !$uploadUrl) {
            return ['success' => false, 'error' => 'Failed to register media'];
        }
        
        // Step 2: Upload video
        $videoData = file_get_contents($videoUrl);
        
        $this->makeRequest('POST', $uploadUrl, [
            'Content-Type: video/mp4',
        ], $videoData);
        
        // Step 3: Wait for processing and create pin
        sleep(5); // Wait for processing
        
        $data = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'video_id',
                'media_id' => $mediaId,
            ],
        ];
        
        if (!empty($description)) {
            $data['description'] = $description;
        }
        
        if (!empty($options['title'])) {
            $data['title'] = $options['title'];
        }
        
        if (!empty($options['link'])) {
            $data['link'] = $options['link'];
        }
        
        if (!empty($options['cover_image_url'])) {
            $data['media_source']['cover_image_url'] = $options['cover_image_url'];
        }
        
        $response = $this->makeRequest('POST', "{$this->apiUrl}/pins", [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'platform' => 'pinterest',
        ];
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        $url = "{$this->apiUrl}/pins/{$postId}";
        
        try {
            $this->makeRequest('DELETE', $url, [
                "Authorization: Bearer $accessToken",
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get pin details
     */
    public function getPin(string $accessToken, string $pinId): array
    {
        $url = "{$this->apiUrl}/pins/{$pinId}";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    /**
     * Get user's pins
     */
    public function getPins(string $accessToken, int $pageSize = 25): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/pins", [
            'page_size' => min($pageSize, 100),
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response['items'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/pins/{$postId}/analytics", [
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'end_date' => date('Y-m-d'),
            'metric_types' => 'IMPRESSION,SAVE,PIN_CLICK,OUTBOUND_CLICK',
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $url = $this->buildUrl("{$this->apiUrl}/user_account/analytics", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'metric_types' => 'IMPRESSION,ENGAGEMENT,PIN_CLICK,OUTBOUND_CLICK,SAVE',
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    public function getAudienceInsights(string $accessToken): array
    {
        $url = "{$this->apiUrl}/user_account/analytics/audience";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    public function getComments(string $accessToken, string $postId): array
    {
        // Pinterest doesn't expose comments via API
        return [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        // Not supported
        return ['success' => false, 'error' => 'Pinterest comments not accessible via API'];
    }
}
