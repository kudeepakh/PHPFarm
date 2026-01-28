<?php

namespace PHPFrarm\Core\Auth\OAuth;

/**
 * OAuth Factory
 * 
 * Factory class to instantiate OAuth providers by name.
 * Supports Google, Facebook, GitHub with easy extensibility.
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class OAuthFactory
{
    private static array $providers = [];

    /**
     * Get OAuth provider by name
     * 
     * @param string $provider Provider name (google, facebook, github, apple, microsoft, linkedin, twitter)
     * @return OAuthProviderInterface
     * @throws \InvalidArgumentException
     */
    public static function getProvider(string $provider): OAuthProviderInterface
    {
        $provider = strtolower($provider);

        // Return cached instance if exists
        if (isset(self::$providers[$provider])) {
            return self::$providers[$provider];
        }

        // Instantiate new provider
        $instance = match ($provider) {
            'google' => new GoogleOAuthProvider(),
            'facebook' => new FacebookOAuthProvider(),
            'github' => new GithubOAuthProvider(),
            'apple' => new AppleOAuthProvider(),
            'microsoft', 'azure', 'azuread' => new MicrosoftOAuthProvider(),
            'linkedin' => new LinkedInOAuthProvider(),
            'twitter', 'x' => new TwitterOAuthProvider(),
            default => throw new \InvalidArgumentException("Unknown OAuth provider: $provider")
        };

        // Cache instance
        self::$providers[$provider] = $instance;

        return $instance;
    }

    /**
     * Get all configured providers
     * 
     * @return array ['google' => GoogleOAuthProvider, ...]
     */
    public static function getConfiguredProviders(): array
    {
        $providers = [];

        foreach (self::getAvailableProviders() as $name) {
            try {
                $provider = self::getProvider($name);
                if ($provider->isConfigured()) {
                    $providers[$name] = $provider;
                }
            } catch (\Exception $e) {
                // Skip unconfigured providers
            }
        }

        return $providers;
    }

    /**
     * Check if a provider is configured
     * 
     * @param string $provider Provider name
     * @return bool
     */
    public static function isProviderConfigured(string $provider): bool
    {
        try {
            $instance = self::getProvider($provider);
            return $instance->isConfigured();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all available provider names
     * 
     * @return array All supported OAuth providers
     */
    public static function getAvailableProviders(): array
    {
        return ['google', 'facebook', 'github', 'apple', 'microsoft', 'linkedin', 'twitter'];
    }

    /**
     * Get provider status summary
     * 
     * @return array
     */
    public static function getProvidersStatus(): array
    {
        $status = [];

        foreach (self::getAvailableProviders() as $name) {
            try {
                $provider = self::getProvider($name);
                $status[$name] = [
                    'configured' => $provider->isConfigured(),
                    'default_scopes' => $provider->getDefaultScopes()
                ];
            } catch (\Exception $e) {
                $status[$name] = [
                    'configured' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $status;
    }

    /**
     * Reset cached providers (useful for testing)
     */
    public static function reset(): void
    {
        self::$providers = [];
    }
}
