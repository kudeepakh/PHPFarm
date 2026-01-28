<?php

namespace PHPFrarm\Core\Storage\Drivers;

use PHPFrarm\Core\Storage\Contracts\StorageDriverInterface;
use PHPFrarm\Core\Logger;

/**
 * Azure Blob Storage Driver
 * 
 * Native driver for Microsoft Azure Blob Storage.
 * 
 * @package PHPFrarm\Core\Storage\Drivers
 */
class AzureDriver implements StorageDriverInterface
{
    private array $config;
    private $client = null;
    private $containerClient = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get or create the Azure Blob client
     */
    private function getContainerClient()
    {
        if ($this->containerClient === null) {
            $connectionString = sprintf(
                'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
                $this->config['account_name'],
                $this->config['account_key']
            );

            if (!empty($this->config['endpoint'])) {
                $connectionString .= ';BlobEndpoint=' . $this->config['endpoint'];
            }

            $blobServiceClient = \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService($connectionString);
            $this->client = $blobServiceClient;
            $this->containerClient = $this->config['container'];
        }

        return $this->client;
    }

    /**
     * Get container name
     */
    private function getContainer(): string
    {
        return $this->config['container'];
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, $contents, array $options = []): bool
    {
        try {
            $client = $this->getContainerClient();
            
            $blobOptions = new \MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions();
            
            if (isset($options['content_type'])) {
                $blobOptions->setContentType($options['content_type']);
            }

            if (isset($options['metadata'])) {
                $blobOptions->setMetadata($options['metadata']);
            }

            $client->createBlockBlob($this->getContainer(), $path, $contents, $blobOptions);

            Logger::info('Azure: File uploaded', [
                'path' => $path,
                'container' => $this->getContainer(),
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error('Azure: Upload failed', [
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

        $contents = fopen($localPath, 'rb');
        
        if (!isset($options['content_type'])) {
            $options['content_type'] = mime_content_type($localPath);
        }

        $result = $this->put($path, $contents, $options);
        fclose($contents);

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
            $client = $this->getContainerClient();
            $blob = $client->getBlob($this->getContainer(), $path);
            
            return stream_get_contents($blob->getContentStream());
        } catch (\Exception $e) {
            Logger::warning('Azure: Get file failed', [
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
            $client = $this->getContainerClient();
            $blob = $client->getBlob($this->getContainer(), $path);
            
            return $blob->getContentStream();
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
            $client = $this->getContainerClient();
            $client->getBlobProperties($this->getContainer(), $path);
            return true;
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
            $client = $this->getContainerClient();
            $client->deleteBlob($this->getContainer(), $path);

            Logger::info('Azure: File deleted', ['path' => $path]);
            return true;
        } catch (\Exception $e) {
            Logger::error('Azure: Delete failed', [
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
        try {
            $client = $this->getContainerClient();
            $sourceUrl = $this->url($from);
            
            $client->copyBlob($this->getContainer(), $to, $sourceUrl);
            
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
            $client = $this->getContainerClient();
            $props = $client->getBlobProperties($this->getContainer(), $path);
            
            return (int) $props->getProperties()->getContentLength();
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
            $client = $this->getContainerClient();
            $props = $client->getBlobProperties($this->getContainer(), $path);
            
            return $props->getProperties()->getContentType();
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
            $client = $this->getContainerClient();
            $props = $client->getBlobProperties($this->getContainer(), $path);
            
            return $props->getProperties()->getLastModified()->getTimestamp();
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
            $client = $this->getContainerClient();
            $props = $client->getBlobProperties($this->getContainer(), $path);
            $blobProps = $props->getProperties();

            return [
                'size' => (int) $blobProps->getContentLength(),
                'mime_type' => $blobProps->getContentType(),
                'last_modified' => $blobProps->getLastModified()->getTimestamp(),
                'etag' => $blobProps->getETag(),
                'metadata' => $props->getMetadata(),
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
        // Azure uses container-level access policies
        // For blob-level, you would need to use SAS tokens
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility(string $path): string
    {
        return $this->config['visibility'] ?? 'private';
    }

    /**
     * {@inheritdoc}
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        try {
            $client = $this->getContainerClient();
            $options = new \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions();
            
            if ($directory) {
                $options->setPrefix(rtrim($directory, '/') . '/');
            }

            if (!$recursive) {
                $options->setDelimiter('/');
            }

            $results = [];
            $blobs = $client->listBlobs($this->getContainer(), $options);

            foreach ($blobs->getBlobs() as $blob) {
                $results[] = [
                    'path' => $blob->getName(),
                    'type' => 'file',
                    'size' => $blob->getProperties()->getContentLength(),
                    'last_modified' => $blob->getProperties()->getLastModified()->getTimestamp(),
                ];
            }

            if (!$recursive) {
                foreach ($blobs->getBlobPrefixes() as $prefix) {
                    $results[] = [
                        'path' => rtrim($prefix->getName(), '/'),
                        'type' => 'directory',
                    ];
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
        // Azure Blob Storage doesn't have real directories
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $path): bool
    {
        try {
            $contents = $this->listContents($path, true);
            
            foreach ($contents as $item) {
                if ($item['type'] === 'file') {
                    $this->delete($item['path']);
                }
            }

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
            // Generate SAS token
            $sasToken = $this->generateSasToken($path, $expiration, 'r');
            
            return $this->url($path) . '?' . $sasToken;
        } catch (\Exception $e) {
            Logger::error('Azure: SAS URL generation failed', [
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
            // Generate SAS token with write permission
            $sasToken = $this->generateSasToken($path, $expiration, 'cw');
            
            return [
                'url' => $this->url($path) . '?' . $sasToken,
                'headers' => [
                    'x-ms-blob-type' => 'BlockBlob',
                    'Content-Type' => $options['content_type'] ?? 'application/octet-stream',
                ],
                'method' => 'PUT',
            ];
        } catch (\Exception $e) {
            return ['url' => '', 'headers' => []];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function url(string $path): string
    {
        $accountName = $this->config['account_name'];
        $container = $this->getContainer();
        
        if (!empty($this->config['endpoint'])) {
            return rtrim($this->config['endpoint'], '/') . '/' . $container . '/' . $path;
        }

        return "https://{$accountName}.blob.core.windows.net/{$container}/{$path}";
    }

    /**
     * Generate SAS token for Azure Blob
     */
    private function generateSasToken(string $path, int $expiration, string $permissions): string
    {
        $signedStart = gmdate('Y-m-d\TH:i:s\Z', time() - 300); // 5 minutes ago
        $signedExpiry = gmdate('Y-m-d\TH:i:s\Z', time() + $expiration);
        $signedResource = 'b'; // blob
        $signedVersion = '2020-12-06';

        $canonicalizedResource = sprintf(
            '/blob/%s/%s/%s',
            $this->config['account_name'],
            $this->getContainer(),
            $path
        );

        $stringToSign = implode("\n", [
            $permissions,
            $signedStart,
            $signedExpiry,
            $canonicalizedResource,
            '', // signedIdentifier
            '', // signedIP
            'https', // signedProtocol
            $signedVersion,
            $signedResource,
            '', // signedSnapshotTime
            '', // rscc
            '', // rscd
            '', // rsce
            '', // rscl
            '', // rsct
        ]);

        $signature = base64_encode(
            hash_hmac('sha256', $stringToSign, base64_decode($this->config['account_key']), true)
        );

        return http_build_query([
            'sv' => $signedVersion,
            'ss' => 'b',
            'srt' => 'o',
            'sp' => $permissions,
            'se' => $signedExpiry,
            'st' => $signedStart,
            'spr' => 'https',
            'sig' => $signature,
        ]);
    }
}
