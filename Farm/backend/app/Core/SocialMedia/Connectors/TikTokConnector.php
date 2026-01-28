<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * TikTok Connector
 * 
 * TikTok for Business API integration.
 * 
 * Features:
 * - Video upload and publishing
 * - Analytics and insights
 * - Sound and music integration
 * - Duet and stitch settings
 * 
 * API Docs: https://developers.tiktok.com/doc/login-kit-web
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class TikTokConnector extends BasePlatformConnector
{
    protected string $platformName = 'TikTok';
    protected string $platformType = 'video';
    protected array $supportedContentTypes = ['video'];
    
    private string $apiUrl = 'https://open.tiktokapis.com/v2';
    private string $authUrl = 'https://www.tiktok.com/v2/auth/authorize';
    
    protected function getConfigKey(): string
    {
        return 'tiktok';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['client_key']) && !empty($this->config['client_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (OAuth 2.0)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'user.info.basic',
            'video.list',
            'video.upload',
            'video.publish',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        // Generate PKCE
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        
        $_SESSION['tiktok_code_verifier'] = $codeVerifier;
        
        $params = [
            'client_key' => $this->config['client_key'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'state' => $this->generateState(),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        
        return $this->buildUrl($this->authUrl, $params);
    }
    
    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
    
    private function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $codeVerifier = $_SESSION['tiktok_code_verifier'] ?? '';
        unset($_SESSION['tiktok_code_verifier']);
        
        $url = "{$this->apiUrl}/oauth/token/";
        
        $data = [
            'client_key' => $this->config['client_key'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ];
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query($data));
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = "{$this->apiUrl}/oauth/token/";
        
        $data = [
            'client_key' => $this->config['client_key'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query($data));
    }
    
    public function revokeToken(string $token): bool
    {
        $url = "{$this->apiUrl}/oauth/revoke/";
        
        try {
            $this->makeRequest('POST', $url, [
                'Content-Type: application/x-www-form-urlencoded',
            ], http_build_query([
                'client_key' => $this->config['client_key'],
                'client_secret' => $this->config['client_secret'],
                'token' => $token,
            ]));
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        $url = "{$this->apiUrl}/user/info/";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ], null, [
            'fields' => 'open_id,union_id,avatar_url,avatar_url_100,display_name,bio_description,profile_deep_link,is_verified,follower_count,following_count,likes_count,video_count',
        ]);
        
        return $response['data']['user'] ?? [];
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // TikTok doesn't have managed accounts like Facebook
        return [$this->getProfile($accessToken)];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Video Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        // TikTok requires video
        if (empty($options['video_url'])) {
            return [
                'success' => false,
                'error' => 'TikTok requires video content',
            ];
        }
        
        return $this->publishVideo($accessToken, $options['video_url'], $content, $options);
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $description = '', array $options = []): array
    {
        // Step 1: Initialize upload
        $initUrl = "{$this->apiUrl}/post/publish/inbox/video/init/";
        
        // Download video to get size
        $videoData = file_get_contents($videoUrl);
        $videoSize = strlen($videoData);
        $chunkSize = min($videoSize, 10 * 1024 * 1024); // 10MB chunks max
        $totalChunkCount = ceil($videoSize / $chunkSize);
        
        $initData = [
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => $videoSize,
                'chunk_size' => $chunkSize,
                'total_chunk_count' => $totalChunkCount,
            ],
        ];
        
        $initResponse = $this->makeRequest('POST', $initUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $initData);
        
        if (empty($initResponse['data']['publish_id'])) {
            return ['success' => false, 'error' => 'Failed to initialize upload'];
        }
        
        $publishId = $initResponse['data']['publish_id'];
        $uploadUrl = $initResponse['data']['upload_url'];
        
        // Step 2: Upload video chunks
        $offset = 0;
        $chunkIndex = 0;
        
        while ($offset < $videoSize) {
            $chunk = substr($videoData, $offset, $chunkSize);
            $chunkLength = strlen($chunk);
            
            $this->makeRequest('PUT', $uploadUrl, [
                'Content-Type: video/mp4',
                'Content-Length: ' . $chunkLength,
                'Content-Range: bytes ' . $offset . '-' . ($offset + $chunkLength - 1) . '/' . $videoSize,
            ], $chunk);
            
            $offset += $chunkLength;
            $chunkIndex++;
        }
        
        // Step 3: Publish video
        $publishUrl = "{$this->apiUrl}/post/publish/video/init/";
        
        $publishData = [
            'post_info' => [
                'title' => substr($description, 0, 150), // TikTok title limit
                'privacy_level' => $options['privacy'] ?? 'PUBLIC_TO_EVERYONE',
                'disable_duet' => $options['disable_duet'] ?? false,
                'disable_comment' => $options['disable_comment'] ?? false,
                'disable_stitch' => $options['disable_stitch'] ?? false,
                'video_cover_timestamp_ms' => $options['cover_timestamp'] ?? 0,
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $videoUrl, // Or use the uploaded video
            ],
        ];
        
        // For direct upload, use different source
        if (!empty($options['use_upload'])) {
            $publishData['source_info'] = [
                'source' => 'FILE_UPLOAD',
                'publish_id' => $publishId,
            ];
        }
        
        $response = $this->makeRequest('POST', $publishUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $publishData);
        
        Logger::info('TikTok video published', [
            'publish_id' => $response['data']['publish_id'] ?? null,
        ]);
        
        return [
            'success' => true,
            'post_id' => $response['data']['publish_id'] ?? null,
            'platform' => 'tiktok',
        ];
    }
    
    /**
     * Check publish status
     */
    public function getPublishStatus(string $accessToken, string $publishId): array
    {
        $url = "{$this->apiUrl}/post/publish/status/fetch/";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'publish_id' => $publishId,
        ]);
        
        return $response['data'] ?? [];
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        // TikTok doesn't provide delete API for published videos
        Logger::warning('TikTok video deletion not supported via API');
        return false;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Video Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get user's videos
     */
    public function getVideos(string $accessToken, int $maxCount = 20, string $cursor = ''): array
    {
        $url = "{$this->apiUrl}/video/list/";
        
        $data = [
            'max_count' => min($maxCount, 20),
        ];
        
        if (!empty($cursor)) {
            $data['cursor'] = $cursor;
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'videos' => $response['data']['videos'] ?? [],
            'cursor' => $response['data']['cursor'] ?? null,
            'has_more' => $response['data']['has_more'] ?? false,
        ];
    }
    
    /**
     * Query specific videos
     */
    public function queryVideos(string $accessToken, array $videoIds): array
    {
        $url = "{$this->apiUrl}/video/query/";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'filters' => [
                'video_ids' => $videoIds,
            ],
        ]);
        
        return $response['data']['videos'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        $videos = $this->queryVideos($accessToken, [$postId]);
        
        if (empty($videos)) {
            return [];
        }
        
        $video = $videos[0];
        
        return [
            'video_id' => $postId,
            'title' => $video['title'] ?? '',
            'create_time' => $video['create_time'] ?? '',
            'views' => $video['view_count'] ?? 0,
            'likes' => $video['like_count'] ?? 0,
            'comments' => $video['comment_count'] ?? 0,
            'shares' => $video['share_count'] ?? 0,
            'duration' => $video['duration'] ?? 0,
        ];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        $profile = $this->getProfile($accessToken);
        
        return [
            'followers' => $profile['follower_count'] ?? 0,
            'following' => $profile['following_count'] ?? 0,
            'likes' => $profile['likes_count'] ?? 0,
            'video_count' => $profile['video_count'] ?? 0,
        ];
    }
    
    public function getAudienceInsights(string $accessToken): array
    {
        // TikTok's audience insights require business API access
        return [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    */
    
    public function getComments(string $accessToken, string $postId): array
    {
        $url = "{$this->apiUrl}/video/comment/list/";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'video_id' => $postId,
            'max_count' => 50,
        ]);
        
        return $response['data']['comments'] ?? [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        $url = "{$this->apiUrl}/video/comment/reply/";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'comment_id' => $commentId,
            'text' => $message,
        ]);
        
        return [
            'success' => true,
            'comment_id' => $response['data']['comment_id'] ?? null,
        ];
    }
}
