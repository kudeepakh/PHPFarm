<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

/**
 * Reddit Connector
 * 
 * Reddit API integration for subreddit posting and community engagement.
 * 
 * Features:
 * - Link and text post submission
 * - Subreddit moderation
 * - Comment replies
 * - Analytics and insights
 * 
 * API Docs: https://www.reddit.com/dev/api/
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class RedditConnector extends BasePlatformConnector
{
    protected string $platformName = 'Reddit';
    
    private string $apiUrl = 'https://oauth.reddit.com';
    private string $authUrl = 'https://www.reddit.com/api/v1';
    
    protected function getConfigKey(): string
    {
        return 'reddit';
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
            'identity',
            'submit',
            'read',
            'privatemessages',
            'mysubreddits',
            'history',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'response_type' => 'code',
            'state' => bin2hex(random_bytes(16)),
            'redirect_uri' => $redirectUri,
            'duration' => 'permanent',
            'scope' => implode(' ', $scopes),
        ];
        
        return $this->buildUrl("{$this->authUrl}/authorize", $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = "{$this->authUrl}/access_token";
        
        $auth = base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']);
        
        $data = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
        
        return $this->makeRequest('POST', $url, [
            "Authorization: Basic $auth",
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = "{$this->authUrl}/access_token";
        
        $auth = base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']);
        
        $data = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
        
        return $this->makeRequest('POST', $url, [
            "Authorization: Basic $auth",
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    /**
     * Get API headers
     */
    private function getHeaders(string $accessToken): array
    {
        return [
            "Authorization: Bearer $accessToken",
            'User-Agent: PHPFrarm/1.0',
            'Content-Type: application/json',
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Account Information
    |--------------------------------------------------------------------------
    */
    
    public function getAccountInfo(string $accessToken): array
    {
        $url = "{$this->apiUrl}/api/v1/me";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    public function getAccountAnalytics(string $accessToken, array $params = []): array
    {
        // Get karma breakdown
        $url = "{$this->apiUrl}/api/v1/me/karma";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Post Management
    |--------------------------------------------------------------------------
    */
    
    /**
     * Submit a link post
     */
    public function publishLink(string $accessToken, string $subreddit, string $title, string $url, array $options = []): array
    {
        $endpoint = "{$this->apiUrl}/api/submit";
        
        $data = [
            'kind' => 'link',
            'sr' => $subreddit,
            'title' => $title,
            'url' => $url,
            'api_type' => 'json',
        ];
        
        if (!empty($options['flair_id'])) {
            $data['flair_id'] = $options['flair_id'];
        }
        
        if (!empty($options['flair_text'])) {
            $data['flair_text'] = $options['flair_text'];
        }
        
        if (isset($options['nsfw'])) {
            $data['nsfw'] = $options['nsfw'];
        }
        
        if (isset($options['spoiler'])) {
            $data['spoiler'] = $options['spoiler'];
        }
        
        return $this->makeRequest('POST', $endpoint, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), http_build_query($data));
    }
    
    /**
     * Submit a text post
     */
    public function publishText(string $accessToken, string $subreddit, string $title, string $text, array $options = []): array
    {
        $endpoint = "{$this->apiUrl}/api/submit";
        
        $data = [
            'kind' => 'self',
            'sr' => $subreddit,
            'title' => $title,
            'text' => $text,
            'api_type' => 'json',
        ];
        
        if (!empty($options['flair_id'])) {
            $data['flair_id'] = $options['flair_id'];
        }
        
        if (!empty($options['flair_text'])) {
            $data['flair_text'] = $options['flair_text'];
        }
        
        if (isset($options['nsfw'])) {
            $data['nsfw'] = $options['nsfw'];
        }
        
        if (isset($options['spoiler'])) {
            $data['spoiler'] = $options['spoiler'];
        }
        
        return $this->makeRequest('POST', $endpoint, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), http_build_query($data));
    }
    
    /**
     * Submit an image post
     */
    public function publishImage(string $accessToken, string $subreddit, string $title, string $imageUrl, array $options = []): array
    {
        // Reddit uses link posts for images
        return $this->publishLink($accessToken, $subreddit, $title, $imageUrl, $options);
    }
    
    /**
     * Submit a video post (via link)
     */
    public function publishVideo(string $accessToken, string $subreddit, string $title, string $videoUrl, array $options = []): array
    {
        return $this->publishLink($accessToken, $subreddit, $title, $videoUrl, $options);
    }
    
    /**
     * Crosspost to another subreddit
     */
    public function crosspost(string $accessToken, string $originalPostId, string $subreddit, string $title): array
    {
        $endpoint = "{$this->apiUrl}/api/submit";
        
        $data = [
            'kind' => 'crosspost',
            'sr' => $subreddit,
            'title' => $title,
            'crosspost_fullname' => "t3_{$originalPostId}",
            'api_type' => 'json',
        ];
        
        return $this->makeRequest('POST', $endpoint, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), http_build_query($data));
    }
    
    /**
     * Delete a post
     */
    public function deletePost(string $accessToken, string $postId): array
    {
        $url = "{$this->apiUrl}/api/del";
        
        $data = http_build_query([
            'id' => "t3_{$postId}",
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /**
     * Get post details
     */
    public function getPost(string $accessToken, string $postId): array
    {
        $url = "{$this->apiUrl}/api/info";
        
        $params = [
            'id' => "t3_{$postId}",
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    */
    
    /**
     * Post a comment
     */
    public function comment(string $accessToken, string $thingId, string $text): array
    {
        $url = "{$this->apiUrl}/api/comment";
        
        $data = http_build_query([
            'thing_id' => $thingId,
            'text' => $text,
            'api_type' => 'json',
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /**
     * Get comments on a post
     */
    public function getComments(string $accessToken, string $subreddit, string $postId, int $limit = 100): array
    {
        $url = "{$this->apiUrl}/r/{$subreddit}/comments/{$postId}";
        
        $params = [
            'limit' => $limit,
            'sort' => 'best',
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Voting & Engagement
    |--------------------------------------------------------------------------
    */
    
    /**
     * Upvote a post or comment
     */
    public function upvote(string $accessToken, string $thingId): array
    {
        return $this->vote($accessToken, $thingId, 1);
    }
    
    /**
     * Downvote a post or comment
     */
    public function downvote(string $accessToken, string $thingId): array
    {
        return $this->vote($accessToken, $thingId, -1);
    }
    
    /**
     * Remove vote
     */
    public function unvote(string $accessToken, string $thingId): array
    {
        return $this->vote($accessToken, $thingId, 0);
    }
    
    /**
     * Vote on a thing
     */
    private function vote(string $accessToken, string $thingId, int $direction): array
    {
        $url = "{$this->apiUrl}/api/vote";
        
        $data = http_build_query([
            'id' => $thingId,
            'dir' => $direction,
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /**
     * Save a post or comment
     */
    public function save(string $accessToken, string $thingId): array
    {
        $url = "{$this->apiUrl}/api/save";
        
        $data = http_build_query([
            'id' => $thingId,
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /**
     * Unsave a post or comment
     */
    public function unsave(string $accessToken, string $thingId): array
    {
        $url = "{$this->apiUrl}/api/unsave";
        
        $data = http_build_query([
            'id' => $thingId,
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Subreddit Operations
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get subreddit info
     */
    public function getSubreddit(string $accessToken, string $subreddit): array
    {
        $url = "{$this->apiUrl}/r/{$subreddit}/about";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /**
     * Get subreddit posts
     */
    public function getSubredditPosts(string $accessToken, string $subreddit, string $sort = 'hot', int $limit = 25): array
    {
        $url = "{$this->apiUrl}/r/{$subreddit}/{$sort}";
        
        $params = [
            'limit' => $limit,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Get user's subscribed subreddits
     */
    public function getSubscribedSubreddits(string $accessToken, int $limit = 100): array
    {
        $url = "{$this->apiUrl}/subreddits/mine/subscriber";
        
        $params = [
            'limit' => $limit,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Subscribe to a subreddit
     */
    public function subscribe(string $accessToken, string $subreddit): array
    {
        $url = "{$this->apiUrl}/api/subscribe";
        
        $data = http_build_query([
            'action' => 'sub',
            'sr_name' => $subreddit,
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /**
     * Unsubscribe from a subreddit
     */
    public function unsubscribe(string $accessToken, string $subreddit): array
    {
        $url = "{$this->apiUrl}/api/subscribe";
        
        $data = http_build_query([
            'action' => 'unsub',
            'sr_name' => $subreddit,
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /**
     * Get available post flair
     */
    public function getPostFlairs(string $accessToken, string $subreddit): array
    {
        $url = "{$this->apiUrl}/r/{$subreddit}/api/link_flair_v2";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get inbox messages
     */
    public function getInbox(string $accessToken, int $limit = 25): array
    {
        $url = "{$this->apiUrl}/message/inbox";
        
        $params = [
            'limit' => $limit,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Send a private message
     */
    public function sendMessage(string $accessToken, string $to, string $subject, string $text): array
    {
        $url = "{$this->apiUrl}/api/compose";
        
        $data = http_build_query([
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
            'api_type' => 'json',
        ]);
        
        return $this->makeRequest('POST', $url, array_merge(
            $this->getHeaders($accessToken),
            ['Content-Type: application/x-www-form-urlencoded']
        ), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    
    /**
     * Search Reddit
     */
    public function search(string $accessToken, string $query, array $options = []): array
    {
        $url = "{$this->apiUrl}/search";
        
        $params = [
            'q' => $query,
            'limit' => $options['limit'] ?? 25,
            'sort' => $options['sort'] ?? 'relevance',
            'type' => $options['type'] ?? 'link',
        ];
        
        if (!empty($options['subreddit'])) {
            $params['restrict_sr'] = true;
            $url = "{$this->apiUrl}/r/{$options['subreddit']}/search";
        }
        
        if (!empty($options['time'])) {
            $params['t'] = $options['time'];
        }
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | User Content
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get user's posts
     */
    public function getUserPosts(string $accessToken, string $username, int $limit = 25): array
    {
        $url = "{$this->apiUrl}/user/{$username}/submitted";
        
        $params = [
            'limit' => $limit,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Get user's comments
     */
    public function getUserComments(string $accessToken, string $username, int $limit = 25): array
    {
        $url = "{$this->apiUrl}/user/{$username}/comments";
        
        $params = [
            'limit' => $limit,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
}
