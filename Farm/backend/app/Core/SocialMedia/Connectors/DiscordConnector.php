<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * Discord Connector
 * 
 * Discord API integration for server management and messaging.
 * 
 * Features:
 * - Channel messaging
 * - Embed messages
 * - Webhook integration
 * - Bot commands
 * - Server management
 * 
 * API Docs: https://discord.com/developers/docs
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class DiscordConnector extends BasePlatformConnector
{
    protected string $platformName = 'Discord';
    protected string $platformType = 'messaging';
    protected array $supportedContentTypes = ['text', 'image', 'video', 'embed', 'file'];
    
    private string $apiUrl = 'https://discord.com/api/v10';
    
    protected function getConfigKey(): string
    {
        return 'discord';
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
            'identify',
            'guilds',
            'guilds.members.read',
        ];
        
        // Add bot scope if configured
        if ($this->config['is_bot'] ?? false) {
            $defaultScopes[] = 'bot';
            $defaultScopes[] = 'applications.commands';
        }
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $this->generateState(),
        ];
        
        // Add permissions for bot
        if (in_array('bot', $scopes) && !empty($this->config['permissions'])) {
            $params['permissions'] = $this->config['permissions'];
        }
        
        return $this->buildUrl('https://discord.com/oauth2/authorize', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = "{$this->apiUrl}/oauth2/token";
        
        $data = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = "{$this->apiUrl}/oauth2/token";
        
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
        $url = "{$this->apiUrl}/oauth2/token/revoke";
        
        try {
            $this->makeRequest('POST', $url, [
                'Content-Type: application/x-www-form-urlencoded',
            ], http_build_query([
                'client_id' => $this->config['client_id'],
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
        $url = "{$this->apiUrl}/users/@me";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Get user's guilds (servers)
        $url = "{$this->apiUrl}/users/@me/guilds";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return $response;
    }
    
    /**
     * Get bot token for API calls
     */
    private function getBotAuth(): string
    {
        return "Bot " . ($this->config['bot_token'] ?? '');
    }
    
    /*
    |--------------------------------------------------------------------------
    | Messaging
    |--------------------------------------------------------------------------
    */
    
    public function sendMessage(string $accessToken, string $channelId, string $message, array $options = []): array
    {
        $url = "{$this->apiUrl}/channels/{$channelId}/messages";
        
        $data = [
            'content' => $message,
        ];
        
        // Add embed
        if (!empty($options['embeds'])) {
            $data['embeds'] = $options['embeds'];
        }
        
        // Add components (buttons, selects)
        if (!empty($options['components'])) {
            $data['components'] = $options['components'];
        }
        
        // Add attachments reference
        if (!empty($options['attachments'])) {
            $data['attachments'] = $options['attachments'];
        }
        
        // Reply to message
        if (!empty($options['reply_to'])) {
            $data['message_reference'] = [
                'message_id' => $options['reply_to'],
            ];
        }
        
        // Use bot token for sending
        $auth = $this->getBotAuth();
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: $auth",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'message_id' => $response['id'] ?? null,
            'channel_id' => $channelId,
        ];
    }
    
    public function getMessages(string $accessToken, string $channelId, int $limit = 50): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/channels/{$channelId}/messages", [
            'limit' => min($limit, 100),
        ]);
        
        $auth = $this->getBotAuth();
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: $auth",
        ]);
        
        return $response;
    }
    
    /**
     * Send rich embed message
     */
    public function sendEmbed(string $channelId, array $embed, string $content = ''): array
    {
        $url = "{$this->apiUrl}/channels/{$channelId}/messages";
        
        $data = [
            'embeds' => [$embed],
        ];
        
        if (!empty($content)) {
            $data['content'] = $content;
        }
        
        $auth = $this->getBotAuth();
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: $auth",
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'message_id' => $response['id'] ?? null,
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        $channelId = $options['channel_id'] ?? null;
        
        if (!$channelId) {
            return ['success' => false, 'error' => 'Channel ID required'];
        }
        
        return $this->sendMessage($accessToken, $channelId, $content, $options);
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array
    {
        $channelId = $options['channel_id'] ?? null;
        
        if (!$channelId) {
            return ['success' => false, 'error' => 'Channel ID required'];
        }
        
        // Create embed with image
        $embed = [
            'image' => [
                'url' => $imageUrl,
            ],
        ];
        
        if (!empty($caption)) {
            $embed['description'] = $caption;
        }
        
        $options['embeds'] = [$embed];
        
        return $this->sendMessage($accessToken, $channelId, '', $options);
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array
    {
        $channelId = $options['channel_id'] ?? null;
        
        if (!$channelId) {
            return ['success' => false, 'error' => 'Channel ID required'];
        }
        
        // Discord embeds video URLs from supported platforms
        $message = $caption . "\n" . $videoUrl;
        
        return $this->sendMessage($accessToken, $channelId, $message, $options);
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        // postId format: "channelId:messageId"
        $parts = explode(':', $postId);
        $channelId = $parts[0] ?? null;
        $messageId = $parts[1] ?? $postId;
        
        if (!$channelId) {
            return false;
        }
        
        $url = "{$this->apiUrl}/channels/{$channelId}/messages/{$messageId}";
        
        try {
            $this->makeRequest('DELETE', $url, [
                "Authorization: " . $this->getBotAuth(),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Channel Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get channel info
     */
    public function getChannel(string $channelId): array
    {
        $url = "{$this->apiUrl}/channels/{$channelId}";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: " . $this->getBotAuth(),
        ]);
        
        return $response;
    }
    
    /**
     * Get guild (server) channels
     */
    public function getGuildChannels(string $guildId): array
    {
        $url = "{$this->apiUrl}/guilds/{$guildId}/channels";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: " . $this->getBotAuth(),
        ]);
        
        return $response;
    }
    
    /**
     * Create channel
     */
    public function createChannel(string $guildId, string $name, int $type = 0, array $options = []): array
    {
        $url = "{$this->apiUrl}/guilds/{$guildId}/channels";
        
        $data = [
            'name' => $name,
            'type' => $type, // 0 = text, 2 = voice, 4 = category
        ];
        
        if (!empty($options['topic'])) {
            $data['topic'] = $options['topic'];
        }
        
        if (!empty($options['parent_id'])) {
            $data['parent_id'] = $options['parent_id'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: " . $this->getBotAuth(),
            'Content-Type: application/json',
        ], $data);
        
        return $response;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    
    /**
     * Send message via webhook
     */
    public function sendWebhookMessage(string $webhookUrl, string $content, array $options = []): array
    {
        $data = [
            'content' => $content,
        ];
        
        if (!empty($options['username'])) {
            $data['username'] = $options['username'];
        }
        
        if (!empty($options['avatar_url'])) {
            $data['avatar_url'] = $options['avatar_url'];
        }
        
        if (!empty($options['embeds'])) {
            $data['embeds'] = $options['embeds'];
        }
        
        $response = $this->makeRequest('POST', $webhookUrl, [
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => true,
            'message_id' => $response['id'] ?? null,
        ];
    }
    
    /**
     * Create webhook
     */
    public function createWebhook(string $channelId, string $name): array
    {
        $url = "{$this->apiUrl}/channels/{$channelId}/webhooks";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: " . $this->getBotAuth(),
            'Content-Type: application/json',
        ], [
            'name' => $name,
        ]);
        
        return $response;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Reactions & Engagement
    |--------------------------------------------------------------------------
    */
    
    /**
     * Add reaction to message
     */
    public function addReaction(string $channelId, string $messageId, string $emoji): bool
    {
        $encodedEmoji = urlencode($emoji);
        $url = "{$this->apiUrl}/channels/{$channelId}/messages/{$messageId}/reactions/{$encodedEmoji}/@me";
        
        try {
            $this->makeRequest('PUT', $url, [
                "Authorization: " . $this->getBotAuth(),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getComments(string $accessToken, string $postId): array
    {
        // Discord doesn't have threaded comments in the traditional sense
        // Return replies in a thread if applicable
        return [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        // commentId format: "channelId:messageId"
        $parts = explode(':', $commentId);
        $channelId = $parts[0] ?? null;
        $messageId = $parts[1] ?? null;
        
        if (!$channelId || !$messageId) {
            return ['success' => false, 'error' => 'Invalid comment ID format'];
        }
        
        return $this->sendMessage($accessToken, $channelId, $message, [
            'reply_to' => $messageId,
        ]);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics (Limited)
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        // Discord doesn't provide analytics
        return [];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        return [];
    }
}
