<?php

namespace PHPFrarm\Modules\Storage\Services;

use PHPFrarm\Core\Storage\StorageManager;
use PHPFrarm\Core\Logger;

/**
 * Storage Service
 * 
 * Business logic for file storage operations.
 * Extracted from StorageController to follow SRP.
 */
class StorageService
{
    private StorageManager $storage;

    public function __construct()
    {
        $this->storage = StorageManager::getInstance();
    }

    /**
     * Upload a file
     * 
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function uploadFile(array $file, string $category = 'media', string $visibility = 'private'): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException($this->getUploadErrorMessage($file['error']));
        }

        $categoryStorage = $this->storage->category($category);
        $path = $categoryStorage->store($file, ['visibility' => $visibility]);

        if ($path === null) {
            throw new \RuntimeException('storage.upload.store_failed');
        }

        Logger::audit('file_uploaded', [
            'category' => $category,
            'path' => $path,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'mime_type' => $file['type'],
        ]);

        return [
            'path' => $path,
            'url' => $categoryStorage->url($path),
            'size' => $file['size'],
            'mime_type' => $file['type'],
            'original_name' => $file['name'],
        ];
    }

    /**
     * Generate pre-signed upload URL
     */
    public function generatePresignedUploadUrl(
        string $filename,
        string $category = 'media',
        string $contentType = 'application/octet-stream',
        int $expiration = 900
    ): array {
        $expiration = min($expiration, 3600); // Max 1 hour

        $categoryStorage = $this->storage->category($category);
        $result = $categoryStorage->temporaryUploadUrl($filename, $expiration, [
            'content_type' => $contentType,
        ]);

        return [
            'upload_url' => $result['url'],
            'method' => $result['method'] ?? 'PUT',
            'headers' => $result['headers'] ?? [],
            'path' => $result['path'],
            'expires_in' => $expiration,
        ];
    }

    /**
     * Generate pre-signed download URL
     */
    public function generatePresignedDownloadUrl(
        string $path,
        string $category = 'media',
        int $expiration = 3600
    ): array {
        $expiration = min($expiration, 86400); // Max 24 hours

        $categoryStorage = $this->storage->category($category);

        if (!$categoryStorage->exists($path)) {
            throw new \RuntimeException('storage.file_not_found');
        }

        $url = $categoryStorage->temporaryUrl($path, $expiration);

        return [
            'download_url' => $url,
            'expires_in' => $expiration,
        ];
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $path, string $category = 'media'): bool
    {
        $categoryStorage = $this->storage->category($category);

        if (!$categoryStorage->exists($path)) {
            throw new \RuntimeException('storage.file_not_found');
        }

        $result = $categoryStorage->delete($path);

        if ($result) {
            Logger::audit('file_deleted', [
                'category' => $category,
                'path' => $path,
            ]);
        }

        return $result;
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(string $path, string $category = 'media'): array
    {
        $categoryStorage = $this->storage->category($category);

        if (!$categoryStorage->exists($path)) {
            throw new \RuntimeException('storage.file_not_found');
        }

        $metadata = $categoryStorage->getMetadata($path);

        return [
            'path' => $path,
            'size' => $metadata['size'] ?? 0,
            'mime_type' => $metadata['mime_type'] ?? 'application/octet-stream',
            'last_modified' => $metadata['timestamp'] ?? null,
            'url' => $categoryStorage->url($path),
        ];
    }

    /**
     * List files in category
     */
    public function listFiles(
        string $category = 'media',
        string $directory = '',
        bool $recursive = false
    ): array {
        $categoryStorage = $this->storage->category($category);
        return $categoryStorage->listContents($directory, $recursive);
    }

    /**
     * Get storage configuration (safe for clients)
     */
    public function getClientConfig(): array
    {
        $config = $this->storage->getConfig();

        $safeConfig = [
            'categories' => [],
            'signed_urls' => [
                'enabled' => $config['signed_urls']['enabled'] ?? true,
                'expiry' => $config['signed_urls']['expiry'] ?? 3600,
            ],
        ];

        foreach ($config['categories'] as $name => $categoryConfig) {
            $safeConfig['categories'][$name] = [
                'allowed_types' => $categoryConfig['allowed_types'] ?? ['*/*'],
                'max_size' => $categoryConfig['max_size'] ?? PHP_INT_MAX,
                'max_size_formatted' => $this->formatBytes($categoryConfig['max_size'] ?? PHP_INT_MAX),
            ];
        }

        return $safeConfig;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'storage.upload.error.ini_size',
            UPLOAD_ERR_FORM_SIZE => 'storage.upload.error.form_size',
            UPLOAD_ERR_PARTIAL => 'storage.upload.error.partial',
            UPLOAD_ERR_NO_FILE => 'storage.upload.error.no_file',
            UPLOAD_ERR_NO_TMP_DIR => 'storage.upload.error.no_tmp_dir',
            UPLOAD_ERR_CANT_WRITE => 'storage.upload.error.cant_write',
            UPLOAD_ERR_EXTENSION => 'storage.upload.error.extension',
        ];

        return $messages[$errorCode] ?? 'storage.upload.error.unknown';
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
}
