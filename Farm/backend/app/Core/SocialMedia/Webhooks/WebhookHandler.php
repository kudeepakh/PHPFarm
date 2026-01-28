<?php

namespace PHPFrarm\Core\SocialMedia\Webhooks;

use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Config;
use MongoDB\Client as MongoClient;

/**
 * Social Webhook Handler
 * 
 * Centralized webhook handler for all social media platforms.
 * Validates, processes, and routes incoming webhook events.
 * 
 * Features:
 * - Platform-specific verification
 * - Event routing and dispatching
 * - Secure signature validation
 * - Event logging to MongoDB
 * 
 * @package PHPFrarm\Core\SocialMedia\Webhooks
 */
class WebhookHandler
{
    private array $config;
    private ?MongoClient $mongo = null;
    
    /**
     * Registered event handlers
     */
    private array $handlers = [];
    
    /**
     * Platform-specific verifiers
     */
    private array $verifiers = [
        'facebook' => 'verifyFacebookWebhook',
        'instagram' => 'verifyFacebookWebhook', // Same as Facebook
        'twitter' => 'verifyTwitterWebhook',
        'linkedin' => 'verifyLinkedInWebhook',
        'tiktok' => 'verifyTikTokWebhook',
        'telegram' => 'verifyTelegramWebhook',
        'discord' => 'verifyDiscordWebhook',
        'slack' => 'verifySlackWebhook',
        'youtube' => 'verifyGoogleWebhook',
    ];
    
    public function __construct()
    {
        $this->config = Config::get('social.webhooks', []);
    }
    
    /**
     * Handle incoming webhook
     */
    public function handle(string $platform, array $headers, string $rawBody): array
    {
        $platform = strtolower($platform);
        
        // Log incoming webhook
        $this->logWebhook($platform, $headers, $rawBody);
        
        // Verify webhook signature
        if (!$this->verify($platform, $headers, $rawBody)) {
            Logger::warning('Webhook verification failed', [
                'platform' => $platform,
            ]);
            
            return [
                'success' => false,
                'error' => 'Verification failed',
            ];
        }
        
        // Parse payload
        $payload = json_decode($rawBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try form-encoded
            parse_str($rawBody, $payload);
        }
        
        // Route to handlers
        $events = $this->extractEvents($platform, $payload);
        
        $results = [];
        foreach ($events as $event) {
            $results[] = $this->dispatch($platform, $event['type'], $event['data']);
        }
        
        return [
            'success' => true,
            'events_processed' => count($events),
            'results' => $results,
        ];
    }
    
    /**
     * Verify webhook request
     */
    public function verify(string $platform, array $headers, string $rawBody): bool
    {
        $verifier = $this->verifiers[$platform] ?? null;
        
        if (!$verifier || !method_exists($this, $verifier)) {
            // If no specific verifier, use shared secret
            return $this->verifyGeneric($headers, $rawBody);
        }
        
        return $this->{$verifier}($headers, $rawBody);
    }
    
    /**
     * Handle challenge/verification requests
     */
    public function handleChallenge(string $platform, array $params): ?string
    {
        switch ($platform) {
            case 'facebook':
            case 'instagram':
                return $this->handleFacebookChallenge($params);
                
            case 'twitter':
                return $this->handleTwitterChallenge($params);
                
            case 'slack':
                return $this->handleSlackChallenge($params);
                
            case 'telegram':
                return null; // Telegram doesn't use challenge
                
            default:
                return null;
        }
    }
    
    /**
     * Register event handler
     */
    public function on(string $platform, string $eventType, callable $handler): void
    {
        $key = "{$platform}:{$eventType}";
        
        if (!isset($this->handlers[$key])) {
            $this->handlers[$key] = [];
        }
        
        $this->handlers[$key][] = $handler;
    }
    
