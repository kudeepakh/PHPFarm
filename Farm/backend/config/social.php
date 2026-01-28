<?php

/**
 * Social Media Platform Configuration
 * 
 * This file contains configuration for all social media platform integrations.
 * 
 * IMPORTANT: All credentials should be stored in environment variables.
 * Never commit actual credentials to version control.
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'rate_limit_buffer' => 0.1, // Keep 10% buffer on rate limits
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
        'timeout' => 30, // seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Facebook / Meta
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'enabled' => env('FACEBOOK_ENABLED', false),
        'app_id' => env('FACEBOOK_APP_ID', ''),
        'app_secret' => env('FACEBOOK_APP_SECRET', ''),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v18.0'),
        'default_page_id' => env('FACEBOOK_DEFAULT_PAGE_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Instagram
    |--------------------------------------------------------------------------
    */
    'instagram' => [
        'enabled' => env('INSTAGRAM_ENABLED', false),
        // Uses Facebook app credentials
        'app_id' => env('FACEBOOK_APP_ID', ''),
        'app_secret' => env('FACEBOOK_APP_SECRET', ''),
        'instagram_account_id' => env('INSTAGRAM_ACCOUNT_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Twitter / X
    |--------------------------------------------------------------------------
    */
    'twitter' => [
        'enabled' => env('TWITTER_ENABLED', false),
        // OAuth 2.0 (User authentication)
        'client_id' => env('TWITTER_CLIENT_ID', ''),
        'client_secret' => env('TWITTER_CLIENT_SECRET', ''),
        // OAuth 1.0a (For media upload)
        'api_key' => env('TWITTER_API_KEY', ''),
        'api_secret' => env('TWITTER_API_SECRET', ''),
        'access_token' => env('TWITTER_ACCESS_TOKEN', ''),
        'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET', ''),
        'bearer_token' => env('TWITTER_BEARER_TOKEN', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | LinkedIn
    |--------------------------------------------------------------------------
    */
    'linkedin' => [
        'enabled' => env('LINKEDIN_ENABLED', false),
        'client_id' => env('LINKEDIN_CLIENT_ID', ''),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET', ''),
        'manage_pages' => env('LINKEDIN_MANAGE_PAGES', false),
        'organization_id' => env('LINKEDIN_ORGANIZATION_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | YouTube
    |--------------------------------------------------------------------------
    */
    'youtube' => [
        'enabled' => env('YOUTUBE_ENABLED', false),
        // Uses Google OAuth
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'api_key' => env('YOUTUBE_API_KEY', ''),
        'default_category_id' => env('YOUTUBE_DEFAULT_CATEGORY', '22'), // People & Blogs
    ],
    
    /*
    |--------------------------------------------------------------------------
    | TikTok
    |--------------------------------------------------------------------------
    */
    'tiktok' => [
        'enabled' => env('TIKTOK_ENABLED', false),
        'client_key' => env('TIKTOK_CLIENT_KEY', ''),
        'client_secret' => env('TIKTOK_CLIENT_SECRET', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Pinterest
    |--------------------------------------------------------------------------
    */
    'pinterest' => [
        'enabled' => env('PINTEREST_ENABLED', false),
        'app_id' => env('PINTEREST_APP_ID', ''),
        'app_secret' => env('PINTEREST_APP_SECRET', ''),
        'default_board_id' => env('PINTEREST_DEFAULT_BOARD_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Telegram
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Discord
    |--------------------------------------------------------------------------
    */
    'discord' => [
        'enabled' => env('DISCORD_ENABLED', false),
        'client_id' => env('DISCORD_CLIENT_ID', ''),
        'client_secret' => env('DISCORD_CLIENT_SECRET', ''),
        'bot_token' => env('DISCORD_BOT_TOKEN', ''),
        'is_bot' => env('DISCORD_IS_BOT', true),
        'permissions' => env('DISCORD_PERMISSIONS', '2147483647'), // Administrator
        'default_guild_id' => env('DISCORD_DEFAULT_GUILD_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'enabled' => env('SLACK_ENABLED', false),
        'client_id' => env('SLACK_CLIENT_ID', ''),
        'client_secret' => env('SLACK_CLIENT_SECRET', ''),
        'signing_secret' => env('SLACK_SIGNING_SECRET', ''),
        'bot_token' => env('SLACK_BOT_TOKEN', ''),
        'user_scopes' => [],
        'default_channel' => env('SLACK_DEFAULT_CHANNEL', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Reddit
    |--------------------------------------------------------------------------
    */
    'reddit' => [
        'enabled' => env('REDDIT_ENABLED', false),
        'client_id' => env('REDDIT_CLIENT_ID', ''),
        'client_secret' => env('REDDIT_CLIENT_SECRET', ''),
        'user_agent' => env('REDDIT_USER_AGENT', 'PHPFrarm/1.0'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Snapchat
    |--------------------------------------------------------------------------
    */
    'snapchat' => [
        'enabled' => env('SNAPCHAT_ENABLED', false),
        'client_id' => env('SNAPCHAT_CLIENT_ID', ''),
        'client_secret' => env('SNAPCHAT_CLIENT_SECRET', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Tumblr
    |--------------------------------------------------------------------------
    */
    'tumblr' => [
        'enabled' => env('TUMBLR_ENABLED', false),
        'consumer_key' => env('TUMBLR_CONSUMER_KEY', ''),
        'consumer_secret' => env('TUMBLR_CONSUMER_SECRET', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Video Platforms
    |--------------------------------------------------------------------------
    */
    
    'vimeo' => [
        'enabled' => env('VIMEO_ENABLED', false),
        'client_id' => env('VIMEO_CLIENT_ID', ''),
        'client_secret' => env('VIMEO_CLIENT_SECRET', ''),
        'access_token' => env('VIMEO_ACCESS_TOKEN', ''),
    ],
    
    'twitch' => [
        'enabled' => env('TWITCH_ENABLED', false),
        'client_id' => env('TWITCH_CLIENT_ID', ''),
        'client_secret' => env('TWITCH_CLIENT_SECRET', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Photo Platforms
    |--------------------------------------------------------------------------
    */
    
    'flickr' => [
        'enabled' => env('FLICKR_ENABLED', false),
        'api_key' => env('FLICKR_API_KEY', ''),
        'api_secret' => env('FLICKR_API_SECRET', ''),
    ],
    
    'imgur' => [
        'enabled' => env('IMGUR_ENABLED', false),
        'client_id' => env('IMGUR_CLIENT_ID', ''),
        'client_secret' => env('IMGUR_CLIENT_SECRET', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Publishing Platforms
    |--------------------------------------------------------------------------
    */
    
    'medium' => [
        'enabled' => env('MEDIUM_ENABLED', false),
        'client_id' => env('MEDIUM_CLIENT_ID', ''),
        'client_secret' => env('MEDIUM_CLIENT_SECRET', ''),
        'integration_token' => env('MEDIUM_INTEGRATION_TOKEN', ''),
    ],
    
    'wordpress' => [
        'enabled' => env('WORDPRESS_ENABLED', false),
        'site_url' => env('WORDPRESS_SITE_URL', ''),
        'username' => env('WORDPRESS_USERNAME', ''),
        'application_password' => env('WORDPRESS_APP_PASSWORD', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | India-Specific Platforms
    |--------------------------------------------------------------------------
    */
    
    'sharechat' => [
        'enabled' => env('SHARECHAT_ENABLED', false),
        'api_key' => env('SHARECHAT_API_KEY', ''),
        'api_secret' => env('SHARECHAT_API_SECRET', ''),
    ],
    
    'moj' => [
        'enabled' => env('MOJ_ENABLED', false),
        'api_key' => env('MOJ_API_KEY', ''),
        'api_secret' => env('MOJ_API_SECRET', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Advertising Platforms
    |--------------------------------------------------------------------------
    */
    
    'meta_ads' => [
        'enabled' => env('META_ADS_ENABLED', false),
        // Uses Facebook app credentials
        'app_id' => env('FACEBOOK_APP_ID', ''),
        'app_secret' => env('FACEBOOK_APP_SECRET', ''),
        'ad_account_id' => env('META_AD_ACCOUNT_ID', ''),
    ],
    
    'google_ads' => [
        'enabled' => env('GOOGLE_ADS_ENABLED', false),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN', ''),
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID', ''),
    ],
    
    'linkedin_ads' => [
        'enabled' => env('LINKEDIN_ADS_ENABLED', false),
        'client_id' => env('LINKEDIN_CLIENT_ID', ''),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET', ''),
        'ad_account_id' => env('LINKEDIN_AD_ACCOUNT_ID', ''),
    ],
    
    'tiktok_ads' => [
        'enabled' => env('TIKTOK_ADS_ENABLED', false),
        'app_id' => env('TIKTOK_ADS_APP_ID', ''),
        'app_secret' => env('TIKTOK_ADS_APP_SECRET', ''),
        'advertiser_id' => env('TIKTOK_ADVERTISER_ID', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'base_url' => env('SOCIAL_WEBHOOK_BASE_URL', ''),
        'secret' => env('SOCIAL_WEBHOOK_SECRET', ''),
        
        // Platform-specific webhook endpoints
        'endpoints' => [
            'facebook' => '/webhooks/facebook',
            'instagram' => '/webhooks/instagram',
            'twitter' => '/webhooks/twitter',
            'telegram' => '/webhooks/telegram',
            'discord' => '/webhooks/discord',
            'slack' => '/webhooks/slack',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'facebook' => [
            'calls_per_hour' => 200,
            'calls_per_day' => 4800,
        ],
        'instagram' => [
            'calls_per_hour' => 200,
            'posts_per_day' => 25,
        ],
        'twitter' => [
            'tweets_per_day' => 2400,
            'dm_per_day' => 1000,
        ],
        'linkedin' => [
            'posts_per_day' => 100,
        ],
        'youtube' => [
            'quota_per_day' => 10000, // units
        ],
        'tiktok' => [
            'videos_per_day' => 50,
        ],
        'pinterest' => [
            'pins_per_hour' => 100,
        ],
    ],
    
];
