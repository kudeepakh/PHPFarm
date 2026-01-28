<?php
/**
 * PHPFrarm Framework - Storage Facade
 * 
 * Static facade for easy access to storage operations.
 * Provides a clean API for file storage across multiple providers.
 */

namespace PHPFrarm\Core\Storage;

use PHPFrarm\Core\Storage\Contracts\StorageDriverInterface;

class Storage
{
    /**
     * Get the default disk
     */
    public static function disk(?string $name = null): StorageDriverInterface
    {
        return StorageManager::getInstance()->disk($name);
    }

    /**
     * Get a category storage wrapper
     */
    public static function category(string $name): CategoryStorage
    {
        return StorageManager::getInstance()->category($name);
    }

    /**
     * Shortcut for media category
     */
    public static function media(): CategoryStorage
    {
        return static::category('media');
    }

    /**
     * Shortcut for documents category
     */
    public static function documents(): CategoryStorage
    {
        return static::category('documents');
    }

    /**
     * Shortcut for avatars category
     */
    public static function avatars(): CategoryStorage
    {
        return static::category('avatars');
    }

    /**
     * Shortcut for backups category
     */
    public static function backups(): CategoryStorage
    {
        return static::category('backups');
    }

    /**
     * Shortcut for temp category
     */
    public static function temp(): CategoryStorage
    {
        return static::category('temp');
    }

    /**
     * Shortcut for exports category
     */
    public static function exports(): CategoryStorage
    {
        return static::category('exports');
    }

    /**
     * Store a file (shortcut for default disk)
     */
    public static function put(string $path, $contents, array $options = []): bool
    {
        return static::disk()->put($path, $contents, $options);
    }

    /**
     * Get file contents (shortcut for default disk)
     */
    public static function get(string $path): ?string
    {
        return static::disk()->get($path);
    }

    /**
     * Delete a file (shortcut for default disk)
     */
    public static function delete(string $path): bool
    {
        return static::disk()->delete($path);
    }

    /**
     * Check if file exists (shortcut for default disk)
     */
    public static function exists(string $path): bool
    {
        return static::disk()->exists($path);
    }

    /**
     * Get a temporary download URL
     */
    public static function temporaryUrl(string $path, int $expiration = 3600): string
    {
        return static::disk()->temporaryUrl($path, $expiration);
    }

    /**
     * Get a temporary upload URL
     */
    public static function temporaryUploadUrl(string $path, int $expiration = 900, array $options = []): array
    {
        return static::disk()->temporaryUploadUrl($path, $expiration, $options);
    }

    /**
     * List files in a directory
     */
    public static function listContents(string $directory = '', bool $recursive = false): array
    {
        return static::disk()->listContents($directory, $recursive);
    }

    /**
     * Get file metadata
     */
    public static function metadata(string $path): array
    {
        return static::disk()->metadata($path);
    }

    /**
     * Get file size
     */
    public static function size(string $path): int
    {
        return static::disk()->size($path);
    }

    /**
     * Get file MIME type
     */
    public static function mimeType(string $path): ?string
    {
        return static::disk()->mimeType($path);
    }

    /**
     * Copy a file
     */
    public static function copy(string $from, string $to): bool
    {
        return static::disk()->copy($from, $to);
    }

    /**
     * Move a file
     */
    public static function move(string $from, string $to): bool
    {
        return static::disk()->move($from, $to);
    }

    /**
     * Get user storage quota
     */
    public static function getUserQuota(string $userId): array
    {
        return StorageManager::getInstance()->getUserQuota($userId);
    }

    /**
     * Get all configured disks
     */
    public static function getConfiguredDisks(): array
    {
        return StorageManager::getInstance()->getConfiguredDisks();
    }

    /**
     * Get all configured categories
     */
    public static function getCategories(): array
    {
        return StorageManager::getInstance()->getCategories();
    }

    /**
     * Validate a file against category rules
     */
    public static function validateFile(string $category, array $file): array
    {
        return StorageManager::getInstance()->validateFile($category, $file);
    }
}
