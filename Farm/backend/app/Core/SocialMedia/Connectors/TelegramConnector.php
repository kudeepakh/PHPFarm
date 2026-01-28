<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * Telegram Connector
 * 
 * Telegram Bot API integration for messaging and channel management.
 * 
 * Features:
 * - Send messages (text, media, documents)
 * - Channel/group management
 * - Inline keyboards and buttons
 * - Webhook handling
 * 
 * API Docs: https://core.telegram.org/bots/api
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class TelegramConnector extends BasePlatformConnector
{
    protected string $platformName = 'Telegram';
    protected string $platformType = 'messaging';
    protected array $supportedContentTypes = ['text', 'image', 'video', 'document', 'audio', 'poll'];
    
    private string $apiUrl = 'https://api.telegram.org/bot';
    
    protected function getConfigKey(): string
    {
        return 'telegram';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['bot_token']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (Bot Token based)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        // Telegram uses bot tokens, not OAuth
        // Return link to BotFather for token creation
        return 'https://t.me/BotFather';
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        // Bot token is configured directly, not via OAuth
        return [
            'access_token' => $this->config['bot_token'],
        ];
    }
    
    public function refreshToken(string $refreshToken): array
    {
        // Bot tokens don't expire
        return [
            'access_token' => $this->config['bot_token'],
        ];
    }
    
    public function revokeToken(string $token): bool
    {
        // Tokens can only be revoked via BotFather
        return true;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        $url = $this->getApiUrl($accessToken) . '/getMe';
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['result'] ?? [];
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Return bot info
        return [$this->getProfile($accessToken)];
    }
    
    /**
     * Get API URL with bot token
     */
    private function getApiUrl(string $token): string
    {
        return $this->apiUrl . $token;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Messaging
    |--------------------------------------------------------------------------
    */
    
    public function sendMessage(string $accessToken, string $recipientId, string $message, array $options = []): array
    {
        $url = $this->getApiUrl($accessToken) . '/sendMessage';
        
        $data = [
            'chat_id' => $recipientId,
            'text' => $message,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ];
        
        // Add inline keyboard
        if (!empty($options['keyboard'])) {
            $data['reply_markup'] = json_encode([
                'inline_keyboard' => $options['keyboard'],
            ]);
        }
        
        // Disable link preview
        if (!empty($options['disable_preview'])) {
            $data['disable_web_page_preview'] = true;
        }
        
        // Silent message
        if (!empty($options['silent'])) {
            $data['disable_notification'] = true;
        }
        
        // Reply to message
        if (!empty($options['reply_to'])) {
            $data['reply_to_message_id'] = $options['reply_to'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'message_id' => $response['result']['message_id'] ?? null,
        ];
    }
    
    public function getMessages(string $accessToken, string $conversationId): array
    {
        // Telegram doesn't provide message history via API
        // Messages are received via webhooks or getUpdates
        return [];
    }
    
    /**
     * Get updates (polling method)
     */
    public function getUpdates(string $accessToken, int $offset = 0, int $limit = 100): array
    {
        $url = $this->getApiUrl($accessToken) . '/getUpdates';
        
        $data = [
            'offset' => $offset,
            'limit' => min($limit, 100),
            'timeout' => 30,
        ];
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return $response['result'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        $chatId = $options['chat_id'] ?? $options['channel'] ?? null;
        
        if (!$chatId) {
            return ['success' => false, 'error' => 'Chat ID or channel required'];
        }
        
        return $this->sendMessage($accessToken, $chatId, $content, $options);
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array
    {
        $chatId = $options['chat_id'] ?? $options['channel'] ?? null;
        
        if (!$chatId) {
            return ['success' => false, 'error' => 'Chat ID or channel required'];
        }
        
        $url = $this->getApiUrl($accessToken) . '/sendPhoto';
        
        $data = [
            'chat_id' => $chatId,
            'photo' => $imageUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'post_id' => $response['result']['message_id'] ?? null,
            'platform' => 'telegram',
        ];
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array
    {
        $chatId = $options['chat_id'] ?? $options['channel'] ?? null;
        
        if (!$chatId) {
            return ['success' => false, 'error' => 'Chat ID or channel required'];
        }
        
        $url = $this->getApiUrl($accessToken) . '/sendVideo';
        
        $data = [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];
        
        if (!empty($options['duration'])) {
            $data['duration'] = $options['duration'];
        }
        
        if (!empty($options['supports_streaming'])) {
            $data['supports_streaming'] = true;
        }
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'post_id' => $response['result']['message_id'] ?? null,
            'platform' => 'telegram',
        ];
    }
    
    /**
     * Send document
     */
    public function sendDocument(string $accessToken, string $chatId, string $documentUrl, string $caption = ''): array
    {
        $url = $this->getApiUrl($accessToken) . '/sendDocument';
        
        $data = [
            'chat_id' => $chatId,
            'document' => $documentUrl,
            'caption' => $caption,
        ];
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'message_id' => $response['result']['message_id'] ?? null,
        ];
    }
    
    /**
     * Create poll
     */
    public function createPoll(string $accessToken, string $chatId, string $question, array $options, array $settings = []): array
    {
        $url = $this->getApiUrl($accessToken) . '/sendPoll';
        
        $data = [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => json_encode($options),
            'is_anonymous' => $settings['anonymous'] ?? true,
            'type' => $settings['type'] ?? 'regular', // or 'quiz'
            'allows_multiple_answers' => $settings['multiple'] ?? false,
        ];
        
        if ($settings['type'] === 'quiz' && isset($settings['correct_option'])) {
            $data['correct_option_id'] = $settings['correct_option'];
        }
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return [
            'success' => $response['ok'] ?? false,
            'poll_id' => $response['result']['poll']['id'] ?? null,
            'message_id' => $response['result']['message_id'] ?? null,
        ];
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        // postId format: "chatId:messageId"
        $parts = explode(':', $postId);
        $chatId = $parts[0] ?? null;
        $messageId = $parts[1] ?? $postId;
        
        if (!$chatId) {
            return false;
        }
        
        $url = $this->getApiUrl($accessToken) . '/deleteMessage';
        
        try {
            $response = $this->makeRequest('POST', $url, [
                'Content-Type: application/json',
            ], [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
            
            return $response['ok'] ?? false;
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
     * Get chat/channel info
     */
    public function getChatInfo(string $accessToken, string $chatId): array
    {
        $url = $this->getApiUrl($accessToken) . '/getChat';
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], [
            'chat_id' => $chatId,
        ]);
        
        return $response['result'] ?? [];
    }
    
    /**
     * Get chat member count
     */
    public function getMemberCount(string $accessToken, string $chatId): int
    {
        $url = $this->getApiUrl($accessToken) . '/getChatMemberCount';
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], [
            'chat_id' => $chatId,
        ]);
        
        return $response['result'] ?? 0;
    }
    
    /**
     * Get chat administrators
     */
    public function getAdministrators(string $accessToken, string $chatId): array
    {
        $url = $this->getApiUrl($accessToken) . '/getChatAdministrators';
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], [
            'chat_id' => $chatId,
        ]);
        
        return $response['result'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Set webhook URL
     */
    public function setWebhook(string $accessToken, string $webhookUrl, array $options = []): bool
    {
        $url = $this->getApiUrl($accessToken) . '/setWebhook';
        
        $data = [
            'url' => $webhookUrl,
        ];
        
        if (!empty($options['secret_token'])) {
            $data['secret_token'] = $options['secret_token'];
        }
        
        if (!empty($options['allowed_updates'])) {
            $data['allowed_updates'] = json_encode($options['allowed_updates']);
        }
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return $response['ok'] ?? false;
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook(string $accessToken): bool
    {
        $url = $this->getApiUrl($accessToken) . '/deleteWebhook';
        
        $response = $this->makeRequest('POST', $url);
        
        return $response['ok'] ?? false;
    }
    
    /**
     * Get webhook info
     */
    public function getWebhookInfo(string $accessToken): array
    {
        $url = $this->getApiUrl($accessToken) . '/getWebhookInfo';
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['result'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics (Limited)
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        // Telegram doesn't provide post analytics via API
        return [];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        $botInfo = $this->getProfile($accessToken);
        
        return [
            'bot' => $botInfo,
        ];
    }
    
    public function getComments(string $accessToken, string $postId): array
    {
        // Would need webhook to capture replies
        return [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        // commentId format: "chatId:messageId"
        $parts = explode(':', $commentId);
        $chatId = $parts[0] ?? null;
        $messageId = $parts[1] ?? null;
        
        if (!$chatId || !$messageId) {
            return ['success' => false, 'error' => 'Invalid comment ID format'];
        }
        
        return $this->sendMessage($accessToken, $chatId, $message, [
            'reply_to' => $messageId,
        ]);
    }
}
