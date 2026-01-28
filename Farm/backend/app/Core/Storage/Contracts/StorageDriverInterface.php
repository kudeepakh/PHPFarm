<?php

namespace PHPFrarm\Core\Storage\Contracts;

/**
 * Storage Driver Interface
 * 
 * Defines the contract for all storage drivers (S3, Azure, GCS, Local, etc.)
 * 
 * @package PHPFrarm\Core\Storage
 */
interface StorageDriverInterface
{
    /**
     * Store a file
     *
     * @param string $path Path where file should be stored
     * @param string|resource $contents File contents or stream
     * @param array $options Additional options (visibility, metadata, etc.)
     * @return bool
     */
    public function put(string $path, $contents, array $options = []): bool;

    /**
     * Store a file from a local path
     *
     * @param string $path Destination path
     * @param string $localPath Local file path
     * @param array $options Additional options
     * @return bool
     */
    public function putFile(string $path, string $localPath, array $options = []): bool;

    /**
     * Store a file from upload
     *
     * @param string $path Destination path
     * @param array $uploadedFile $_FILES array element
     * @param array $options Additional options
     * @return bool
     */
    public function putUploadedFile(string $path, array $uploadedFile, array $options = []): bool;

    /**
     * Get file contents
     *
     * @param string $path File path
     * @return string|null
     */
    public function get(string $path): ?string;

    /**
     * Get file as stream
     *
     * @param string $path File path
     * @return resource|null
     */
    public function getStream(string $path);

    /**
     * Check if file exists
     *
     * @param string $path File path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Delete a file
     *
     * @param string $path File path
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Delete multiple files
     *
     * @param array $paths Array of file paths
     * @return bool
     */
    public function deleteMultiple(array $paths): bool;

    /**
     * Copy a file
     *
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file
     *
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool
     */
    public function move(string $from, string $to): bool;

    /**
     * Get file size in bytes
     *
     * @param string $path File path
     * @return int|null
     */
    public function size(string $path): ?int;

    /**
     * Get file MIME type
     *
     * @param string $path File path
     * @return string|null
     */
    public function mimeType(string $path): ?string;

    /**
     * Get last modified timestamp
     *
     * @param string $path File path
     * @return int|null Unix timestamp
     */
    public function lastModified(string $path): ?int;

    /**
     * Get file metadata
     *
     * @param string $path File path
     * @return array
     */
    public function metadata(string $path): array;

    /**
     * Set file visibility
     *
     * @param string $path File path
     * @param string $visibility 'public' or 'private'
     * @return bool
     */
    public function setVisibility(string $path, string $visibility): bool;

    /**
     * Get file visibility
     *
     * @param string $path File path
     * @return string 'public' or 'private'
     */
    public function getVisibility(string $path): string;

    /**
     * List files in a directory
     *
     * @param string $directory Directory path
     * @param bool $recursive Include subdirectories
     * @return array
     */
    public function listContents(string $directory = '', bool $recursive = false): array;

    /**
     * Create a directory
     *
     * @param string $path Directory path
     * @return bool
     */
    public function createDirectory(string $path): bool;

    /**
     * Delete a directory
     *
     * @param string $path Directory path
     * @return bool
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Get a temporary URL for the file (signed URL)
     *
     * @param string $path File path
     * @param int $expiration Expiration time in seconds
     * @param array $options Additional options
     * @return string
     */
    public function temporaryUrl(string $path, int $expiration = 3600, array $options = []): string;

    /**
     * Get a temporary upload URL (pre-signed PUT URL)
     *
     * @param string $path File path
     * @param int $expiration Expiration time in seconds
     * @param array $options Additional options (content-type, etc.)
     * @return array ['url' => string, 'headers' => array]
     */
    public function temporaryUploadUrl(string $path, int $expiration = 900, array $options = []): array;

    /**
     * Get the public URL for a file
     *
     * @param string $path File path
     * @return string
     */
    public function url(string $path): string;
}
