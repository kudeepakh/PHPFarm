<?php

namespace PHPFrarm\Core\Storage\Drivers;

use PHPFrarm\Core\Storage\Contracts\StorageDriverInterface;
use PHPFrarm\Core\Logger;

/**
 * Local Filesystem Storage Driver
 * 
 * For development and on-premises deployments.
 * 
 * @package PHPFrarm\Core\Storage\Drivers
 */
class LocalDriver implements StorageDriverInterface
{
    private array $config;
    private string $root;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->root = rtrim($config['root'] ?? '/var/www/html/storage', '/');

        // Ensure root directory exists
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    /**
     * Get full path
     */
    private function fullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Ensure directory exists for path
     */
    private function ensureDirectory(string $path): void
    {
        $dir = dirname($this->fullPath($path));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, $contents, array $options = []): bool
    {
        try {
            $this->ensureDirectory($path);
            $fullPath = $this->fullPath($path);

            if (is_resource($contents)) {
                $result = file_put_contents($fullPath, stream_get_contents($contents));
            } else {
                $result = file_put_contents($fullPath, $contents);
            }

            if ($result !== false) {
                $visibility = $options['visibility'] ?? ($this->config['visibility'] ?? 'private');
                $this->setVisibility($path, $visibility);

                Logger::info('Local: File saved', ['path' => $path]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Local: Save failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function putFile(string $path, string $localPath, array $options = []): bool
    {
        if (!file_exists($localPath)) {
            return false;
        }

        $this->ensureDirectory($path);
        $result = copy($localPath, $this->fullPath($path));

        if ($result) {
            $visibility = $options['visibility'] ?? ($this->config['visibility'] ?? 'private');
            $this->setVisibility($path, $visibility);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function putUploadedFile(string $path, array $uploadedFile, array $options = []): bool
    {
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            return false;
        }

        $this->ensureDirectory($path);
        $result = move_uploaded_file($uploadedFile['tmp_name'], $this->fullPath($path));

        if ($result) {
            $visibility = $options['visibility'] ?? ($this->config['visibility'] ?? 'private');
            $this->setVisibility($path, $visibility);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getStream(string $path)
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return null;
        }

        return fopen($fullPath, 'rb');
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return true;
        }

        $result = unlink($fullPath);
        
        if ($result) {
            Logger::info('Local: File deleted', ['path' => $path]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $paths): bool
    {
        $success = true;
        
        foreach ($paths as $path) {
            if (!$this->delete($path)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $from, string $to): bool
    {
        $this->ensureDirectory($to);
        return copy($this->fullPath($from), $this->fullPath($to));
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): bool
    {
        $this->ensureDirectory($to);
        return rename($this->fullPath($from), $this->fullPath($to));
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $path): ?int
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return null;
        }

        return filesize($fullPath);
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType(string $path): ?string
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return null;
        }

        return mime_content_type($fullPath);
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): ?int
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return null;
        }

        return filemtime($fullPath);
    }

    /**
     * {@inheritdoc}
     */
    public function metadata(string $path): array
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return [];
        }

        return [
            'size' => filesize($fullPath),
            'mime_type' => mime_content_type($fullPath),
            'last_modified' => filemtime($fullPath),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return false;
        }

        $permissions = $this->config['permissions']['file'][$visibility] ?? 
                       ($visibility === 'public' ? 0644 : 0600);

        return chmod($fullPath, $permissions);
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility(string $path): string
    {
        $fullPath = $this->fullPath($path);
        
        if (!file_exists($fullPath)) {
            return 'private';
        }

        $perms = fileperms($fullPath);
        
        // Check if world-readable
        if ($perms & 0004) {
            return 'public';
        }

        return 'private';
    }

    /**
     * {@inheritdoc}
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->fullPath($directory);
        
        if (!is_dir($fullPath)) {
            return [];
        }

        $results = [];
        $iterator = $recursive 
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fullPath))
            : new \DirectoryIterator($fullPath);

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            $relativePath = str_replace($this->root . '/', '', $item->getPathname());

            $results[] = [
                'path' => $relativePath,
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->isFile() ? $item->getSize() : null,
                'last_modified' => $item->getMTime(),
            ];
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);
        
        if (is_dir($fullPath)) {
            return true;
        }

        $visibility = $this->config['visibility'] ?? 'private';
        $permissions = $this->config['permissions']['dir'][$visibility] ?? 0755;

        return mkdir($fullPath, $permissions, true);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);
        
        if (!is_dir($fullPath)) {
            return true;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        return rmdir($fullPath);
    }

    /**
     * {@inheritdoc}
     */
    public function temporaryUrl(string $path, int $expiration = 3600, array $options = []): string
    {
        // For local storage, generate a signed URL with expiration
        $expires = time() + $expiration;
        $signature = $this->generateSignature($path, $expires);
        
        $baseUrl = $this->config['url'] ?? '/storage';
        
        return $baseUrl . '/' . $path . '?expires=' . $expires . '&signature=' . $signature;
    }

    /**
     * {@inheritdoc}
     */
    public function temporaryUploadUrl(string $path, int $expiration = 900, array $options = []): array
    {
        // For local storage, return an upload endpoint with signed token
        $expires = time() + $expiration;
        $signature = $this->generateSignature($path, $expires, 'upload');

        return [
            'url' => '/api/v1/storage/upload?path=' . urlencode($path) . '&expires=' . $expires . '&signature=' . $signature,
            'headers' => [
                'Content-Type' => $options['content_type'] ?? 'multipart/form-data',
            ],
            'method' => 'POST',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function url(string $path): string
    {
        $baseUrl = $this->config['url'] ?? '/storage';
        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Generate signature for signed URLs
     */
    private function generateSignature(string $path, int $expires, string $action = 'download'): string
    {
        $secret = $_ENV['APP_SECRET'] ?? 'phpfrarm-secret';
        $data = $path . ':' . $expires . ':' . $action;
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Verify signature for signed URLs
     */
    public function verifySignature(string $path, int $expires, string $signature, string $action = 'download'): bool
    {
        if (time() > $expires) {
            return false;
        }

        $expectedSignature = $this->generateSignature($path, $expires, $action);
        
        return hash_equals($expectedSignature, $signature);
    }
}
