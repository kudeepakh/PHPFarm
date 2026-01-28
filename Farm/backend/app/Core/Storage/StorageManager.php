<?php

namespace PHPFrarm\Core\Storage;

use PHPFrarm\Core\Storage\Contracts\StorageDriverInterface;
use PHPFrarm\Core\Storage\Drivers\LocalDriver;
use PHPFrarm\Core\Storage\Drivers\S3Driver;
use PHPFrarm\Core\Storage\Drivers\AzureDriver;
use PHPFrarm\Core\Logger;

/**
 * Storage Manager
 * 
 * Central manager for file storage operations.
 * Supports multiple disks, categories, and drivers.
 * 
 * @package PHPFrarm\Core\Storage
 */
class StorageManager
{
    private static ?StorageManager $instance = null;
    private array $config;
    private array $disks = [];

    private function __construct()
    {
        $this->config = require __DIR__ . '/../../../config/storage.php';
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a storage disk
     *
     * @param string|null $name Disk name (null for default)
     * @return StorageDriverInterface
     */
    public function disk(?string $name = null): StorageDriverInterface
    {
        $name = $name ?? $this->config['default'];

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->createDisk($name);
        }

        return $this->disks[$name];
    }

    /**
     * Create a disk driver instance
     */
    private function createDisk(string $name): StorageDriverInterface
    {
        if (!isset($this->config['disks'][$name])) {
            throw new \InvalidArgumentException("Storage disk '{$name}' is not configured.");
        }

        $config = $this->config['disks'][$name];
        $driver = $config['driver'];

        switch ($driver) {
            case 'local':
                return new LocalDriver($config);

            case 's3':
                return new S3Driver($config);

            case 'azure':
                return new AzureDriver($config);

            case 'gcs':
                // Google Cloud Storage driver
                return new S3Driver($config); // GCS has S3 interoperability

            default:
                throw new \InvalidArgumentException("Unknown storage driver: {$driver}");
        }
    }

    /**
     * Get storage for a specific category
     *
     * @param string $category Category name (media, documents, backups, etc.)
     * @return CategoryStorage
     */
    public function category(string $category): CategoryStorage
    {
        if (!isset($this->config['categories'][$category])) {
            throw new \InvalidArgumentException("Storage category '{$category}' is not configured.");
        }

        $categoryConfig = $this->config['categories'][$category];
        $diskName = $categoryConfig['disk'];
        
        // Handle 'default' disk reference
        if ($diskName === 'default') {
            $diskName = $this->config['default'];
        }

        return new CategoryStorage(
            $this->disk($diskName),
            $category,
            $categoryConfig
        );
    }

    /**
     * Shorthand for media storage
     */
    public function media(): CategoryStorage
    {
        return $this->category('media');
    }

    /**
     * Shorthand for documents storage
     */
    public function documents(): CategoryStorage
    {
        return $this->category('documents');
    }

    /**
     * Shorthand for backups storage
     */
    public function backups(): CategoryStorage
    {
        return $this->category('backups');
    }

    /**
     * Shorthand for temp storage
     */
    public function temp(): CategoryStorage
    {
        return $this->category('temp');
    }

    /**
     * Shorthand for exports storage
     */
    public function exports(): CategoryStorage
    {
        return $this->category('exports');
    }

    /**
     * Shorthand for avatars storage
     */
    public function avatars(): CategoryStorage
    {
        return $this->category('avatars');
    }

    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get available disk names
     */
    public function getAvailableDisks(): array
    {
        return array_keys($this->config['disks']);
    }

    /**
     * Get available categories
     */
    public function getAvailableCategories(): array
    {
        return array_keys($this->config['categories']);
    }
}

/**
 * Category Storage
 * 
 * Wrapper for storage operations within a specific category.
 * Handles path prefixing, validation, and category-specific rules.
 */
class CategoryStorage
{
    private StorageDriverInterface $disk;
    private string $category;
    private array $config;

    public function __construct(StorageDriverInterface $disk, string $category, array $config)
    {
        $this->disk = $disk;
        $this->category = $category;
        $this->config = $config;
    }

    /**
     * Get full path with category prefix
     */
    private function fullPath(string $path): string
    {
        $basePath = $this->config['path'] ?? $this->category;
        return $basePath . '/' . ltrim($path, '/');
    }

