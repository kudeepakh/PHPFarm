<?php

namespace PHPFrarm\Core\SocialMedia\Ads;

use PHPFrarm\Core\Logger;

/**
 * TikTok Ads Connector
 * 
 * TikTok Marketing API integration for advertising campaigns.
 * 
 * Features:
 * - Campaign management
 * - Ad group and ad creation
 * - Audience targeting
 * - Spark Ads (boosting organic content)
 * - Reporting and analytics
 * 
 * API Docs: https://business-api.tiktok.com/portal/docs
 * 
 * @package PHPFrarm\Core\SocialMedia\Ads
 */
class TikTokAdsConnector extends BaseAdsConnector
{
    protected string $platformName = 'TikTok Ads';
    
    private string $apiUrl = 'https://business-api.tiktok.com/open_api';
    private string $apiVersion = 'v1.3';
    
    protected function getConfigKey(): string
    {
        return 'tiktok_ads';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['app_id']) && 
               !empty($this->config['app_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $params = [
            'app_id' => $this->config['app_id'],
            'redirect_uri' => $redirectUri,
            'state' => bin2hex(random_bytes(16)),
            'rid' => uniqid(),
        ];
        
        return $this->buildUrl('https://business-api.tiktok.com/portal/auth', $params);
    }
    
    public function getAccessToken(string $authCode, string $redirectUri = ''): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/oauth2/access_token/";
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], [
            'app_id' => $this->config['app_id'],
            'secret' => $this->config['app_secret'],
            'auth_code' => $authCode,
        ]);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/oauth2/refresh_token/";
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/json',
        ], [
            'app_id' => $this->config['app_id'],
            'secret' => $this->config['app_secret'],
            'refresh_token' => $refreshToken,
        ]);
    }
    
    /**
     * Get API headers with access token
     */
    private function getHeaders(string $accessToken): array
    {
        return [
            "Access-Token: $accessToken",
            'Content-Type: application/json',
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Account Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdAccount(string $accessToken): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/advertiser/info/";
        
        $params = [
            'advertiser_ids' => json_encode([$advertiserId]),
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function getAdAccounts(string $accessToken): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/oauth2/advertiser/get/";
        
        $params = [
            'app_id' => $this->config['app_id'],
            'secret' => $this->config['app_secret'],
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Management
    |--------------------------------------------------------------------------
    */
    
    public function getCampaigns(string $accessToken, array $filters = []): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/campaign/get/";
        
        $params = [
            'advertiser_id' => $advertiserId,
            'page' => $filters['page'] ?? 1,
            'page_size' => $filters['limit'] ?? 50,
        ];
        
        if (!empty($filters['status'])) {
            $params['filtering'] = json_encode([
                'status' => $filters['status'],
            ]);
        }
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function getCampaign(string $accessToken, string $campaignId): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/campaign/get/";
        
        $params = [
            'advertiser_id' => $advertiserId,
            'filtering' => json_encode([
                'campaign_ids' => [$campaignId],
            ]),
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function createCampaign(string $accessToken, array $campaignData): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/campaign/create/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'campaign_name' => $campaignData['name'],
            'objective_type' => $campaignData['objective'] ?? 'TRAFFIC',
            'budget_mode' => $campaignData['budget_mode'] ?? 'BUDGET_MODE_DAY',
            'budget' => $campaignData['budget'] ?? 50,
        ];
        
        // Optional fields
        if (!empty($campaignData['special_industries'])) {
            $data['special_industries'] = $campaignData['special_industries'];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        Logger::info('TikTok Ads campaign created', [
            'campaign_id' => $response['data']['campaign_id'] ?? null,
        ]);
        
        return $response;
    }
    
    public function updateCampaign(string $accessToken, string $campaignId, array $updates): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/campaign/update/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'campaign_id' => $campaignId,
        ];
        
        if (isset($updates['name'])) {
            $data['campaign_name'] = $updates['name'];
        }
        
        if (isset($updates['budget'])) {
            $data['budget'] = $updates['budget'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function pauseCampaign(string $accessToken, string $campaignId): bool
    {
        return $this->updateCampaignStatus($accessToken, $campaignId, 'DISABLE');
    }
    
    public function resumeCampaign(string $accessToken, string $campaignId): bool
    {
        return $this->updateCampaignStatus($accessToken, $campaignId, 'ENABLE');
    }
    
    public function deleteCampaign(string $accessToken, string $campaignId): bool
    {
        return $this->updateCampaignStatus($accessToken, $campaignId, 'DELETE');
    }
    
    /**
     * Update campaign status
     */
    private function updateCampaignStatus(string $accessToken, string $campaignId, string $status): bool
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/campaign/update/status/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'campaign_ids' => [$campaignId],
            'opt_status' => $status,
        ];
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        return ($response['code'] ?? 0) === 0;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Group Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdSets(string $accessToken, string $campaignId): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/adgroup/get/";
        
        $params = [
            'advertiser_id' => $advertiserId,
            'filtering' => json_encode([
                'campaign_ids' => [$campaignId],
            ]),
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function createAdSet(string $accessToken, string $campaignId, array $adSetData): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/adgroup/create/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'campaign_id' => $campaignId,
            'adgroup_name' => $adSetData['name'],
            'placement_type' => $adSetData['placement_type'] ?? 'PLACEMENT_TYPE_AUTOMATIC',
            'budget_mode' => $adSetData['budget_mode'] ?? 'BUDGET_MODE_DAY',
            'budget' => $adSetData['budget'] ?? 20,
            'schedule_type' => $adSetData['schedule_type'] ?? 'SCHEDULE_FROM_NOW',
            'billing_event' => $adSetData['billing_event'] ?? 'CPC',
            'bid_type' => $adSetData['bid_type'] ?? 'BID_TYPE_NO_BID',
            'optimization_goal' => $adSetData['optimization_goal'] ?? 'CLICK',
            'pacing' => $adSetData['pacing'] ?? 'PACING_MODE_SMOOTH',
        ];
        
        // Location targeting
        if (!empty($adSetData['location_ids'])) {
            $data['location_ids'] = $adSetData['location_ids'];
        }
        
        // Age targeting
        if (!empty($adSetData['age_groups'])) {
            $data['age_groups'] = $adSetData['age_groups'];
        }
        
        // Gender targeting
        if (isset($adSetData['gender'])) {
            $data['gender'] = $adSetData['gender'];
        }
        
        // Interest targeting
        if (!empty($adSetData['interest_category_ids'])) {
            $data['interest_category_ids'] = $adSetData['interest_category_ids'];
        }
        
        // Schedule
        if (!empty($adSetData['schedule_start_time'])) {
            $data['schedule_start_time'] = $adSetData['schedule_start_time'];
        }
        
        if (!empty($adSetData['schedule_end_time'])) {
            $data['schedule_end_time'] = $adSetData['schedule_end_time'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function updateAdSet(string $accessToken, string $adSetId, array $updates): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/adgroup/update/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'adgroup_id' => $adSetId,
        ];
        
        if (isset($updates['name'])) {
            $data['adgroup_name'] = $updates['name'];
        }
        
        if (isset($updates['budget'])) {
            $data['budget'] = $updates['budget'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Management
    |--------------------------------------------------------------------------
    */
    
    public function getAds(string $accessToken, string $adSetId): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/ad/get/";
        
        $params = [
            'advertiser_id' => $advertiserId,
            'filtering' => json_encode([
                'adgroup_ids' => [$adSetId],
            ]),
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function createAd(string $accessToken, string $adSetId, array $adData): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/ad/create/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'adgroup_id' => $adSetId,
            'ad_name' => $adData['name'],
            'ad_format' => $adData['format'] ?? 'SINGLE_VIDEO',
            'display_name' => $adData['display_name'] ?? '',
            'ad_text' => $adData['text'] ?? '',
            'video_id' => $adData['video_id'],
            'call_to_action' => $adData['call_to_action'] ?? 'LEARN_MORE',
        ];
        
        // Landing page
        if (!empty($adData['landing_page_url'])) {
            $data['landing_page_url'] = $adData['landing_page_url'];
        }
        
        // Thumbnail
        if (!empty($adData['image_ids'])) {
            $data['image_ids'] = $adData['image_ids'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function updateAd(string $accessToken, string $adId, array $updates): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/ad/update/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'ad_id' => $adId,
        ];
        
        if (isset($updates['name'])) {
            $data['ad_name'] = $updates['name'];
        }
        
        if (isset($updates['text'])) {
            $data['ad_text'] = $updates['text'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Spark Ads (Boosting Organic Content)
    |--------------------------------------------------------------------------
    */
    
    /**
     * Create Spark Ad (boost existing TikTok post)
     */
    public function createSparkAd(string $accessToken, string $adSetId, array $sparkData): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/ad/create/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'adgroup_id' => $adSetId,
            'ad_name' => $sparkData['name'],
            'ad_format' => 'SPARK_ADS',
            'tiktok_item_id' => $sparkData['tiktok_post_id'],
            'identity_id' => $sparkData['identity_id'],
            'identity_type' => $sparkData['identity_type'] ?? 'AUTH_CODE',
        ];
        
        if (!empty($sparkData['call_to_action'])) {
            $data['call_to_action'] = $sparkData['call_to_action'];
        }
        
        if (!empty($sparkData['landing_page_url'])) {
            $data['landing_page_url'] = $sparkData['landing_page_url'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Creative Assets
    |--------------------------------------------------------------------------
    */
    
    /**
     * Upload video creative
     */
    public function uploadVideo(string $accessToken, string $videoPath): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/file/video/ad/upload/";
        
        // Read file content
        $videoContent = file_get_contents($videoPath);
        $base64Video = base64_encode($videoContent);
        
        $data = [
            'advertiser_id' => $advertiserId,
            'upload_type' => 'UPLOAD_BY_FILE',
            'video_file' => $base64Video,
            'file_name' => basename($videoPath),
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /**
     * Upload image creative
     */
    public function uploadImage(string $accessToken, string $imagePath): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/file/image/ad/upload/";
        
        $imageContent = file_get_contents($imagePath);
        $base64Image = base64_encode($imageContent);
        
        $data = [
            'advertiser_id' => $advertiserId,
            'upload_type' => 'UPLOAD_BY_FILE',
            'image_file' => $base64Image,
            'file_name' => basename($imagePath),
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Targeting
    |--------------------------------------------------------------------------
    */
    
    public function getTargetingOptions(string $accessToken, string $type): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $endpoints = [
            'interest' => 'interest_category/',
            'action' => 'action_category/',
            'language' => 'language/',
        ];
        
        $endpoint = $endpoints[$type] ?? 'interest_category/';
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/targeting/{$endpoint}";
        
        $params = [
            'advertiser_id' => $advertiserId,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function searchTargeting(string $accessToken, string $query, string $type = 'interest'): array
    {
        return $this->getTargetingOptions($accessToken, $type);
    }
    
    /**
     * Get location targeting options
     */
    public function getLocations(string $accessToken, array $locationIds = []): array
    {
        $url = "{$this->apiUrl}/{$this->apiVersion}/region/";
        
        $params = [];
        
        if (!empty($locationIds)) {
            $params['location_ids'] = json_encode($locationIds);
        }
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
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
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/dmp/custom_audience/list/";
        
        $params = [
            'advertiser_id' => $advertiserId,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Create custom audience from file
     */
    public function createCustomAudience(string $accessToken, array $audienceData): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/dmp/custom_audience/create/";
        
        $data = [
            'advertiser_id' => $advertiserId,
            'custom_audience_name' => $audienceData['name'],
            'file_paths' => $audienceData['file_paths'] ?? [],
            'calculate_type' => $audienceData['calculate_type'] ?? 'NONE',
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Reporting
    |--------------------------------------------------------------------------
    */
    
    public function getCampaignInsights(string $accessToken, string $campaignId, array $dateRange = []): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/report/integrated/get/";
        
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $data = [
            'advertiser_id' => $advertiserId,
            'report_type' => 'BASIC',
            'dimensions' => ['campaign_id'],
            'data_level' => 'AUCTION_CAMPAIGN',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'metrics' => [
                'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
                'conversions', 'conversion_rate', 'cost_per_conversion',
                'reach', 'video_play_actions', 'video_watched_2s', 'video_watched_6s',
            ],
            'filtering' => [
                'campaign_ids' => [$campaignId],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function getAdSetInsights(string $accessToken, string $adSetId, array $dateRange = []): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/report/integrated/get/";
        
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $data = [
            'advertiser_id' => $advertiserId,
            'report_type' => 'BASIC',
            'dimensions' => ['adgroup_id'],
            'data_level' => 'AUCTION_ADGROUP',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'metrics' => [
                'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
                'conversions', 'conversion_rate', 'cost_per_conversion',
            ],
            'filtering' => [
                'adgroup_ids' => [$adSetId],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function getAdInsights(string $accessToken, string $adId, array $dateRange = []): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/report/integrated/get/";
        
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $data = [
            'advertiser_id' => $advertiserId,
            'report_type' => 'BASIC',
            'dimensions' => ['ad_id'],
            'data_level' => 'AUCTION_AD',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'metrics' => [
                'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
                'conversions', 'conversion_rate', 'cost_per_conversion',
                'video_play_actions', 'video_watched_2s', 'video_watched_6s',
            ],
            'filtering' => [
                'ad_ids' => [$adId],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function getAccountInsights(string $accessToken, array $dateRange = []): array
    {
        $advertiserId = $this->config['advertiser_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/report/integrated/get/";
        
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $data = [
            'advertiser_id' => $advertiserId,
            'report_type' => 'BASIC',
            'dimensions' => ['stat_time_day'],
            'data_level' => 'AUCTION_ADVERTISER',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'metrics' => [
                'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
                'conversions', 'conversion_rate', 'cost_per_conversion',
                'reach', 'frequency',
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
}
