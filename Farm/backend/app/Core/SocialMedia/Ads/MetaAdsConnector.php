<?php

namespace PHPFrarm\Core\SocialMedia\Ads;

use PHPFrarm\Core\Logger;

/**
 * Meta Ads Connector (Facebook & Instagram Ads)
 * 
 * Facebook Marketing API integration for ad campaign management.
 * 
 * Features:
 * - Campaign, Ad Set, and Ad management
 * - Audience targeting and custom audiences
 * - Creative management
 * - Insights and reporting
 * - Pixel and conversion tracking
 * 
 * API Docs: https://developers.facebook.com/docs/marketing-apis
 * 
 * @package PHPFrarm\Core\SocialMedia\Ads
 */
class MetaAdsConnector extends BaseAdsConnector
{
    protected string $platformName = 'Meta Ads';
    
    private string $apiUrl = 'https://graph.facebook.com';
    private string $apiVersion = 'v18.0';
    
    protected function getConfigKey(): string
    {
        return 'meta_ads';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['app_id']) && !empty($this->config['app_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'ads_management',
            'ads_read',
            'business_management',
            'pages_read_engagement',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['app_id'],
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'state' => bin2hex(random_bytes(16)),
        ];
        
        return $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/dialog/oauth", $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/oauth/access_token", [
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        // Facebook uses long-lived tokens, exchange short-lived for long-lived
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'fb_exchange_token' => $refreshToken,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Account Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdAccount(string $accessToken): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}", [
            'fields' => 'id,name,account_status,currency,timezone_name,spend_cap,amount_spent',
            'access_token' => $accessToken,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    public function getAdAccounts(string $accessToken): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/me/adaccounts", [
            'fields' => 'id,name,account_status,currency,timezone_name',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Management
    |--------------------------------------------------------------------------
    */
    
    public function getCampaigns(string $accessToken, array $filters = []): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $params = [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time,created_time',
            'access_token' => $accessToken,
        ];
        
        if (!empty($filters['status'])) {
            $params['filtering'] = json_encode([
                ['field' => 'effective_status', 'operator' => 'IN', 'value' => (array)$filters['status']],
            ]);
        }
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/campaigns", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function createCampaign(string $accessToken, array $campaignData): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/campaigns";
        
        $data = [
            'name' => $campaignData['name'],
            'objective' => $campaignData['objective'] ?? 'OUTCOME_TRAFFIC',
            'status' => $campaignData['status'] ?? 'PAUSED',
            'special_ad_categories' => $campaignData['special_ad_categories'] ?? [],
            'access_token' => $accessToken,
        ];
        
        if (!empty($campaignData['daily_budget'])) {
            $data['daily_budget'] = $campaignData['daily_budget'] * 100; // Convert to cents
        }
        
        if (!empty($campaignData['lifetime_budget'])) {
            $data['lifetime_budget'] = $campaignData['lifetime_budget'] * 100;
        }
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        Logger::info('Meta Ads campaign created', [
            'campaign_id' => $response['id'] ?? null,
        ]);
        
        return $response;
    }
    
    public function updateCampaign(string $accessToken, string $campaignId, array $updates): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/{$campaignId}";
        
