<?php

namespace PHPFrarm\Core\SocialMedia;

use PHPFrarm\Core\Logger;

/**
 * Social Platform Factory
 * 
 * Factory class to instantiate social media platform connectors.
 * Supports all major social, video, photo, and messaging platforms.
 * 
 * @package PHPFrarm\Core\SocialMedia
 */
class SocialPlatformFactory
{
    /** @var array<string, SocialPlatformInterface> */
    private static array $platforms = [];
    
    // Platform categories
    const CATEGORY_SOCIAL = 'social';
    const CATEGORY_VIDEO = 'video';
    const CATEGORY_PHOTO = 'photo';
    const CATEGORY_MESSAGING = 'messaging';
    const CATEGORY_PUBLISHING = 'publishing';
    const CATEGORY_COMMERCE = 'commerce';
    const CATEGORY_ADS = 'ads';

    /**
     * Get platform connector by name
     * 
     * @param string $platform Platform name
     * @return SocialPlatformInterface
     * @throws \InvalidArgumentException
     */
    public static function getPlatform(string $platform): SocialPlatformInterface
    {
        $platform = strtolower($platform);
        
        if (isset(self::$platforms[$platform])) {
            return self::$platforms[$platform];
        }
        
        $connector = match($platform) {
            // Major Social Platforms
            'facebook', 'fb' => new Connectors\FacebookConnector(),
            'instagram', 'ig' => new Connectors\InstagramConnector(),
            'twitter', 'x' => new Connectors\TwitterConnector(),
            'linkedin' => new Connectors\LinkedInConnector(),
            'pinterest' => new Connectors\PinterestConnector(),
            'snapchat' => new Connectors\SnapchatConnector(),
            'reddit' => new Connectors\RedditConnector(),
            'tumblr' => new Connectors\TumblrConnector(),
            
            // Video Platforms
            'youtube', 'yt' => new Connectors\YouTubeConnector(),
            'tiktok' => new Connectors\TikTokConnector(),
            'vimeo' => new Connectors\VimeoConnector(),
            'twitch' => new Connectors\TwitchConnector(),
            
            // Photo Platforms
            'flickr' => new Connectors\FlickrConnector(),
            'imgur' => new Connectors\ImgurConnector(),
            
            // Messaging Platforms
            'telegram' => new Connectors\TelegramConnector(),
            'discord' => new Connectors\DiscordConnector(),
            'slack' => new Connectors\SlackConnector(),
            
            // Publishing Platforms
            'medium' => new Connectors\MediumConnector(),
            'wordpress' => new Connectors\WordPressConnector(),
            
            // Regional Platforms (India)
            'sharechat' => new Connectors\ShareChatConnector(),
            
            default => throw new \InvalidArgumentException("Unsupported platform: $platform")
        };
        
        self::$platforms[$platform] = $connector;
        
        return $connector;
    }

    /**
     * Get all available platforms
     */
    public static function getAvailablePlatforms(): array
    {
        return [
            self::CATEGORY_SOCIAL => [
                'facebook' => 'Facebook',
                'instagram' => 'Instagram',
                'twitter' => 'X (Twitter)',
                'linkedin' => 'LinkedIn',
                'pinterest' => 'Pinterest',
                'snapchat' => 'Snapchat',
                'reddit' => 'Reddit',
                'tumblr' => 'Tumblr',
            ],
            self::CATEGORY_VIDEO => [
                'youtube' => 'YouTube',
                'tiktok' => 'TikTok',
                'vimeo' => 'Vimeo',
                'twitch' => 'Twitch',
            ],
            self::CATEGORY_PHOTO => [
                'flickr' => 'Flickr',
                'imgur' => 'Imgur',
            ],
            self::CATEGORY_MESSAGING => [
                'telegram' => 'Telegram',
                'discord' => 'Discord',
                'slack' => 'Slack',
            ],
            self::CATEGORY_PUBLISHING => [
                'medium' => 'Medium',
                'wordpress' => 'WordPress',
            ],
        ];
    }

    /**
     * Get configured platforms
     */
    public static function getConfiguredPlatforms(): array
    {
        $configured = [];
        $allPlatforms = self::getAvailablePlatforms();
        
        foreach ($allPlatforms as $category => $platforms) {
            foreach ($platforms as $key => $name) {
                try {
                    $connector = self::getPlatform($key);
                    if ($connector->isConfigured()) {
                        $configured[$key] = [
                            'name' => $name,
                            'category' => $category,
                            'type' => $connector->getPlatformType(),
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        return $configured;
    }

    /**
     * Get platforms by category
     */
    public static function getPlatformsByCategory(string $category): array
    {
        $platforms = self::getAvailablePlatforms();
        return $platforms[$category] ?? [];
    }

    /**
     * Publish to multiple platforms
     * 
     * @param array $platforms Platform names
     * @param array $tokens Access tokens keyed by platform
     * @param string $content Post content
     * @param array $options Additional options (image_url, video_url, etc.)
     * @return array Results keyed by platform
     */
    public static function publishToMultiple(array $platforms, array $tokens, string $content, array $options = []): array
    {
        $results = [];
        
        foreach ($platforms as $platform) {
            try {
                $connector = self::getPlatform($platform);
                $token = $tokens[$platform] ?? null;
                
                if (!$token) {
                    $results[$platform] = [
                        'success' => false,
                        'error' => 'No access token provided',
                    ];
                    continue;
                }
                
                // Determine content type
                if (!empty($options['video_url'])) {
                    $result = $connector->publishVideo($token, $options['video_url'], $content, $options);
                } elseif (!empty($options['image_url'])) {
                    $result = $connector->publishImage($token, $options['image_url'], $content, $options);
                } else {
                    $result = $connector->publishPost($token, $content, $options);
                }
                
                $results[$platform] = $result;
                
            } catch (\Exception $e) {
                Logger::error("Failed to publish to $platform", [
                    'error' => $e->getMessage(),
                ]);
                $results[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get analytics from multiple platforms
     */
    public static function getMultiPlatformAnalytics(array $platforms, array $tokens, array $dateRange = []): array
    {
        $analytics = [];
        
        foreach ($platforms as $platform) {
            try {
                $connector = self::getPlatform($platform);
                $token = $tokens[$platform] ?? null;
                
                if (!$token) continue;
                
                $analytics[$platform] = $connector->getAccountAnalytics($token, [], $dateRange);
                
            } catch (\Exception $e) {
                Logger::warning("Failed to get analytics from $platform", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $analytics;
    }

    /**
     * Reset cached instances
     */
    public static function reset(): void
    {
        self::$platforms = [];
    }
}
