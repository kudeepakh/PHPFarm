<?php

namespace PHPFrarm\Core\Storage\Drivers;

use PHPFrarm\Core\Storage\Contracts\StorageDriverInterface;
use PHPFrarm\Core\Logger;

/**
 * S3-Compatible Storage Driver
 * 
 * Supports: AWS S3, MinIO, Cloudflare R2, DigitalOcean Spaces,
 * Backblaze B2, Wasabi, Oracle OCI, Alibaba OSS, and more.
 * 
 * @package PHPFrarm\Core\Storage\Drivers
 */
class S3Driver implements StorageDriverInterface
{
    private array $config;
    private $client = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get or create the S3 client
     */
    private function getClient()
    {
        if ($this->client === null) {
            // Using AWS SDK for PHP
            $options = [
                'version' => 'latest',
                'region' => $this->config['region'] ?? 'us-east-1',
                'credentials' => [
                    'key' => $this->config['key'],
                    'secret' => $this->config['secret'],
                ],
            ];

            if (!empty($this->config['endpoint'])) {
                $options['endpoint'] = $this->config['endpoint'];
            }

            if ($this->config['use_path_style_endpoint'] ?? false) {
                $options['use_path_style_endpoint'] = true;
            }

            $this->client = new \Aws\S3\S3Client($options);
        }

        return $this->client;
    }