    /**
     * Dispatch event to handlers
     */
    private function dispatch(string $platform, string $eventType, array $data): array
    {
        $key = "{$platform}:{$eventType}";
        $wildcardKey = "{$platform}:*";
        
        $handlers = array_merge(
            $this->handlers[$key] ?? [],
            $this->handlers[$wildcardKey] ?? []
        );
        
        $results = [];
        
        foreach ($handlers as $handler) {
            try {
                $results[] = $handler($eventType, $data, $platform);
            } catch (\Exception $e) {
                Logger::error('Webhook handler error', [
                    'platform' => $platform,
                    'event' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Platform-Specific Verifiers
    |--------------------------------------------------------------------------
    */
    
    /**
     * Verify Facebook/Instagram webhook
     */
    private function verifyFacebookWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Hub-Signature-256'] ?? $headers['x-hub-signature-256'] ?? null;
        
        if (!$signature) {
            return false;
        }
        
        $secret = Config::get('social.facebook.webhook_secret');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Handle Facebook verification challenge
     */
    private function handleFacebookChallenge(array $params): ?string
    {
        $mode = $params['hub_mode'] ?? null;
        $token = $params['hub_verify_token'] ?? null;
        $challenge = $params['hub_challenge'] ?? null;
        
        if ($mode !== 'subscribe') {
            return null;
        }
        
        $verifyToken = Config::get('social.facebook.verify_token');
        
        if ($token === $verifyToken) {
            return $challenge;
        }
        
        return null;
    }
    
    /**
     * Verify Twitter webhook
     */
    private function verifyTwitterWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Twitter-Webhooks-Signature'] ?? $headers['x-twitter-webhooks-signature'] ?? null;
        
        if (!$signature) {
            return false;
        }
        
        $secret = Config::get('social.twitter.api_secret');
        $expectedSignature = 'sha256=' . base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Handle Twitter CRC challenge
     */
    private function handleTwitterChallenge(array $params): ?string
    {
        $crcToken = $params['crc_token'] ?? null;
        
        if (!$crcToken) {
            return null;
        }
        
        $secret = Config::get('social.twitter.api_secret');
        $responseToken = base64_encode(hash_hmac('sha256', $crcToken, $secret, true));
        
        return json_encode(['response_token' => "sha256={$responseToken}"]);
    }
    
    /**
     * Verify LinkedIn webhook
     */
    private function verifyLinkedInWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-LI-Signature'] ?? $headers['x-li-signature'] ?? null;
        
        if (!$signature) {
            return false;
        }
        
        $secret = Config::get('social.linkedin.client_secret');
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Verify TikTok webhook
     */
    private function verifyTikTokWebhook(array $headers, string $rawBody): bool
    {
        $timestamp = $headers['X-TikTok-Timestamp'] ?? $headers['x-tiktok-timestamp'] ?? null;
        $signature = $headers['X-TikTok-Signature'] ?? $headers['x-tiktok-signature'] ?? null;
        
        if (!$timestamp || !$signature) {
            return false;
        }
        
        // Check timestamp freshness (5 minute window)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }
        
        $secret = Config::get('social.tiktok.client_secret');
        $payload = $timestamp . $rawBody;
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Verify Telegram webhook
     */
    private function verifyTelegramWebhook(array $headers, string $rawBody): bool
    {
        // Telegram uses secret_token in URL or header
        $secretToken = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? 
                       $headers['x-telegram-bot-api-secret-token'] ?? null;
        
        if (!$secretToken) {
            return true; // Legacy mode without secret token
        }
        
        $expectedToken = Config::get('social.telegram.webhook_secret');
        
        return hash_equals($expectedToken, $secretToken);
    }
    
    /**
     * Verify Discord webhook
     */
    private function verifyDiscordWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Signature-Ed25519'] ?? $headers['x-signature-ed25519'] ?? null;
        $timestamp = $headers['X-Signature-Timestamp'] ?? $headers['x-signature-timestamp'] ?? null;
        
        if (!$signature || !$timestamp) {
            return false;
        }
        
        $publicKey = Config::get('social.discord.public_key');
        
        // Discord uses Ed25519 signatures
        $message = $timestamp . $rawBody;
        
        // This requires sodium extension
        if (function_exists('sodium_crypto_sign_verify_detached')) {
            return sodium_crypto_sign_verify_detached(
                hex2bin($signature),
                $message,
                hex2bin($publicKey)
            );
        }
        
        return false;
    }
    
    /**
     * Verify Slack webhook
     */
    private function verifySlackWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Slack-Signature'] ?? $headers['x-slack-signature'] ?? null;
        $timestamp = $headers['X-Slack-Request-Timestamp'] ?? $headers['x-slack-request-timestamp'] ?? null;
        
        if (!$signature || !$timestamp) {
            return false;
        }
        
        // Check timestamp (5 minute window)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }
        
        $secret = Config::get('social.slack.signing_secret');
        $baseString = "v0:{$timestamp}:{$rawBody}";
        $expectedSignature = 'v0=' . hash_hmac('sha256', $baseString, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Handle Slack challenge
     */
    private function handleSlackChallenge(array $params): ?string
    {
        $challenge = $params['challenge'] ?? null;
        
        if ($challenge) {
            return $challenge;
        }
        
        return null;
    }
    
    /**
     * Verify Google/YouTube webhook (PubSubHubbub)
     */
    private function verifyGoogleWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Hub-Signature'] ?? $headers['x-hub-signature'] ?? null;
        
        if (!$signature) {
            return true; // Google may not always include signature
        }
        
        $secret = Config::get('social.youtube.webhook_secret');
        
        if (strpos($signature, 'sha1=') === 0) {
            $hash = substr($signature, 5);
            $expected = hash_hmac('sha1', $rawBody, $secret);
            return hash_equals($expected, $hash);
        }
        
        return false;
    }
    
    /**
     * Generic verification using shared secret
     */
    private function verifyGeneric(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Webhook-Signature'] ?? 
                     $headers['x-webhook-signature'] ?? 
                     $headers['X-Signature'] ?? 
                     $headers['x-signature'] ?? null;
        
        if (!$signature) {
            return true; // Allow if no signature mechanism
        }
        
        $secret = $this->config['secret'] ?? Config::get('app.key');
        $expected = hash_hmac('sha256', $rawBody, $secret);
        
        return hash_equals($expected, $signature);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Event Extraction
    |--------------------------------------------------------------------------
    */
    
    /**
     * Extract events from platform payload
     */
    private function extractEvents(string $platform, array $payload): array
    {
        switch ($platform) {
            case 'facebook':
            case 'instagram':
                return $this->extractFacebookEvents($payload);
                
            case 'twitter':
                return $this->extractTwitterEvents($payload);
                
            case 'telegram':
                return $this->extractTelegramEvents($payload);
                
            case 'discord':
                return $this->extractDiscordEvents($payload);
                
            case 'slack':
                return $this->extractSlackEvents($payload);
                
            case 'youtube':
                return $this->extractYouTubeEvents($payload);
                
            default:
                return [[
                    'type' => 'unknown',
                    'data' => $payload,
                ]];
        }
    }
    
    /**
     * Extract Facebook/Instagram events
     */
    private function extractFacebookEvents(array $payload): array
    {
        $events = [];
        
        $object = $payload['object'] ?? 'unknown';
        
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $events[] = [
                    'type' => "{$object}.{$change['field']}",
                    'data' => [
                        'id' => $entry['id'],
                        'time' => $entry['time'] ?? time(),
                        'field' => $change['field'],
                        'value' => $change['value'],
                    ],
                ];
            }
            
            // Messaging events
            foreach ($entry['messaging'] ?? [] as $message) {
                $events[] = [
                    'type' => "{$object}.messaging",
                    'data' => $message,
                ];
            }
        }
        
        return $events ?: [[
            'type' => $object,
            'data' => $payload,
        ]];
    }
    
    /**
     * Extract Twitter events
     */
    private function extractTwitterEvents(array $payload): array
    {
        $events = [];
        
        // Tweet create events
        if (!empty($payload['tweet_create_events'])) {
            foreach ($payload['tweet_create_events'] as $tweet) {
                $events[] = [
                    'type' => 'tweet.create',
                    'data' => $tweet,
                ];
            }
        }
        
        // Direct message events
        if (!empty($payload['direct_message_events'])) {
            foreach ($payload['direct_message_events'] as $dm) {
                $events[] = [
                    'type' => 'direct_message',
                    'data' => $dm,
                ];
            }
        }
        
        // Follow events
        if (!empty($payload['follow_events'])) {
            foreach ($payload['follow_events'] as $follow) {
                $events[] = [
                    'type' => 'follow',
                    'data' => $follow,
                ];
            }
        }
        
        return $events ?: [[
            'type' => 'unknown',
            'data' => $payload,
        ]];
    }
    
    /**
     * Extract Telegram events
     */
    private function extractTelegramEvents(array $payload): array
    {
        $updateId = $payload['update_id'] ?? null;
        
        if (isset($payload['message'])) {
            return [[
                'type' => 'message',
                'data' => array_merge($payload['message'], ['update_id' => $updateId]),
            ]];
        }
        
        if (isset($payload['callback_query'])) {
            return [[
                'type' => 'callback_query',
                'data' => array_merge($payload['callback_query'], ['update_id' => $updateId]),
            ]];
        }
        
        if (isset($payload['channel_post'])) {
            return [[
                'type' => 'channel_post',
                'data' => array_merge($payload['channel_post'], ['update_id' => $updateId]),
            ]];
        }
        
        return [[
            'type' => 'update',
            'data' => $payload,
        ]];
    }
    
    /**
     * Extract Discord events
     */
    private function extractDiscordEvents(array $payload): array
    {
        $type = $payload['type'] ?? 0;
        
        // Interaction types
        $typeNames = [
            1 => 'ping',
            2 => 'application_command',
            3 => 'message_component',
            4 => 'application_command_autocomplete',
            5 => 'modal_submit',
        ];
        
        return [[
            'type' => $typeNames[$type] ?? 'unknown',
            'data' => $payload,
        ]];
    }
    
    /**
     * Extract Slack events
     */
    private function extractSlackEvents(array $payload): array
    {
        // URL verification
        if (($payload['type'] ?? '') === 'url_verification') {
            return [[
                'type' => 'url_verification',
                'data' => $payload,
            ]];
        }
        
        // Event callback
        if (($payload['type'] ?? '') === 'event_callback') {
            $event = $payload['event'] ?? [];
            
            return [[
                'type' => $event['type'] ?? 'unknown',
                'data' => $event,
            ]];
        }
        
        return [[
            'type' => $payload['type'] ?? 'unknown',
            'data' => $payload,
        ]];
    }
    
    /**
     * Extract YouTube events (PubSubHubbub)
     */
    private function extractYouTubeEvents(array $payload): array
    {
        // YouTube webhooks come as Atom XML, this assumes pre-parsed
        if (!empty($payload['feed']['entry'])) {
            $entry = $payload['feed']['entry'];
            
            return [[
                'type' => 'video.published',
                'data' => [
                    'video_id' => $entry['yt:videoId'] ?? null,
                    'channel_id' => $entry['yt:channelId'] ?? null,
                    'title' => $entry['title'] ?? null,
                    'published' => $entry['published'] ?? null,
                    'updated' => $entry['updated'] ?? null,
                ],
            ]];
        }
        
        return [[
            'type' => 'unknown',
            'data' => $payload,
        ]];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    
    /**
     * Log webhook to MongoDB
     */
    private function logWebhook(string $platform, array $headers, string $rawBody): void
    {
        try {
            $mongo = $this->getMongo();
            
            if (!$mongo) {
                return;
            }
            
            $collection = $mongo->selectCollection(
                Config::get('database.mongo.database', 'phpfrarm'),
                'social_webhooks'
            );
            
            $collection->insertOne([
                'platform' => $platform,
                'headers' => $this->sanitizeHeaders($headers),
                'body_hash' => hash('sha256', $rawBody),
                'body_size' => strlen($rawBody),
                'timestamp' => new \MongoDB\BSON\UTCDateTime(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to log webhook', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get MongoDB client
     */
    private function getMongo(): ?MongoClient
    {
        if ($this->mongo === null) {
            try {
                $uri = Config::get('database.mongo.uri', 'mongodb://localhost:27017');
                $this->mongo = new MongoClient($uri);
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return $this->mongo;
    }
    
    /**
     * Sanitize headers for logging
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveKeys = ['authorization', 'x-api-key', 'cookie'];
        
        $sanitized = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            
            if (in_array($lowerKey, $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}
