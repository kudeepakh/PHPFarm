<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

/**
 * WordPress Connector
 * 
 * WordPress REST API integration for blog publishing.
 * 
 * Features:
 * - Post creation and management
 * - Media upload
 * - Category and tag management
 * - User management
 * - Comments
 * 
 * API Docs: https://developer.wordpress.org/rest-api/
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class WordPressConnector extends BasePlatformConnector
{
    protected string $platformName = 'WordPress';
    
    private string $siteUrl;
    private string $apiUrl;
    
    protected function getConfigKey(): string
    {
        return 'wordpress';
    }
    
    public function __construct()
    {
        parent::__construct();
        
        $this->siteUrl = rtrim($this->config['site_url'] ?? '', '/');
        $this->apiUrl = "{$this->siteUrl}/wp-json/wp/v2";
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['site_url']) && 
               (!empty($this->config['application_password']) || 
                !empty($this->config['client_id']));
    }
    
    /**
     * Set the WordPress site URL (for multi-site usage)
     */
    public function setSiteUrl(string $siteUrl): self
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->apiUrl = "{$this->siteUrl}/wp-json/wp/v2";
        return $this;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get OAuth authorization URL (for WordPress.com or OAuth plugin)
     */
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        // For WordPress.com OAuth
        $params = [
            'client_id' => $this->config['client_id'] ?? '',
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes ?: ['global']),
            'state' => bin2hex(random_bytes(16)),
        ];
        
        return $this->buildUrl('https://public-api.wordpress.com/oauth2/authorize', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        // For WordPress.com OAuth
        $url = 'https://public-api.wordpress.com/oauth2/token';
        
        $data = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        // WordPress.com doesn't support refresh tokens in the standard way
        // Tokens are long-lived
        return ['error' => 'WordPress tokens are long-lived and do not require refresh'];
    }
    
    /**
     * Get headers for API requests
     * Supports OAuth token or Application Password
     */
    private function getHeaders(?string $accessToken = null): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        if ($accessToken) {
            $headers[] = "Authorization: Bearer $accessToken";
        } elseif (!empty($this->config['username']) && !empty($this->config['application_password'])) {
            $auth = base64_encode($this->config['username'] . ':' . $this->config['application_password']);
            $headers[] = "Authorization: Basic $auth";
        }
        
        return $headers;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Account Information
    |--------------------------------------------------------------------------
    */
    
    public function getAccountInfo(?string $accessToken = null): array
    {
        $url = "{$this->apiUrl}/users/me";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    public function getAccountAnalytics(?string $accessToken = null, array $params = []): array
    {
        // Get site info and post count
        $siteInfo = $this->getSiteInfo($accessToken);
        $posts = $this->getPosts($accessToken, ['per_page' => 1]);
        
        return [
            'site' => $siteInfo,
            'total_posts' => $posts['headers']['X-WP-Total'] ?? 0,
        ];
    }
    
    /**
     * Get site information
     */
    public function getSiteInfo(?string $accessToken = null): array
    {
        $url = "{$this->siteUrl}/wp-json";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Post Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get posts
     */
    public function getPosts(?string $accessToken = null, array $params = []): array
    {
        $url = "{$this->apiUrl}/posts";
        
        $defaultParams = [
            'per_page' => $params['per_page'] ?? 10,
            'page' => $params['page'] ?? 1,
            'status' => $params['status'] ?? 'publish',
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, array_merge($defaultParams, $params)), $this->getHeaders($accessToken));
    }
    
    /**
     * Get a single post
     */
    public function getPost(?string $accessToken, int $postId): array
    {
        $url = "{$this->apiUrl}/posts/{$postId}";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /**
     * Create a post
     * 
     * @param array $postData [
     *     'title' => 'Post Title',
     *     'content' => 'Post content HTML',
     *     'excerpt' => 'Post excerpt',
     *     'status' => 'draft' | 'publish' | 'pending' | 'private',
     *     'categories' => [1, 2, 3],
     *     'tags' => [4, 5, 6],
     *     'featured_media' => 123,
     *     'format' => 'standard' | 'aside' | 'gallery' | 'link' | 'image' | 'quote' | 'status' | 'video' | 'audio' | 'chat',
     *     'slug' => 'custom-slug',
     *     'date' => '2024-01-15T10:00:00',
     *     'meta' => ['key' => 'value'],
     * ]
     */
    public function createPost(?string $accessToken, array $postData): array
    {
        $url = "{$this->apiUrl}/posts";
        
        $data = [
            'title' => $postData['title'],
            'content' => $postData['content'],
            'status' => $postData['status'] ?? 'draft',
        ];
        
        $optionalFields = ['excerpt', 'categories', 'tags', 'featured_media', 'format', 'slug', 'date', 'meta', 'author'];
        
        foreach ($optionalFields as $field) {
            if (isset($postData[$field])) {
                $data[$field] = $postData[$field];
            }
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /**
     * Update a post
     */
    public function updatePost(?string $accessToken, int $postId, array $updates): array
    {
        $url = "{$this->apiUrl}/posts/{$postId}";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $updates);
    }
    
    /**
     * Delete a post
     */
    public function deletePost(?string $accessToken, int $postId, bool $force = false): array
    {
        $url = "{$this->apiUrl}/posts/{$postId}";
        
        if ($force) {
            $url .= '?force=true';
        }
        
        return $this->makeRequest('DELETE', $url, $this->getHeaders($accessToken));
    }
    
    /**
     * Publish a draft post
     */
    public function publishPost(?string $accessToken, int $postId): array
    {
        return $this->updatePost($accessToken, $postId, ['status' => 'publish']);
    }
    
    /**
     * Schedule a post
     */
    public function schedulePost(?string $accessToken, int $postId, string $dateTime): array
    {
        return $this->updatePost($accessToken, $postId, [
            'status' => 'future',
            'date' => $dateTime,
        ]);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Media Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Upload media
     */
    public function uploadMedia(?string $accessToken, string $filePath, array $metadata = []): array
    {
        $url = "{$this->apiUrl}/media";
        
        $fileName = $metadata['filename'] ?? basename($filePath);
        $mimeType = $metadata['mime_type'] ?? mime_content_type($filePath);
        
        $headers = $this->getHeaders($accessToken);
        $headers[] = "Content-Disposition: attachment; filename=\"{$fileName}\"";
        $headers[] = "Content-Type: {$mimeType}";
        
        // Remove JSON content type
        $headers = array_filter($headers, fn($h) => strpos($h, 'Content-Type: application/json') === false);
        
        $fileContent = file_get_contents($filePath);
        
        $response = $this->makeRequest('POST', $url, $headers, $fileContent);
        
        // Update metadata if provided
        if ($response['id'] ?? false) {
            if (!empty($metadata['title']) || !empty($metadata['alt_text']) || !empty($metadata['caption'])) {
                $this->updateMedia($accessToken, $response['id'], $metadata);
            }
        }
        
        return $response;
    }
    
    /**
     * Upload media from URL
     */
    public function uploadMediaFromUrl(?string $accessToken, string $url, array $metadata = []): array
    {
        // Download file to temp
        $tempFile = tempnam(sys_get_temp_dir(), 'wp_media_');
        file_put_contents($tempFile, file_get_contents($url));
        
        $metadata['filename'] = $metadata['filename'] ?? basename(parse_url($url, PHP_URL_PATH));
        
        $result = $this->uploadMedia($accessToken, $tempFile, $metadata);
        
        unlink($tempFile);
        
        return $result;
    }
    
    /**
     * Get media
     */
    public function getMedia(?string $accessToken, int $mediaId): array
    {
        $url = "{$this->apiUrl}/media/{$mediaId}";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /**
     * Update media metadata
     */
    public function updateMedia(?string $accessToken, int $mediaId, array $metadata): array
    {
        $url = "{$this->apiUrl}/media/{$mediaId}";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $metadata);
    }
    
    /**
     * Delete media
     */
    public function deleteMedia(?string $accessToken, int $mediaId, bool $force = true): array
    {
        $url = "{$this->apiUrl}/media/{$mediaId}?force=" . ($force ? 'true' : 'false');
        
        return $this->makeRequest('DELETE', $url, $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Categories & Tags
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get categories
     */
    public function getCategories(?string $accessToken = null, array $params = []): array
    {
        $url = "{$this->apiUrl}/categories";
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Create category
     */
    public function createCategory(?string $accessToken, string $name, array $data = []): array
    {
        $url = "{$this->apiUrl}/categories";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), array_merge([
            'name' => $name,
        ], $data));
    }
    
    /**
     * Get tags
     */
    public function getTags(?string $accessToken = null, array $params = []): array
    {
        $url = "{$this->apiUrl}/tags";
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Create tag
     */
    public function createTag(?string $accessToken, string $name, array $data = []): array
    {
        $url = "{$this->apiUrl}/tags";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), array_merge([
            'name' => $name,
        ], $data));
    }
    
    /**
     * Get or create category by name
     */
    public function getOrCreateCategory(?string $accessToken, string $name): array
    {
        $categories = $this->getCategories($accessToken, ['search' => $name]);
        
        foreach ($categories as $category) {
            if (strtolower($category['name']) === strtolower($name)) {
                return $category;
            }
        }
        
        return $this->createCategory($accessToken, $name);
    }
    
    /**
     * Get or create tag by name
     */
    public function getOrCreateTag(?string $accessToken, string $name): array
    {
        $tags = $this->getTags($accessToken, ['search' => $name]);
        
        foreach ($tags as $tag) {
            if (strtolower($tag['name']) === strtolower($name)) {
                return $tag;
            }
        }
        
        return $this->createTag($accessToken, $name);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get comments
     */
    public function getComments(?string $accessToken = null, array $params = []): array
    {
        $url = "{$this->apiUrl}/comments";
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Get comments for a post
     */
    public function getPostComments(?string $accessToken, int $postId, array $params = []): array
    {
        $params['post'] = $postId;
        return $this->getComments($accessToken, $params);
    }
    
    /**
     * Create comment
     */
    public function createComment(?string $accessToken, int $postId, string $content, array $data = []): array
    {
        $url = "{$this->apiUrl}/comments";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), array_merge([
            'post' => $postId,
            'content' => $content,
        ], $data));
    }
    
    /**
     * Update comment
     */
    public function updateComment(?string $accessToken, int $commentId, array $updates): array
    {
        $url = "{$this->apiUrl}/comments/{$commentId}";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $updates);
    }
    
    /**
     * Delete comment
     */
    public function deleteComment(?string $accessToken, int $commentId, bool $force = false): array
    {
        $url = "{$this->apiUrl}/comments/{$commentId}";
        
        if ($force) {
            $url .= '?force=true';
        }
        
        return $this->makeRequest('DELETE', $url, $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get pages
     */
    public function getPages(?string $accessToken = null, array $params = []): array
    {
        $url = "{$this->apiUrl}/pages";
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Create page
     */
    public function createPage(?string $accessToken, array $pageData): array
    {
        $url = "{$this->apiUrl}/pages";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $pageData);
    }
    
    /**
     * Update page
     */
    public function updatePage(?string $accessToken, int $pageId, array $updates): array
    {
        $url = "{$this->apiUrl}/pages/{$pageId}";
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $updates);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get users
     */
    public function getUsers(?string $accessToken = null, array $params = []): array
    {
        $url = "{$this->apiUrl}/users";
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Get user by ID
     */
    public function getUser(?string $accessToken, int $userId): array
    {
        $url = "{$this->apiUrl}/users/{$userId}";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    
    /**
     * Search content
     */
    public function search(?string $accessToken, string $query, array $params = []): array
    {
        $url = "{$this->apiUrl}/search";
        
        $params['search'] = $query;
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
}
