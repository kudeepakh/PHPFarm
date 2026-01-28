<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

/**
 * Medium Connector
 * 
 * Medium Publishing API integration for blog content.
 * 
 * Features:
 * - Article publishing (draft/public)
 * - Publication posting
 * - Content formatting (Markdown/HTML)
 * - User publications
 * 
 * API Docs: https://github.com/Medium/medium-api-docs
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class MediumConnector extends BasePlatformConnector
{
    protected string $platformName = 'Medium';
    
    private string $apiUrl = 'https://api.medium.com/v1';
    
    protected function getConfigKey(): string
    {
        return 'medium';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && 
               !empty($this->config['client_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (OAuth 2.0)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'basicProfile',
            'publishPost',
            'listPublications',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'scope' => implode(',', $scopes),
            'state' => bin2hex(random_bytes(16)),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
        ];
        
        return $this->buildUrl('https://medium.com/m/oauth/authorize', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = 'https://api.medium.com/v1/tokens';
        
        $data = http_build_query([
            'code' => $code,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = 'https://api.medium.com/v1/tokens';
        
        $data = http_build_query([
            'refresh_token' => $refreshToken,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'refresh_token',
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], $data);
    }
    
    /**
     * Get API headers
     */
    private function getHeaders(string $accessToken): array
    {
        return [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Account Information
    |--------------------------------------------------------------------------
    */
    
    public function getAccountInfo(string $accessToken): array
    {
        $url = "{$this->apiUrl}/me";
        
        $response = $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
        
        return $response['data'] ?? $response;
    }
    
    public function getAccountAnalytics(string $accessToken, array $params = []): array
    {
        // Medium doesn't provide analytics API
        // Return basic account info instead
        return $this->getAccountInfo($accessToken);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Publications
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get user's publications
     */
    public function getPublications(string $accessToken): array
    {
        $user = $this->getAccountInfo($accessToken);
        $userId = $user['id'] ?? null;
        
        if (!$userId) {
            return ['error' => 'Could not get user ID'];
        }
        
        $url = "{$this->apiUrl}/users/{$userId}/publications";
        
        $response = $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
        
        return $response['data'] ?? [];
    }
    
    /**
     * Get publication contributors
     */
    public function getPublicationContributors(string $accessToken, string $publicationId): array
    {
        $url = "{$this->apiUrl}/publications/{$publicationId}/contributors";
        
        $response = $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
        
        return $response['data'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Post Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Publish an article
     * 
     * @param string $accessToken
     * @param array $articleData [
     *     'title' => 'Article Title',
     *     'content' => 'Article content in HTML or Markdown',
     *     'contentFormat' => 'html' | 'markdown',
     *     'publishStatus' => 'public' | 'draft' | 'unlisted',
     *     'tags' => ['tag1', 'tag2'],
     *     'canonicalUrl' => 'https://original-url.com/article',
     *     'license' => 'all-rights-reserved' | 'cc-40-by' | 'cc-40-by-sa' | 'cc-40-by-nd' | 'cc-40-by-nc' | 'cc-40-by-nc-nd' | 'cc-40-by-nc-sa' | 'cc-40-zero' | 'public-domain',
     *     'notifyFollowers' => true | false,
     * ]
     */
    public function publishArticle(string $accessToken, array $articleData): array
    {
        $user = $this->getAccountInfo($accessToken);
        $userId = $user['id'] ?? null;
        
        if (!$userId) {
            return ['error' => 'Could not get user ID'];
        }
        
        $url = "{$this->apiUrl}/users/{$userId}/posts";
        
        $data = [
            'title' => $articleData['title'],
            'contentFormat' => $articleData['contentFormat'] ?? 'html',
            'content' => $articleData['content'],
            'publishStatus' => $articleData['publishStatus'] ?? 'draft',
        ];
        
        if (!empty($articleData['tags'])) {
            $data['tags'] = array_slice($articleData['tags'], 0, 5); // Max 5 tags
        }
        
        if (!empty($articleData['canonicalUrl'])) {
            $data['canonicalUrl'] = $articleData['canonicalUrl'];
        }
        
        if (!empty($articleData['license'])) {
            $data['license'] = $articleData['license'];
        }
        
        if (isset($articleData['notifyFollowers'])) {
            $data['notifyFollowers'] = $articleData['notifyFollowers'];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        return $response['data'] ?? $response;
    }
    
    /**
     * Publish to a publication
     */
    public function publishToPublication(string $accessToken, string $publicationId, array $articleData): array
    {
        $url = "{$this->apiUrl}/publications/{$publicationId}/posts";
        
        $data = [
            'title' => $articleData['title'],
            'contentFormat' => $articleData['contentFormat'] ?? 'html',
            'content' => $articleData['content'],
            'publishStatus' => $articleData['publishStatus'] ?? 'draft',
        ];
        
        if (!empty($articleData['tags'])) {
            $data['tags'] = array_slice($articleData['tags'], 0, 5);
        }
        
        if (!empty($articleData['canonicalUrl'])) {
            $data['canonicalUrl'] = $articleData['canonicalUrl'];
        }
        
        if (!empty($articleData['license'])) {
            $data['license'] = $articleData['license'];
        }
        
        if (isset($articleData['notifyFollowers'])) {
            $data['notifyFollowers'] = $articleData['notifyFollowers'];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        return $response['data'] ?? $response;
    }
    
    /**
     * Publish article with Markdown content
     */
    public function publishMarkdown(string $accessToken, string $title, string $markdown, array $options = []): array
    {
        return $this->publishArticle($accessToken, array_merge([
            'title' => $title,
            'content' => $markdown,
            'contentFormat' => 'markdown',
        ], $options));
    }
    
    /**
     * Publish article with HTML content
     */
    public function publishHtml(string $accessToken, string $title, string $html, array $options = []): array
    {
        return $this->publishArticle($accessToken, array_merge([
            'title' => $title,
            'content' => $html,
            'contentFormat' => 'html',
        ], $options));
    }
    
    /**
     * Save as draft
     */
    public function saveDraft(string $accessToken, string $title, string $content, string $format = 'html'): array
    {
        return $this->publishArticle($accessToken, [
            'title' => $title,
            'content' => $content,
            'contentFormat' => $format,
            'publishStatus' => 'draft',
        ]);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Helpers
    |--------------------------------------------------------------------------
    */
    
    /**
     * Format HTML content with proper Medium styling
     */
    public function formatHtmlContent(string $content, array $options = []): string
    {
        $html = $content;
        
        // Ensure images have proper figure wrapper
        $html = preg_replace(
            '/<img([^>]*)>/i',
            '<figure><img$1></figure>',
            $html
        );
        
        // Ensure code blocks are properly formatted
        $html = preg_replace(
            '/<pre><code([^>]*)>(.*?)<\/code><\/pre>/is',
            '<pre data-code-block-mode="1"><code$1>$2</code></pre>',
            $html
        );
        
        return $html;
    }
    
    /**
     * Convert Markdown to Medium-compatible HTML
     */
    public function markdownToHtml(string $markdown): string
    {
        // Basic Markdown to HTML conversion
        // For production, use a proper Markdown parser like Parsedown
        
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
        
        // Bold
        $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $html);
        
        // Italic
        $html = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $html);
        
        // Links
        $html = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', $html);
        
        // Images
        $html = preg_replace('/!\[(.*?)\]\((.*?)\)/s', '<figure><img src="$2" alt="$1"></figure>', $html);
        
        // Code blocks
        $html = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $html);
        
        // Inline code
        $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);
        
        // Paragraphs
        $html = preg_replace('/\n\n/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';
        
        return $html;
    }
    
    /**
     * Upload image and return URL
     * Note: Medium doesn't have a direct image upload API
     * Images should be included inline in the content or hosted externally
     */
    public function getImageEmbed(string $imageUrl, string $alt = '', string $caption = ''): string
    {
        $html = '<figure>';
        $html .= "<img src=\"{$imageUrl}\" alt=\"{$alt}\">";
        
        if ($caption) {
            $html .= "<figcaption>{$caption}</figcaption>";
        }
        
        $html .= '</figure>';
        
        return $html;
    }
}
