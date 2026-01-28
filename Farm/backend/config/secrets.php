<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Secrets Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for external secret storage backends (HashiCorp Vault, 
    | AWS Secrets Manager, Azure Key Vault) and local fallback.
    |
    */

    'backend' => env('SECRET_BACKEND', 'local'), // local, vault, aws, azure

    /*
    |--------------------------------------------------------------------------
    | HashiCorp Vault Configuration
    |--------------------------------------------------------------------------
    */
    'vault' => [
        'url' => env('VAULT_URL', 'http://localhost:8200'),
        'token' => env('VAULT_TOKEN', ''),
        'path' => env('VAULT_PATH', 'secret'),
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Secrets Manager Configuration
    |--------------------------------------------------------------------------
    */
    'aws' => [
        'region' => env('AWS_REGION', 'us-east-1'),
        'access_key' => env('AWS_ACCESS_KEY_ID', ''),
        'secret_key' => env('AWS_SECRET_ACCESS_KEY', ''),
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Azure Key Vault Configuration
    |--------------------------------------------------------------------------
    */
    'azure' => [
        'vault_url' => env('AZURE_VAULT_URL', ''),
        'tenant_id' => env('AZURE_TENANT_ID', ''),
        'client_id' => env('AZURE_CLIENT_ID', ''),
        'client_secret' => env('AZURE_CLIENT_SECRET', ''),
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Secrets (Development Only)
    |--------------------------------------------------------------------------
    |
    | For development/testing only. Never use in production!
    |
    */
    'local' => [
        'enabled' => env('APP_ENV') !== 'production',
        'fallback_to_env' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('SECRET_CACHE_ENABLED', true),
        'ttl' => (int) env('SECRET_CACHE_TTL', 300), // 5 minutes
    ],
];