    /**
     * Get bucket name
     */
    private function getBucket(): string
    {
        return $this->config['bucket'];
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, $contents, array $options = []): bool
    {
        try {
            $params = [
                'Bucket' => $this->getBucket(),
                'Key' => $path,
                'Body' => $contents,
            ];

            // Set visibility
            if (isset($options['visibility'])) {
                $params['ACL'] = $options['visibility'] === 'public' ? 'public-read' : 'private';
            } else {
                $params['ACL'] = ($this->config['visibility'] ?? 'private') === 'public' ? 'public-read' : 'private';
            }

            // Set content type
            if (isset($options['content_type'])) {
                $params['ContentType'] = $options['content_type'];
            }

            // Set metadata
            if (isset($options['metadata'])) {
                $params['Metadata'] = $options['metadata'];
            }

            // Set cache control
            if (isset($this->config['options']['CacheControl'])) {
                $params['CacheControl'] = $this->config['options']['CacheControl'];
            }

            $this->getClient()->putObject($params);

            Logger::info('S3: File uploaded', [
                'path' => $path,
                'bucket' => $this->getBucket(),
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error('S3: Upload failed', [
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

        $contents = file_get_contents($localPath);
        
        if (!isset($options['content_type'])) {
            $options['content_type'] = mime_content_type($localPath);
        }

        return $this->put($path, $contents, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function putUploadedFile(string $path, array $uploadedFile, array $options = []): bool
    {
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            return false;
        }

        if (!isset($options['content_type']) && isset($uploadedFile['type'])) {
            $options['content_type'] = $uploadedFile['type'];
        }

        return $this->putFile($path, $uploadedFile['tmp_name'], $options);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): ?string
    {
        try {
            $result = $this->getClient()->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            return (string) $result['Body'];
        } catch (\Exception $e) {
            Logger::warning('S3: Get file failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStream(string $path)
    {
        try {
            $result = $this->getClient()->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            return $result['Body']->detach();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): bool
    {
        try {
            return $this->getClient()->doesObjectExist($this->getBucket(), $path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): bool
    {
        try {
            $this->getClient()->deleteObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            Logger::info('S3: File deleted', ['path' => $path]);
            return true;
        } catch (\Exception $e) {
            Logger::error('S3: Delete failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $paths): bool
    {
        try {
            $objects = array_map(fn($path) => ['Key' => $path], $paths);

            $this->getClient()->deleteObjects([
                'Bucket' => $this->getBucket(),
                'Delete' => ['Objects' => $objects],
            ]);

            Logger::info('S3: Multiple files deleted', ['count' => count($paths)]);
            return true;
        } catch (\Exception $e) {
            Logger::error('S3: Multiple delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $from, string $to): bool
    {
        try {
            $this->getClient()->copyObject([
                'Bucket' => $this->getBucket(),
                'CopySource' => $this->getBucket() . '/' . $from,
                'Key' => $to,
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): bool
    {
        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $path): ?int
    {
        try {
            $result = $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            return (int) $result['ContentLength'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType(string $path): ?string
    {
        try {
            $result = $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            return $result['ContentType'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): ?int
    {
        try {
            $result = $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            return $result['LastModified']->getTimestamp();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function metadata(string $path): array
    {
        try {
            $result = $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            return [
                'size' => (int) $result['ContentLength'],
                'mime_type' => $result['ContentType'],
                'last_modified' => $result['LastModified']->getTimestamp(),
                'etag' => trim($result['ETag'], '"'),
                'metadata' => $result['Metadata'] ?? [],
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        try {
            $this->getClient()->putObjectAcl([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
                'ACL' => $visibility === 'public' ? 'public-read' : 'private',
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility(string $path): string
    {
        try {
            $result = $this->getClient()->getObjectAcl([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            foreach ($result['Grants'] as $grant) {
                if (isset($grant['Grantee']['URI']) && 
                    str_contains($grant['Grantee']['URI'], 'AllUsers') &&
                    $grant['Permission'] === 'READ') {
                    return 'public';
                }
            }

            return 'private';
        } catch (\Exception $e) {
            return 'private';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        try {
            $params = [
                'Bucket' => $this->getBucket(),
            ];

            if ($directory) {
                $params['Prefix'] = rtrim($directory, '/') . '/';
            }

            if (!$recursive) {
                $params['Delimiter'] = '/';
            }

            $results = [];
            $paginator = $this->getClient()->getPaginator('ListObjectsV2', $params);

            foreach ($paginator as $page) {
                // Files
                if (isset($page['Contents'])) {
                    foreach ($page['Contents'] as $object) {
                        $results[] = [
                            'path' => $object['Key'],
                            'type' => 'file',
                            'size' => $object['Size'],
                            'last_modified' => $object['LastModified']->getTimestamp(),
                        ];
                    }
                }

                // Directories (prefixes)
                if (!$recursive && isset($page['CommonPrefixes'])) {
                    foreach ($page['CommonPrefixes'] as $prefix) {
                        $results[] = [
                            'path' => rtrim($prefix['Prefix'], '/'),
                            'type' => 'directory',
                        ];
                    }
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $path): bool
    {
        // S3 doesn't have directories, but we can create a placeholder
        try {
            $this->getClient()->putObject([
                'Bucket' => $this->getBucket(),
                'Key' => rtrim($path, '/') . '/',
                'Body' => '',
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $path): bool
    {
        try {
            $prefix = rtrim($path, '/') . '/';
            
            // List all objects with this prefix
            $objects = [];
            $paginator = $this->getClient()->getPaginator('ListObjectsV2', [
                'Bucket' => $this->getBucket(),
                'Prefix' => $prefix,
            ]);

            foreach ($paginator as $page) {
                if (isset($page['Contents'])) {
                    foreach ($page['Contents'] as $object) {
                        $objects[] = ['Key' => $object['Key']];
                    }
                }
            }

            if (empty($objects)) {
                return true;
            }

            // Delete all objects
            $this->getClient()->deleteObjects([
                'Bucket' => $this->getBucket(),
                'Delete' => ['Objects' => $objects],
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function temporaryUrl(string $path, int $expiration = 3600, array $options = []): string
    {
        try {
            $cmd = $this->getClient()->getCommand('GetObject', [
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);

            $request = $this->getClient()->createPresignedRequest($cmd, "+{$expiration} seconds");

            return (string) $request->getUri();
        } catch (\Exception $e) {
            Logger::error('S3: Temporary URL generation failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function temporaryUploadUrl(string $path, int $expiration = 900, array $options = []): array
    {
        try {
            $params = [
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ];

            if (isset($options['content_type'])) {
                $params['ContentType'] = $options['content_type'];
            }

            $cmd = $this->getClient()->getCommand('PutObject', $params);
            $request = $this->getClient()->createPresignedRequest($cmd, "+{$expiration} seconds");

            return [
                'url' => (string) $request->getUri(),
                'headers' => [
                    'Content-Type' => $options['content_type'] ?? 'application/octet-stream',
                ],
                'method' => 'PUT',
            ];
        } catch (\Exception $e) {
            Logger::error('S3: Temporary upload URL generation failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return ['url' => '', 'headers' => []];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function url(string $path): string
    {
        $endpoint = $this->config['endpoint'] ?? null;
        $bucket = $this->getBucket();
        $region = $this->config['region'] ?? 'us-east-1';

        if ($this->config['use_path_style_endpoint'] ?? false) {
            // Path-style: https://endpoint/bucket/key
            $baseUrl = rtrim($endpoint ?? "https://s3.{$region}.amazonaws.com", '/');
            return "{$baseUrl}/{$bucket}/{$path}";
        } else {
            // Virtual-hosted style: https://bucket.s3.region.amazonaws.com/key
            if ($endpoint) {
                return rtrim($endpoint, '/') . '/' . $path;
            }
            return "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";
        }
    }
}
