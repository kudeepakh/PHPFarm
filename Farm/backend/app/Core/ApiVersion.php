<?php

namespace PHPFrarm\Core;

/**
 * API Versioning Handler
 * 
 * Manages API version resolution, backward compatibility, and deprecation
 * 
 * Features:
 * - Header-based versioning (Accept-Version: v1)
 * - URL prefix versioning (/v1/users, /v2/users)
 * - Default version fallback
 * - Version deprecation warnings
 * - Breaking change detection
 */
class ApiVersion
{
    private static string $currentVersion = 'v1';
    private static string $defaultVersion = 'v1';
    private static array $supportedVersions = ['v1'];
    private static array $deprecatedVersions = [];
    
    /**
     * Extract version from request
     * Priority: URL prefix > Accept-Version header > Default
     */
    public static function resolveVersion(array $request): string
    {
        // 1. Check URL prefix (e.g., /v2/users)
        $path = $request['path'];
        if (preg_match('#^/v(\d+)/#', $path, $matches)) {
            $version = 'v' . $matches[1];
            if (self::isSupported($version)) {
                self::$currentVersion = $version;
                return $version;
            }
        }
        
        // 2. Check Accept-Version header
        $acceptVersion = $request['headers']['Accept-Version'] ?? 
                        $request['headers']['accept-version'] ?? null;
        
        if ($acceptVersion && self::isSupported($acceptVersion)) {
            self::$currentVersion = $acceptVersion;
            return $acceptVersion;
        }
        
        // 3. Use default version
        self::$currentVersion = self::$defaultVersion;
        return self::$defaultVersion;
    }
    
    /**
     * Strip version prefix from path
     * Handles both /v1/... and /api/v1/... formats
     */
    public static function stripVersionPrefix(string $path): string
    {
        // Handle /api/v1/... format (strip both /api and /v1)
        if (preg_match('#^/api/v\d+/#', $path)) {
            return preg_replace('#^/api/v\d+/#', '/', $path);
        }
        
        // Handle /v1/... format (strip just /v1)
        return preg_replace('#^/v\d+/#', '/', $path);
    }
    
    /**
     * Check if version is supported
     */
    public static function isSupported(string $version): bool
    {
        return in_array($version, self::$supportedVersions);
    }
    
    /**
     * Check if version is deprecated
     */
    public static function isDeprecated(string $version): bool
    {
        return array_key_exists($version, self::$deprecatedVersions);
    }
    
    /**
     * Get current version
     */
    public static function current(): string
    {
        return self::$currentVersion;
    }
    
    /**
     * Get latest version
     */
    public static function latest(): string
    {
        return end(self::$supportedVersions);
    }
    
    /**
     * Register a new API version
     */
    public static function registerVersion(string $version): void
    {
        if (!in_array($version, self::$supportedVersions)) {
            self::$supportedVersions[] = $version;
            Logger::info("API version registered", ['version' => $version]);
        }
    }
    
    /**
     * Deprecate an API version
     */
    public static function deprecateVersion(string $version, string $sunsetDate = ''): void
    {
        $existing = self::$deprecatedVersions[$version] ?? null;
        if ($existing === null || $sunsetDate !== '') {
            self::$deprecatedVersions[$version] = $sunsetDate;
        }

        Logger::warning("API version deprecated", [
            'version' => $version,
            'sunset_date' => $sunsetDate
        ]);
    }
    
    /**
     * Add deprecation warning to response headers
     */
    public static function addDeprecationHeaders(string $version): array
    {
        $headers = [
            'X-API-Version' => $version,
            'X-API-Latest-Version' => self::latest()
        ];
        
        if (self::isDeprecated($version)) {
            $headers['X-API-Deprecated'] = 'true';
            $headers['X-API-Sunset-Date'] = self::$deprecatedVersions[$version]
                ?: ($_ENV['API_' . strtoupper($version) . '_SUNSET'] ?? 'TBD');
            $headers['Warning'] = '299 - "This API version is deprecated. Please migrate to ' . self::latest() . '"';
        }
        
        return $headers;
    }
    
    /**
     * Set supported versions
     */
    public static function setSupportedVersions(array $versions): void
    {
        self::$supportedVersions = $versions;
        self::$defaultVersion = $versions[0] ?? 'v1';
    }
    
    /**
     * Get all supported versions
     */
    public static function getSupportedVersions(): array
    {
        return self::$supportedVersions;
    }
    
    /**
     * Version-specific route registration
     */
    public static function route(string $version, callable $callback): void
    {
        if (self::current() === $version) {
            $callback();
        }
    }
    
    /**
     * Check if feature is available in current version
     */
    public static function supports(string $feature, ?string $version = null): bool
    {
        $version = $version ?? self::$currentVersion;
        
        // Feature availability matrix
        $features = [
            'v1' => ['basic_auth', 'user_crud', 'otp'],
            'v2' => ['basic_auth', 'user_crud', 'otp', 'rbac', 'websockets'],
        ];
        
        return in_array($feature, $features[$version] ?? []);
    }
}