    /**
     * Validate file before upload
     */
    private function validateFile(array $uploadedFile): void
    {
        // Check file size
        $maxSize = $this->config['max_size'] ?? PHP_INT_MAX;
        if ($uploadedFile['size'] > $maxSize) {
            throw new \InvalidArgumentException(
                "File size exceeds maximum allowed size of " . $this->formatBytes($maxSize)
            );
        }

        // Check file type
        $allowedTypes = $this->config['allowed_types'] ?? ['*'];
        if ($allowedTypes !== ['*']) {
            $mimeType = $uploadedFile['type'] ?? mime_content_type($uploadedFile['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \InvalidArgumentException(
                    "File type '{$mimeType}' is not allowed for this category."
                );
            }
        }

        // Check for blocked extensions
        $storageConfig = require __DIR__ . '/../../../config/storage.php';
        $blockedExtensions = $storageConfig['security']['blocked_extensions'] ?? [];
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, $blockedExtensions)) {
            throw new \InvalidArgumentException(
                "File extension '.{$extension}' is not allowed."
            );
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Store a file
     */
    public function put(string $path, $contents, array $options = []): bool
    {
        return $this->disk->put($this->fullPath($path), $contents, $options);
    }

    /**
     * Store an uploaded file with validation
     */
    public function putUploadedFile(string $path, array $uploadedFile, array $options = []): bool
    {
        $this->validateFile($uploadedFile);
        return $this->disk->putUploadedFile($this->fullPath($path), $uploadedFile, $options);
    }

    /**
     * Store file with auto-generated unique name
     */
    public function store(array $uploadedFile, array $options = []): ?string
    {
        $this->validateFile($uploadedFile);

        // Generate unique filename
        $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . strtolower($extension);
        
        // Optional: organize by date
        $path = date('Y/m/d') . '/' . $filename;

        if ($this->disk->putUploadedFile($this->fullPath($path), $uploadedFile, $options)) {
            return $path;
        }

        return null;
    }

    /**
     * Get file contents
     */
    public function get(string $path): ?string
    {
        return $this->disk->get($this->fullPath($path));
    }

    /**
     * Get file as stream
     */
    public function getStream(string $path)
    {
        return $this->disk->getStream($this->fullPath($path));
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        return $this->disk->exists($this->fullPath($path));
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        return $this->disk->delete($this->fullPath($path));
    }

    /**
     * Get file size
     */
    public function size(string $path): ?int
    {
        return $this->disk->size($this->fullPath($path));
    }

    /**
     * Get file MIME type
     */
    public function mimeType(string $path): ?string
    {
        return $this->disk->mimeType($this->fullPath($path));
    }

    /**
     * Get file metadata
     */
    public function metadata(string $path): array
    {
        return $this->disk->metadata($this->fullPath($path));
    }

    /**
     * Get temporary download URL
     */
    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        return $this->disk->temporaryUrl($this->fullPath($path), $expiration);
    }

    /**
     * Get temporary upload URL (pre-signed)
     */
    public function temporaryUploadUrl(string $filename, int $expiration = 900, array $options = []): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $uniqueName = bin2hex(random_bytes(16)) . '.' . strtolower($extension);
        $path = date('Y/m/d') . '/' . $uniqueName;

        $result = $this->disk->temporaryUploadUrl($this->fullPath($path), $expiration, $options);
        $result['path'] = $path;
        
        return $result;
    }

    /**
     * Get public URL
     */
    public function url(string $path): string
    {
        return $this->disk->url($this->fullPath($path));
    }

    /**
     * List files in directory
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        return $this->disk->listContents($this->fullPath($directory), $recursive);
    }

    /**
     * Copy a file
     */
    public function copy(string $from, string $to): bool
    {
        return $this->disk->copy($this->fullPath($from), $this->fullPath($to));
    }

    /**
     * Move a file
     */
    public function move(string $from, string $to): bool
    {
        return $this->disk->move($this->fullPath($from), $this->fullPath($to));
    }

    /**
     * Get category configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get category name
     */
    public function getCategoryName(): string
    {
        return $this->category;
    }
}
