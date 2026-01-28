<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * YouTube Connector
 * 
 * YouTube Data API v3 integration for video management and analytics.
 * 
 * Features:
 * - Video upload and management
 * - Playlist management
 * - Channel analytics
 * - Comment management
 * - Live streaming support
 * 
 * API Docs: https://developers.google.com/youtube/v3
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class YouTubeConnector extends BasePlatformConnector
{
    protected string $platformName = 'YouTube';
    protected string $platformType = 'video';
    protected array $supportedContentTypes = ['video', 'short', 'live'];
    
    private string $apiUrl = 'https://www.googleapis.com/youtube/v3';
    private string $uploadUrl = 'https://www.googleapis.com/upload/youtube/v3';
    
    protected function getConfigKey(): string
    {
        return 'youtube';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (Google OAuth 2.0)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/yt-analytics.readonly',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $this->generateState(),
        ];
        
        return $this->buildUrl('https://accounts.google.com/o/oauth2/v2/auth', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = 'https://oauth2.googleapis.com/token';
        
        $data = http_build_query([
            'code' => $code,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = 'https://oauth2.googleapis.com/token';
        
        $data = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function revokeToken(string $token): bool
    {
        $url = "https://oauth2.googleapis.com/revoke?token={$token}";
        
        try {
            $this->makeRequest('POST', $url, [
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Profile & Channel
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/channels", [
            'part' => 'snippet,statistics,contentDetails,brandingSettings',
            'mine' => 'true',
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        $channel = $response['items'][0] ?? [];
        
        return [
            'id' => $channel['id'] ?? null,
            'title' => $channel['snippet']['title'] ?? '',
            'description' => $channel['snippet']['description'] ?? '',
            'thumbnail' => $channel['snippet']['thumbnails']['default']['url'] ?? '',
            'custom_url' => $channel['snippet']['customUrl'] ?? '',
            'subscribers' => $channel['statistics']['subscriberCount'] ?? 0,
            'video_count' => $channel['statistics']['videoCount'] ?? 0,
            'view_count' => $channel['statistics']['viewCount'] ?? 0,
            'uploads_playlist' => $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null,
        ];
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Return the authenticated channel(s)
        return [$this->getProfile($accessToken)];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Video Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        // YouTube requires video - text-only not supported
        if (empty($options['video_url']) && empty($options['video_path'])) {
            return [
                'success' => false,
                'error' => 'YouTube requires video content',
            ];
        }
        
        return $this->publishVideo(
            $accessToken,
            $options['video_url'] ?? $options['video_path'],
            $content,
            $options
        );
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $description = '', array $options = []): array
    {
        // Step 1: Start resumable upload
        $initUrl = $this->buildUrl("{$this->uploadUrl}/videos", [
            'uploadType' => 'resumable',
            'part' => 'snippet,status',
        ]);
        
        $metadata = [
            'snippet' => [
                'title' => $options['title'] ?? 'Untitled Video',
                'description' => $description,
                'tags' => $options['tags'] ?? [],
                'categoryId' => $options['category_id'] ?? '22', // People & Blogs
            ],
            'status' => [
                'privacyStatus' => $options['privacy'] ?? 'public',
                'madeForKids' => $options['made_for_kids'] ?? false,
                'selfDeclaredMadeForKids' => $options['made_for_kids'] ?? false,
            ],
        ];
        
        // Add scheduled publish time
        if (!empty($options['publish_at'])) {
            $metadata['status']['privacyStatus'] = 'private';
            $metadata['status']['publishAt'] = $options['publish_at'];
        }
        
        $initResponse = $this->makeRequest('POST', $initUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'X-Upload-Content-Type: video/*',
        ], $metadata);
        
        // Get upload URL from Location header
        $uploadUri = $initResponse['location'] ?? null;
        
        if (!$uploadUri) {
            return ['success' => false, 'error' => 'Failed to initialize upload'];
        }
        
        // Step 2: Upload video content
        $videoData = file_get_contents($videoUrl);
        
        $uploadResponse = $this->makeRequest('PUT', $uploadUri, [
            "Authorization: Bearer $accessToken",
            'Content-Type: video/*',
            'Content-Length: ' . strlen($videoData),
        ], $videoData);
        
        Logger::info('YouTube video uploaded', [
            'video_id' => $uploadResponse['id'] ?? null,
        ]);
        
        return [
            'success' => true,
            'post_id' => $uploadResponse['id'] ?? null,
            'video_id' => $uploadResponse['id'] ?? null,
            'platform' => 'youtube',
        ];
    }
    
    /**
     * Upload YouTube Short (vertical video < 60 seconds)
     */
    public function publishShort(string $accessToken, string $videoUrl, string $title, array $options = []): array
    {
        // Shorts are regular videos with #Shorts in title
        if (stripos($title, '#shorts') === false) {
            $title .= ' #Shorts';
        }
        
        $options['title'] = $title;
        
        return $this->publishVideo($accessToken, $videoUrl, $options['description'] ?? '', $options);
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        $url = $this->buildUrl("{$this->apiUrl}/videos", [
            'id' => $postId,
        ]);
        
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
     * Update video metadata
     */
    public function updateVideo(string $accessToken, string $videoId, array $updates): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/videos", [
            'part' => 'snippet,status',
        ]);
        
        $data = [
            'id' => $videoId,
        ];
        
        if (!empty($updates['title']) || !empty($updates['description']) || !empty($updates['tags'])) {
            $data['snippet'] = array_filter([
                'title' => $updates['title'] ?? null,
                'description' => $updates['description'] ?? null,
                'tags' => $updates['tags'] ?? null,
                'categoryId' => $updates['category_id'] ?? null,
            ]);
        }
        
        if (!empty($updates['privacy'])) {
            $data['status'] = [
                'privacyStatus' => $updates['privacy'],
            ];
        }
        
        $response = $this->makeRequest('PUT', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'video_id' => $response['id'] ?? null,
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Playlist Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Create a playlist
     */
    public function createPlaylist(string $accessToken, string $title, string $description = '', array $options = []): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/playlists", [
            'part' => 'snippet,status',
        ]);
        
        $data = [
            'snippet' => [
                'title' => $title,
                'description' => $description,
            ],
            'status' => [
                'privacyStatus' => $options['privacy'] ?? 'public',
            ],
        ];
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'playlist_id' => $response['id'] ?? null,
        ];
    }
    
    /**
     * Add video to playlist
     */
    public function addToPlaylist(string $accessToken, string $playlistId, string $videoId): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/playlistItems", [
            'part' => 'snippet',
        ]);
        
        $data = [
            'snippet' => [
                'playlistId' => $playlistId,
                'resourceId' => [
                    'kind' => 'youtube#video',
                    'videoId' => $videoId,
                ],
            ],
        ];
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'item_id' => $response['id'] ?? null,
        ];
    }
    
    /**
     * Get playlists
     */
    public function getPlaylists(string $accessToken, int $maxResults = 25): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/playlists", [
            'part' => 'snippet,contentDetails',
            'mine' => 'true',
            'maxResults' => $maxResults,
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
        $url = $this->buildUrl("{$this->apiUrl}/videos", [
            'part' => 'statistics,snippet',
            'id' => $postId,
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        $video = $response['items'][0] ?? [];
        
        return [
            'video_id' => $postId,
            'title' => $video['snippet']['title'] ?? '',
            'published_at' => $video['snippet']['publishedAt'] ?? '',
            'views' => $video['statistics']['viewCount'] ?? 0,
            'likes' => $video['statistics']['likeCount'] ?? 0,
            'comments' => $video['statistics']['commentCount'] ?? 0,
            'favorites' => $video['statistics']['favoriteCount'] ?? 0,
        ];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        // Use YouTube Analytics API
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $defaultMetrics = [
            'views',
            'estimatedMinutesWatched',
            'averageViewDuration',
            'subscribersGained',
            'subscribersLost',
            'likes',
            'dislikes',
            'comments',
        ];
        
        $metrics = !empty($metrics) ? $metrics : $defaultMetrics;
        
        $url = $this->buildUrl('https://youtubeanalytics.googleapis.com/v2/reports', [
            'ids' => 'channel==MINE',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'metrics' => implode(',', $metrics),
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    public function getAudienceInsights(string $accessToken): array
    {
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        
        $url = $this->buildUrl('https://youtubeanalytics.googleapis.com/v2/reports', [
            'ids' => 'channel==MINE',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => 'country',
            'metrics' => 'views,estimatedMinutesWatched',
            'sort' => '-views',
            'maxResults' => 10,
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    /**
     * Get video list for channel
     */
    public function getVideos(string $accessToken, int $maxResults = 25): array
    {
        $profile = $this->getProfile($accessToken);
        $uploadsPlaylist = $profile['uploads_playlist'] ?? null;
        
        if (!$uploadsPlaylist) {
            return [];
        }
        
        $url = $this->buildUrl("{$this->apiUrl}/playlistItems", [
            'part' => 'snippet,contentDetails',
            'playlistId' => $uploadsPlaylist,
            'maxResults' => $maxResults,
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response['items'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    */
    
    public function getComments(string $accessToken, string $postId): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/commentThreads", [
            'part' => 'snippet,replies',
            'videoId' => $postId,
            'maxResults' => 50,
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response['items'] ?? [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/comments", [
            'part' => 'snippet',
        ]);
        
        $data = [
            'snippet' => [
                'parentId' => $commentId,
                'textOriginal' => $message,
            ],
        ];
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'comment_id' => $response['id'] ?? null,
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Live Streaming
    |--------------------------------------------------------------------------
    */
    
    /**
     * Create a live broadcast
     */
    public function createLiveBroadcast(string $accessToken, string $title, string $scheduledStartTime, array $options = []): array
    {
        // Create broadcast
        $broadcastUrl = $this->buildUrl("{$this->apiUrl}/liveBroadcasts", [
            'part' => 'snippet,status,contentDetails',
        ]);
        
        $broadcastData = [
            'snippet' => [
                'title' => $title,
                'description' => $options['description'] ?? '',
                'scheduledStartTime' => $scheduledStartTime,
            ],
            'status' => [
                'privacyStatus' => $options['privacy'] ?? 'public',
            ],
            'contentDetails' => [
                'enableAutoStart' => $options['auto_start'] ?? false,
                'enableAutoStop' => $options['auto_stop'] ?? true,
            ],
        ];
        
        $broadcast = $this->makeRequest('POST', $broadcastUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $broadcastData);
        
        // Create stream
        $streamUrl = $this->buildUrl("{$this->apiUrl}/liveStreams", [
            'part' => 'snippet,cdn',
        ]);
        
        $streamData = [
            'snippet' => [
                'title' => $title . ' Stream',
            ],
            'cdn' => [
                'frameRate' => $options['frame_rate'] ?? 'variable',
                'ingestionType' => 'rtmp',
                'resolution' => $options['resolution'] ?? 'variable',
            ],
        ];
        
        $stream = $this->makeRequest('POST', $streamUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $streamData);
        
        // Bind stream to broadcast
        $bindUrl = $this->buildUrl("{$this->apiUrl}/liveBroadcasts/bind", [
            'part' => 'id,contentDetails',
            'id' => $broadcast['id'],
            'streamId' => $stream['id'],
        ]);
        
        $this->makeRequest('POST', $bindUrl, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return [
            'success' => true,
            'broadcast_id' => $broadcast['id'],
            'stream_id' => $stream['id'],
            'stream_key' => $stream['cdn']['ingestionInfo']['streamName'] ?? null,
            'rtmp_url' => $stream['cdn']['ingestionInfo']['ingestionAddress'] ?? null,
        ];
    }
}
