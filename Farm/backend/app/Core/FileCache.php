<?php

namespace PHPFrarm\Core;

/**
 * File-based cache for routes and modules
 * Significantly faster than Redis for local development
 */
class FileCache
{
    private static string $cacheDir;
    private static bool $initialized = false;

    /**
     * Initialize cache directory
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$cacheDir = __DIR__ . '/../../logs/cache';
        
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }

        self::$initialized = true;
    }

    /**
     * Get cache file path
     */
    private static function getCacheFile(string $key): string
    {
        self::init();
        return self::$cacheDir . '/' . md5($key) . '.php';
    }

    /**
     * Check if cache exists and is valid
     */
    public static function has(string $key): bool
    {
        $file = self::getCacheFile($key);
        return file_exists($file);
    }

    /**
     * Get data from cache
     */
    public static function get(string $key): mixed
    {
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }

        try {
            return include $file;
        } catch (\Exception $e) {
            Logger::warning('Failed to load cache file', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Save data to cache
     */
    public static function put(string $key, mixed $data): bool
    {
        $file = self::getCacheFile($key);
        
        try {
            $content = "<?php\n\n// Cache generated at " . date('Y-m-d H:i:s') . "\n\nreturn " . var_export($data, true) . ";\n";
            
            $result = file_put_contents($file, $content, LOCK_EX);
            
            if ($result === false) {
                Logger::error('Failed to write cache file', ['key' => $key]);
                return false;
            }

            // Set opcache if available
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to save cache file', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clear specific cache
     */
    public static function forget(string $key): bool
    {
        $file = self::getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Clear all cache files
     */
    public static function flush(): bool
    {
        self::init();
        
        $files = glob(self::$cacheDir . '/*.php');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Get cache age in seconds
     */
    public static function age(string $key): ?int
    {
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }

        return time() - filemtime($file);
    }
}
