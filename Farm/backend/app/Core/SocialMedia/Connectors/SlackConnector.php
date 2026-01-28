<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * Slack Connector
 * 
 * Slack API integration for workspace messaging and collaboration.
 * 
 * Features:
 * - Channel messaging
 * - Direct messages
 * - Rich message blocks
 * - File sharing
 * - Slash commands
 * - Interactive components
 * 
 * API Docs: https://api.slack.com/
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class SlackConnector extends BasePlatformConnector
{
    protected string $platformName = 'Slack';
    protected string $platformType = 'messaging';
    protected array $supportedContentTypes = ['text', 'image', 'file', 'blocks'];
    
    private string $apiUrl = 'https://slack.com/api';
    
    protected function getConfigKey(): string
    {
        return 'slack';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (OAuth 2.0)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'channels:read',
            'channels:write',
            'chat:write',
            'files:read',
            'files:write',
            'users:read',
            'im:read',
            'im:write',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'state' => $this->generateState(),
        ];
        
        // Add user scopes if needed
        if (!empty($this->config['user_scopes'])) {
            $params['user_scope'] = implode(',', $this->config['user_scopes']);
        }
        
        return $this->buildUrl('https://slack.com/oauth/v2/authorize', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = "{$this->apiUrl}/oauth.v2.access";
        
        $data = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = "{$this->apiUrl}/oauth.v2.access";
        
        $data = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function revokeToken(string $token): bool
    {
        $url = "{$this->apiUrl}/auth.revoke";
        
        try {
            $this->makeRequest('POST', $url, [
                "Authorization: Bearer $token",
            ]);
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
        $url = "{$this->apiUrl}/auth.test";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        if ($response['ok'] ?? false) {
            // Get more user info
            $userUrl = "{$this->apiUrl}/users.info";
            $userResponse = $this->makeRequest('POST', $userUrl, [
                "Authorization: Bearer $accessToken",
                'Content-Type: application/x-www-form-urlencoded',
            ], http_build_query(['user' => $response['user_id']]));
            
            return array_merge($response, $userResponse['user'] ?? []);
        }
        
        return $response;
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Get channels the bot is a member of
        $url = "{$this->apiUrl}/conversations.list";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'types' => 'public_channel,private_channel',
        ]));
        
        return $response['channels'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Messaging
    |--------------------------------------------------------------------------
    */
    
    public function sendMessage(string $accessToken, string $channelId, string $message, array $options = []): array
    {
        $url = "{$this->apiUrl}/chat.postMessage";
        
        $data = [
            'channel' => $channelId,
            'text' => $message,
        ];
        
        // Add blocks for rich formatting
        if (!empty($options['blocks'])) {
            $data['blocks'] = json_encode($options['blocks']);
        }
        
        // Add attachments
        if (!empty($options['attachments'])) {
            $data['attachments'] = json_encode($options['attachments']);
        }
        
        // Thread reply
        if (!empty($options['thread_ts'])) {
            $data['thread_ts'] = $options['thread_ts'];
            $data['reply_broadcast'] = $options['reply_broadcast'] ?? false;
        }
        
        // Unfurl settings
        if (isset($options['unfurl_links'])) {
            $data['unfurl_links'] = $options['unfurl_links'];
        }
        
        if (isset($options['unfurl_media'])) {
            $data['unfurl_media'] = $options['unfurl_media'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'message_id' => $response['ts'] ?? null,
            'channel_id' => $response['channel'] ?? $channelId,
        ];
    }
    
    public function getMessages(string $accessToken, string $channelId, int $limit = 100): array
    {
        $url = "{$this->apiUrl}/conversations.history";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'channel' => $channelId,
            'limit' => min($limit, 1000),
        ]));
        
        return $response['messages'] ?? [];
    }
    
    /**
     * Send block kit message
     */
    public function sendBlockMessage(string $accessToken, string $channelId, array $blocks, string $fallbackText = ''): array
    {
        return $this->sendMessage($accessToken, $channelId, $fallbackText, [
            'blocks' => $blocks,
        ]);
    }
    
    /**
     * Update message
     */
    public function updateMessage(string $accessToken, string $channelId, string $messageTs, string $newText, array $options = []): array
    {
        $url = "{$this->apiUrl}/chat.update";
        
        $data = [
            'channel' => $channelId,
            'ts' => $messageTs,
            'text' => $newText,
        ];
        
        if (!empty($options['blocks'])) {
            $data['blocks'] = json_encode($options['blocks']);
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'message_id' => $response['ts'] ?? null,
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        $channelId = $options['channel_id'] ?? $options['channel'] ?? null;
        
        if (!$channelId) {
            return ['success' => false, 'error' => 'Channel ID required'];
        }
        
        return $this->sendMessage($accessToken, $channelId, $content, $options);
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array
    {
        $channelId = $options['channel_id'] ?? $options['channel'] ?? null;
        
        if (!$channelId) {
            return ['success' => false, 'error' => 'Channel ID required'];
        }
        
        // Create attachment with image
        $options['attachments'] = [
            [
                'text' => $caption,
                'image_url' => $imageUrl,
            ],
        ];
        
        return $this->sendMessage($accessToken, $channelId, $caption, $options);
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array
    {
        // Slack doesn't embed videos directly; share as link
        $message = !empty($caption) ? "$caption\n$videoUrl" : $videoUrl;
        
        return $this->publishPost($accessToken, $message, $options);
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        // postId format: "channelId:messageTs"
        $parts = explode(':', $postId);
        $channelId = $parts[0] ?? null;
        $messageTs = $parts[1] ?? $postId;
        
        if (!$channelId) {
            return false;
        }
        
        $url = "{$this->apiUrl}/chat.delete";
        
        try {
            $response = $this->makeRequest('POST', $url, [
                "Authorization: Bearer $accessToken",
                'Content-Type: application/json',
            ], [
                'channel' => $channelId,
                'ts' => $messageTs,
            ]);
            
            return $response['ok'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | File Sharing
    |--------------------------------------------------------------------------
    */
    
    /**
     * Upload file
     */
    public function uploadFile(string $accessToken, string $channelId, string $filePath, array $options = []): array
    {
        $url = "{$this->apiUrl}/files.upload";
        
        $data = [
            'channels' => $channelId,
            'file' => new \CURLFile($filePath),
        ];
        
        if (!empty($options['title'])) {
            $data['title'] = $options['title'];
        }
        
        if (!empty($options['initial_comment'])) {
            $data['initial_comment'] = $options['initial_comment'];
        }
        
        // Note: This requires multipart form data
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => $result['ok'] ?? false,
            'file_id' => $result['file']['id'] ?? null,
        ];
    }
    
    /**
     * Share remote file
     */
    public function shareRemoteFile(string $accessToken, string $channelId, string $externalUrl, string $title): array
    {
        $url = "{$this->apiUrl}/files.remote.add";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'external_id' => uniqid('file_'),
            'external_url' => $externalUrl,
            'title' => $title,
        ]);
        
        if ($response['ok'] ?? false) {
            // Share to channel
            $shareUrl = "{$this->apiUrl}/files.remote.share";
            $this->makeRequest('POST', $shareUrl, [
                "Authorization: Bearer $accessToken",
                'Content-Type: application/json',
            ], [
                'file' => $response['file']['id'],
                'channels' => $channelId,
            ]);
        }
        
        return [
            'success' => $response['ok'] ?? false,
            'file_id' => $response['file']['id'] ?? null,
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Channel Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get channel info
     */
    public function getChannelInfo(string $accessToken, string $channelId): array
    {
        $url = "{$this->apiUrl}/conversations.info";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query(['channel' => $channelId]));
        
        return $response['channel'] ?? [];
    }
    
    /**
     * Create channel
     */
    public function createChannel(string $accessToken, string $name, bool $isPrivate = false): array
    {
        $url = "{$this->apiUrl}/conversations.create";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'name' => $name,
            'is_private' => $isPrivate,
        ]);
        
        return [
            'success' => $response['ok'] ?? false,
            'channel_id' => $response['channel']['id'] ?? null,
        ];
    }
    
    /**
     * Invite user to channel
     */
    public function inviteToChannel(string $accessToken, string $channelId, string $userId): bool
    {
        $url = "{$this->apiUrl}/conversations.invite";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'channel' => $channelId,
            'users' => $userId,
        ]);
        
        return $response['ok'] ?? false;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Direct Messages
    |--------------------------------------------------------------------------
    */
    
    /**
     * Open direct message channel
     */
    public function openDM(string $accessToken, string $userId): ?string
    {
        $url = "{$this->apiUrl}/conversations.open";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'users' => $userId,
        ]);
        
        return $response['channel']['id'] ?? null;
    }
    
    /**
     * Send direct message to user
     */
    public function sendDirectMessage(string $accessToken, string $userId, string $message, array $options = []): array
    {
        $channelId = $this->openDM($accessToken, $userId);
        
        if (!$channelId) {
            return ['success' => false, 'error' => 'Could not open DM channel'];
        }
        
        return $this->sendMessage($accessToken, $channelId, $message, $options);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Reactions & Engagement
    |--------------------------------------------------------------------------
    */
    
    /**
     * Add reaction to message
     */
    public function addReaction(string $accessToken, string $channelId, string $messageTs, string $emoji): bool
    {
        $url = "{$this->apiUrl}/reactions.add";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ], [
            'channel' => $channelId,
            'timestamp' => $messageTs,
            'name' => $emoji, // without colons, e.g., "thumbsup"
        ]);
        
        return $response['ok'] ?? false;
    }
    
    public function getComments(string $accessToken, string $postId): array
    {
        // Get thread replies
        $parts = explode(':', $postId);
        $channelId = $parts[0] ?? null;
        $threadTs = $parts[1] ?? null;
        
        if (!$channelId || !$threadTs) {
            return [];
        }
        
        $url = "{$this->apiUrl}/conversations.replies";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'channel' => $channelId,
            'ts' => $threadTs,
        ]));
        
        return $response['messages'] ?? [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        // commentId format: "channelId:threadTs"
        $parts = explode(':', $commentId);
        $channelId = $parts[0] ?? null;
        $threadTs = $parts[1] ?? null;
        
        if (!$channelId || !$threadTs) {
            return ['success' => false, 'error' => 'Invalid comment ID format'];
        }
        
        return $this->sendMessage($accessToken, $channelId, $message, [
            'thread_ts' => $threadTs,
        ]);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        // Slack doesn't provide message analytics via API
        return [];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        // Get team info
        $url = "{$this->apiUrl}/team.info";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response['team'] ?? [];
    }
}
