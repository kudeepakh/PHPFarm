<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth provider credentials and settings for social login
    |
    */

    'providers' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/api/auth/social/google/callback'),
            'scopes' => ['openid', 'email', 'profile'],
            'enabled' => env('GOOGLE_OAUTH_ENABLED', false),
        ],

        'facebook' => [
            'app_id' => env('FACEBOOK_APP_ID'),
            'app_secret' => env('FACEBOOK_APP_SECRET'),
            'redirect_uri' => env('FACEBOOK_REDIRECT_URI', env('APP_URL') . '/api/auth/social/facebook/callback'),
            'scopes' => ['email', 'public_profile'],
            'enabled' => env('FACEBOOK_OAUTH_ENABLED', false),
        ],

        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect_uri' => env('GITHUB_REDIRECT_URI', env('APP_URL') . '/api/auth/social/github/callback'),
            'scopes' => ['read:user', 'user:email'],
            'enabled' => env('GITHUB_OAUTH_ENABLED', false),
        ],

        'apple' => [
            'client_id' => env('APPLE_CLIENT_ID'), // Service ID
            'team_id' => env('APPLE_TEAM_ID'),
            'key_id' => env('APPLE_KEY_ID'),
            'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
            'private_key' => env('APPLE_PRIVATE_KEY'), // Or base64-encoded
            'redirect_uri' => env('APPLE_REDIRECT_URI', env('APP_URL') . '/api/auth/social/apple/callback'),
            'scopes' => ['name', 'email'],
            'enabled' => env('APPLE_OAUTH_ENABLED', false),
            'response_mode' => 'form_post', // Apple uses POST callback
        ],

        'microsoft' => [
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'tenant' => env('MICROSOFT_TENANT', 'common'), // common, organizations, consumers, or tenant-id
            'redirect_uri' => env('MICROSOFT_REDIRECT_URI', env('APP_URL') . '/api/auth/social/microsoft/callback'),
            'scopes' => ['openid', 'profile', 'email', 'User.Read'],
            'enabled' => env('MICROSOFT_OAUTH_ENABLED', false),
        ],

        'linkedin' => [
            'client_id' => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            'redirect_uri' => env('LINKEDIN_REDIRECT_URI', env('APP_URL') . '/api/auth/social/linkedin/callback'),
            'scopes' => ['openid', 'profile', 'email'],
            'enabled' => env('LINKEDIN_OAUTH_ENABLED', false),
        ],

        'twitter' => [
            'client_id' => env('TWITTER_CLIENT_ID'),
            'client_secret' => env('TWITTER_CLIENT_SECRET'),
            'redirect_uri' => env('TWITTER_REDIRECT_URI', env('APP_URL') . '/api/auth/social/twitter/callback'),
            'scopes' => ['tweet.read', 'users.read', 'offline.access'],
            'enabled' => env('TWITTER_OAUTH_ENABLED', false),
            'use_pkce' => true, // Twitter requires PKCE
            'code_challenge_method' => 'S256',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Linking Behavior
    |--------------------------------------------------------------------------
    |
    | How to handle OAuth login when email matches existing account
    |
    | 'auto'   → Automatically link OAuth to existing email account
    | 'prompt' → Require user confirmation to link
    | 'deny'   → Create separate account (not recommended)
    |
    */
    'link_existing_accounts' => env('OAUTH_LINK_ACCOUNTS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    |
    | Auto-verify email if OAuth provider confirms it
    |
    */
    'auto_verify_email' => env('OAUTH_AUTO_VERIFY_EMAIL', true),

    /*
    |--------------------------------------------------------------------------
    | Session State Storage
    |--------------------------------------------------------------------------
    |
    | How to store OAuth state for CSRF protection
    |
    | 'session' → PHP session (default)
    | 'cache'   → Redis/Memcached
    |
    */
    'state_storage' => env('OAUTH_STATE_STORAGE', 'session'),

    /*
    |--------------------------------------------------------------------------
    | State TTL
    |--------------------------------------------------------------------------
    |
    | OAuth state token expiration (in seconds)
    | Default: 10 minutes
    |
    */
    'state_ttl' => env('OAUTH_STATE_TTL', 600),
];
