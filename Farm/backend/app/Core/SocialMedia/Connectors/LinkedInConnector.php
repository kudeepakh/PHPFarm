<?php

namespace PHPFrarm\Core\SocialMedia\Connectors;

use PHPFrarm\Core\Logger;

/**
 * LinkedIn Connector
 * 
 * LinkedIn API integration for personal profiles and company pages.
 * 
 * Features:
 * - Post publishing (text, images, articles)
 * - Company page management
 * - Analytics and insights
 * - Share to LinkedIn
 * 
 * API Docs: https://learn.microsoft.com/en-us/linkedin/marketing/
 * 
 * @package PHPFrarm\Core\SocialMedia\Connectors
 */
class LinkedInConnector extends BasePlatformConnector
{
    protected string $platformName = 'LinkedIn';
    protected string $platformType = 'social';
    protected array $supportedContentTypes = ['text', 'image', 'article', 'video', 'document'];
    
    private string $apiUrl = 'https://api.linkedin.com/v2';
    private string $restApiUrl = 'https://api.linkedin.com/rest';
    
    protected function getConfigKey(): string
    {
        return 'linkedin';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication (OAuth 2.0)
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'openid',
            'profile',
            'email',
            'w_member_social',
        ];
        
        // Add company page scopes if needed
        if ($this->config['manage_pages'] ?? false) {
            $defaultScopes = array_merge($defaultScopes, [
                'r_organization_admin',
                'w_organization_social',
                'rw_organization_admin',
            ]);
        }
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $this->generateState(),
        ];
        
        return $this->buildUrl('https://www.linkedin.com/oauth/v2/authorization', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = 'https://www.linkedin.com/oauth/v2/accessToken';
        
        $data = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = 'https://www.linkedin.com/oauth/v2/accessToken';
        
        $data = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function revokeToken(string $token): bool
    {
        // LinkedIn doesn't have a token revocation endpoint
        // Token will expire naturally
        return true;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    
    public function getProfile(string $accessToken): array
    {
        // Get profile using OpenID Connect userinfo endpoint
        $url = "{$this->apiUrl}/userinfo";
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
        ]);
        
        // Also get member info for URN
        $memberUrl = "{$this->apiUrl}/me";
        $member = $this->makeRequest('GET', $memberUrl, [
            "Authorization: Bearer $accessToken",
        ]);
        
        return array_merge($response, [
            'member_id' => $member['id'] ?? null,
            'member_urn' => isset($member['id']) ? "urn:li:person:{$member['id']}" : null,
        ]);
    }
    
    public function getConnectedAccounts(string $accessToken): array
    {
        // Get organizations/companies the user manages
        $url = $this->buildUrl("{$this->apiUrl}/organizationAcls", [
            'q' => 'roleAssignee',
            'role' => 'ADMINISTRATOR',
            'projection' => '(elements*(organization~(id,localizedName,logoV2)))',
        ]);
        
        try {
            $response = $this->makeRequest('GET', $url, [
                "Authorization: Bearer $accessToken",
                'LinkedIn-Version: 202401',
            ]);
            
            return $response['elements'] ?? [];
        } catch (\Exception $e) {
            // User might not have organization permissions
            return [];
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Content Publishing
    |--------------------------------------------------------------------------
    */
    
    public function publishPost(string $accessToken, string $content, array $options = []): array
    {
        $profile = $this->getProfile($accessToken);
        $authorUrn = $options['organization_urn'] ?? $profile['member_urn'] ?? null;
        
        if (!$authorUrn) {
            return ['success' => false, 'error' => 'Author URN not found'];
        }
        
        $postData = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'visibility' => $options['visibility'] ?? 'PUBLIC',
            'commentary' => $content,
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
        ];
        
        // Add article if provided
        if (!empty($options['article'])) {
            $postData['content'] = [
                'article' => [
                    'source' => $options['article']['url'],
                    'title' => $options['article']['title'] ?? '',
                    'description' => $options['article']['description'] ?? '',
                ],
            ];
        }
        
        // Add media if provided
        if (!empty($options['media_ids'])) {
            $postData['content'] = [
                'multiImage' => [
                    'images' => array_map(fn($id) => ['id' => $id], $options['media_ids']),
                ],
            ];
        }
        
        $url = "{$this->restApiUrl}/posts";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'LinkedIn-Version: 202401',
            'X-Restli-Protocol-Version: 2.0.0',
        ], $postData);
        
        // Extract post ID from header
        $postId = $response['x-restli-id'] ?? $response['id'] ?? null;
        
        Logger::info('LinkedIn post published', [
            'post_id' => $postId,
        ]);
        
        return [
            'success' => true,
            'post_id' => $postId,
            'platform' => 'linkedin',
        ];
    }
    
    public function publishImage(string $accessToken, string $imageUrl, string $caption = '', array $options = []): array
    {
        // Step 1: Initialize upload
        $profile = $this->getProfile($accessToken);
        $ownerUrn = $options['organization_urn'] ?? $profile['member_urn'] ?? null;
        
        if (!$ownerUrn) {
            return ['success' => false, 'error' => 'Owner URN not found'];
        }
        
        // Register image upload
        $registerUrl = "{$this->restApiUrl}/images?action=initializeUpload";
        
        $registerData = [
            'initializeUploadRequest' => [
                'owner' => $ownerUrn,
            ],
        ];
        
        $registerResponse = $this->makeRequest('POST', $registerUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'LinkedIn-Version: 202401',
        ], $registerData);
        
        $uploadUrl = $registerResponse['value']['uploadUrl'] ?? null;
        $imageUrn = $registerResponse['value']['image'] ?? null;
        
        if (!$uploadUrl || !$imageUrn) {
            return ['success' => false, 'error' => 'Failed to initialize image upload'];
        }
        
        // Step 2: Upload the image
        $imageData = file_get_contents($imageUrl);
        
        $this->makeRequest('PUT', $uploadUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/octet-stream',
        ], $imageData);
        
        // Step 3: Create post with image
        $options['media_ids'] = [$imageUrn];
        
        return $this->publishPost($accessToken, $caption, $options);
    }
    
    public function publishVideo(string $accessToken, string $videoUrl, string $caption = '', array $options = []): array
    {
        // Video upload is similar but requires chunked upload for large files
        $profile = $this->getProfile($accessToken);
        $ownerUrn = $options['organization_urn'] ?? $profile['member_urn'] ?? null;
        
        if (!$ownerUrn) {
            return ['success' => false, 'error' => 'Owner URN not found'];
        }
        
        // For simplicity, we'll handle small videos
        // Large videos need chunked upload
        
        // Register video
        $registerUrl = "{$this->restApiUrl}/videos?action=initializeUpload";
        
        $videoData = file_get_contents($videoUrl);
        $fileSize = strlen($videoData);
        
        $registerData = [
            'initializeUploadRequest' => [
                'owner' => $ownerUrn,
                'fileSizeBytes' => $fileSize,
                'uploadCaptions' => false,
                'uploadThumbnail' => false,
            ],
        ];
        
        $registerResponse = $this->makeRequest('POST', $registerUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'LinkedIn-Version: 202401',
        ], $registerData);
        
        $uploadUrl = $registerResponse['value']['uploadInstructions'][0]['uploadUrl'] ?? null;
        $videoUrn = $registerResponse['value']['video'] ?? null;
        
        if (!$uploadUrl || !$videoUrn) {
            return ['success' => false, 'error' => 'Failed to initialize video upload'];
        }
        
        // Upload video
        $this->makeRequest('PUT', $uploadUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/octet-stream',
        ], $videoData);
        
        // Finalize upload
        $finalizeUrl = "{$this->restApiUrl}/videos?action=finalizeUpload";
        
        $this->makeRequest('POST', $finalizeUrl, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'LinkedIn-Version: 202401',
        ], [
            'finalizeUploadRequest' => [
                'video' => $videoUrn,
                'uploadToken' => '',
                'uploadedPartIds' => [],
            ],
        ]);
        
        // Create post with video
        $postData = [
            'author' => $ownerUrn,
            'lifecycleState' => 'PUBLISHED',
            'visibility' => 'PUBLIC',
            'commentary' => $caption,
            'content' => [
                'media' => [
                    'id' => $videoUrn,
                ],
            ],
        ];
        
        $url = "{$this->restApiUrl}/posts";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'LinkedIn-Version: 202401',
        ], $postData);
        
        return [
            'success' => true,
            'post_id' => $response['x-restli-id'] ?? $response['id'] ?? null,
            'platform' => 'linkedin',
        ];
    }
    
    /**
     * Share an article/link
     */
    public function shareArticle(string $accessToken, string $url, string $commentary = '', array $options = []): array
    {
        return $this->publishPost($accessToken, $commentary, [
            'article' => [
                'url' => $url,
                'title' => $options['title'] ?? '',
                'description' => $options['description'] ?? '',
            ],
        ]);
    }
    
    public function deletePost(string $accessToken, string $postId): bool
    {
        $url = "{$this->restApiUrl}/posts/{$postId}";
        
        try {
            $this->makeRequest('DELETE', $url, [
                "Authorization: Bearer $accessToken",
                'LinkedIn-Version: 202401',
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    
    public function getPostAnalytics(string $accessToken, string $postId): array
    {
        // Get share statistics
        $url = $this->buildUrl("{$this->restApiUrl}/socialActions/{$postId}", [
            'projection' => '(likesSummary,commentsSummary)',
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
            'LinkedIn-Version: 202401',
        ]);
        
        return [
            'post_id' => $postId,
            'likes' => $response['likesSummary']['totalLikes'] ?? 0,
            'comments' => $response['commentsSummary']['totalFirstLevelComments'] ?? 0,
        ];
    }
    
    public function getAccountAnalytics(string $accessToken, array $metrics = [], array $dateRange = []): array
    {
        // For organization pages, get page statistics
        if (!empty($this->config['organization_id'])) {
            $orgId = $this->config['organization_id'];
            
            $url = $this->buildUrl("{$this->apiUrl}/organizationalEntityShareStatistics", [
                'q' => 'organizationalEntity',
                'organizationalEntity' => "urn:li:organization:{$orgId}",
            ]);
            
            try {
                $response = $this->makeRequest('GET', $url, [
                    "Authorization: Bearer $accessToken",
                    'LinkedIn-Version: 202401',
                ]);
                
                return $response['elements'][0] ?? [];
            } catch (\Exception $e) {
                return [];
            }
        }
        
        // For personal profiles, limited analytics available
        $profile = $this->getProfile($accessToken);
        
        return [
            'profile' => $profile,
        ];
    }
    
    /**
     * Get organization follower statistics
     */
    public function getFollowerStatistics(string $accessToken, string $organizationId): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/organizationalEntityFollowerStatistics", [
            'q' => 'organizationalEntity',
            'organizationalEntity' => "urn:li:organization:{$organizationId}",
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
            'LinkedIn-Version: 202401',
        ]);
        
        return $response['elements'][0] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Engagement
    |--------------------------------------------------------------------------
    */
    
    public function getComments(string $accessToken, string $postId): array
    {
        $url = $this->buildUrl("{$this->restApiUrl}/socialActions/{$postId}/comments", [
            'projection' => '(elements*(actor~,message))',
        ]);
        
        $response = $this->makeRequest('GET', $url, [
            "Authorization: Bearer $accessToken",
            'LinkedIn-Version: 202401',
        ]);
        
        return $response['elements'] ?? [];
    }
    
    public function replyToComment(string $accessToken, string $commentId, string $message): array
    {
        // Extract post ID from comment
        $parts = explode(',', $commentId);
        $postId = $parts[0] ?? '';
        
        $url = "{$this->restApiUrl}/socialActions/{$postId}/comments";
        
        $response = $this->makeRequest('POST', $url, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'LinkedIn-Version: 202401',
        ], [
            'message' => [
                'text' => $message,
            ],
            'parentComment' => $commentId,
        ]);
        
        return [
            'success' => true,
            'comment_id' => $response['id'] ?? null,
        ];
    }
    
    /**
     * Like a post
     */
    public function likePost(string $accessToken, string $postId): bool
    {
        $profile = $this->getProfile($accessToken);
        $actorUrn = $profile['member_urn'] ?? null;
        
        if (!$actorUrn) {
            return false;
        }
        
        $url = "{$this->restApiUrl}/socialActions/{$postId}/likes";
        
        try {
            $this->makeRequest('POST', $url, [
                "Authorization: Bearer $accessToken",
                'Content-Type: application/json',
                'LinkedIn-Version: 202401',
            ], [
                'actor' => $actorUrn,
            ]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