        $updates['access_token'] = $accessToken;
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $updates);
    }
    
    public function pauseCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'PAUSED']);
        return isset($response['success']) ? $response['success'] : true;
    }
    
    public function resumeCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'ACTIVE']);
        return isset($response['success']) ? $response['success'] : true;
    }
    
    public function deleteCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'DELETED']);
        return isset($response['success']) ? $response['success'] : true;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Set Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdSets(string $accessToken, string $campaignId): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/{$campaignId}/adsets", [
            'fields' => 'id,name,status,daily_budget,lifetime_budget,targeting,optimization_goal,billing_event,bid_amount',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function createAdSet(string $accessToken, string $campaignId, array $adSetData): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/adsets";
        
        $data = [
            'name' => $adSetData['name'],
            'campaign_id' => $campaignId,
            'status' => $adSetData['status'] ?? 'PAUSED',
            'optimization_goal' => $adSetData['optimization_goal'] ?? 'LINK_CLICKS',
            'billing_event' => $adSetData['billing_event'] ?? 'IMPRESSIONS',
            'targeting' => json_encode($adSetData['targeting'] ?? ['geo_locations' => ['countries' => ['US']]]),
            'access_token' => $accessToken,
        ];
        
        if (!empty($adSetData['daily_budget'])) {
            $data['daily_budget'] = $adSetData['daily_budget'] * 100;
        }
        
        if (!empty($adSetData['start_time'])) {
            $data['start_time'] = $adSetData['start_time'];
        }
        
        if (!empty($adSetData['end_time'])) {
            $data['end_time'] = $adSetData['end_time'];
        }
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
    }
    
    public function updateAdSet(string $accessToken, string $adSetId, array $updates): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/{$adSetId}";
        
        $updates['access_token'] = $accessToken;
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $updates);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Management
    |--------------------------------------------------------------------------
    */
    
    public function getAds(string $accessToken, string $adSetId): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/{$adSetId}/ads", [
            'fields' => 'id,name,status,creative,adset_id',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function createAd(string $accessToken, string $adSetId, array $adData): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        // First, create ad creative
        $creativeId = $this->createAdCreative($accessToken, $adData['creative']);
        
        if (!$creativeId) {
            return ['error' => 'Failed to create ad creative'];
        }
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/ads";
        
        $data = [
            'name' => $adData['name'],
            'adset_id' => $adSetId,
            'creative' => json_encode(['creative_id' => $creativeId]),
            'status' => $adData['status'] ?? 'PAUSED',
            'access_token' => $accessToken,
        ];
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
    }
    
    /**
     * Create ad creative
     */
    public function createAdCreative(string $accessToken, array $creativeData): ?string
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/adcreatives";
        
        $data = [
            'name' => $creativeData['name'] ?? 'Creative',
            'object_story_spec' => json_encode([
                'page_id' => $creativeData['page_id'],
                'link_data' => [
                    'link' => $creativeData['link'],
                    'message' => $creativeData['message'] ?? '',
                    'name' => $creativeData['headline'] ?? '',
                    'description' => $creativeData['description'] ?? '',
                    'call_to_action' => [
                        'type' => $creativeData['cta'] ?? 'LEARN_MORE',
                    ],
                ],
            ]),
            'access_token' => $accessToken,
        ];
        
        if (!empty($creativeData['image_hash'])) {
            $data['object_story_spec'] = json_encode([
                'page_id' => $creativeData['page_id'],
                'link_data' => [
                    'link' => $creativeData['link'],
                    'message' => $creativeData['message'] ?? '',
                    'image_hash' => $creativeData['image_hash'],
                    'name' => $creativeData['headline'] ?? '',
                    'description' => $creativeData['description'] ?? '',
                ],
            ]);
        }
        
        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
        
        return $response['id'] ?? null;
    }
    
    public function updateAd(string $accessToken, string $adId, array $updates): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/{$adId}";
        
        $updates['access_token'] = $accessToken;
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $updates);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Targeting
    |--------------------------------------------------------------------------
    */
    
    public function getTargetingOptions(string $accessToken, string $type): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/search", [
            'type' => $type, // adgeolocation, adinterest, etc.
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    public function searchTargeting(string $accessToken, string $query, string $type = 'adinterest'): array
    {
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/search", [
            'type' => $type,
            'q' => $query,
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    /**
     * Get reach estimate for targeting
     */
    public function getReachEstimate(string $accessToken, array $targeting): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/reachestimate", [
            'targeting_spec' => json_encode($targeting),
            'access_token' => $accessToken,
        ]);
        
        return $this->makeRequest('GET', $url);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Custom Audiences
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get custom audiences
     */
    public function getCustomAudiences(string $accessToken): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/customaudiences", [
            'fields' => 'id,name,subtype,approximate_count,data_source',
            'access_token' => $accessToken,
        ]);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'] ?? [];
    }
    
    /**
     * Create custom audience
     */
    public function createCustomAudience(string $accessToken, string $name, string $subtype, array $options = []): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/customaudiences";
        
        $data = [
            'name' => $name,
            'subtype' => $subtype, // CUSTOM, WEBSITE, APP, OFFLINE_CONVERSION, LOOKALIKE
            'access_token' => $accessToken,
        ];
        
        if ($subtype === 'LOOKALIKE' && !empty($options['source_audience_id'])) {
            $data['origin_audience_id'] = $options['source_audience_id'];
            $data['lookalike_spec'] = json_encode([
                'type' => $options['type'] ?? 'similarity',
                'ratio' => $options['ratio'] ?? 0.01,
                'country' => $options['country'] ?? 'US',
            ]);
        }
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Reporting
    |--------------------------------------------------------------------------
    */
    
    public function getCampaignInsights(string $accessToken, string $campaignId, array $dateRange = []): array
    {
        $params = [
            'fields' => 'impressions,reach,clicks,spend,cpc,cpm,ctr,conversions,cost_per_conversion',
            'access_token' => $accessToken,
        ];
        
        if (!empty($dateRange['start']) && !empty($dateRange['end'])) {
            $params['time_range'] = json_encode([
                'since' => $dateRange['start'],
                'until' => $dateRange['end'],
            ]);
        }
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/{$campaignId}/insights", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'][0] ?? [];
    }
    
    public function getAdSetInsights(string $accessToken, string $adSetId, array $dateRange = []): array
    {
        $params = [
            'fields' => 'impressions,reach,clicks,spend,cpc,cpm,ctr,conversions',
            'access_token' => $accessToken,
        ];
        
        if (!empty($dateRange['start']) && !empty($dateRange['end'])) {
            $params['time_range'] = json_encode([
                'since' => $dateRange['start'],
                'until' => $dateRange['end'],
            ]);
        }
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/{$adSetId}/insights", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'][0] ?? [];
    }
    
    public function getAdInsights(string $accessToken, string $adId, array $dateRange = []): array
    {
        $params = [
            'fields' => 'impressions,reach,clicks,spend,cpc,cpm,ctr,conversions,actions',
            'access_token' => $accessToken,
        ];
        
        if (!empty($dateRange['start']) && !empty($dateRange['end'])) {
            $params['time_range'] = json_encode([
                'since' => $dateRange['start'],
                'until' => $dateRange['end'],
            ]);
        }
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/{$adId}/insights", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'][0] ?? [];
    }
    
    public function getAccountInsights(string $accessToken, array $dateRange = []): array
    {
        $adAccountId = $this->config['ad_account_id'];
        
        $params = [
            'fields' => 'impressions,reach,clicks,spend,cpc,cpm,ctr',
            'access_token' => $accessToken,
        ];
        
        if (!empty($dateRange['start']) && !empty($dateRange['end'])) {
            $params['time_range'] = json_encode([
                'since' => $dateRange['start'],
                'until' => $dateRange['end'],
            ]);
        }
        
        $url = $this->buildUrl("{$this->apiUrl}/{$this->apiVersion}/act_{$adAccountId}/insights", $params);
        
        $response = $this->makeRequest('GET', $url);
        
        return $response['data'][0] ?? [];
    }
}
