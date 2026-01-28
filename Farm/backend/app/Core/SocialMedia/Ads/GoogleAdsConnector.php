<?php

namespace PHPFrarm\Core\SocialMedia\Ads;

use PHPFrarm\Core\Logger;

/**
 * Google Ads Connector
 * 
 * Google Ads API integration for campaign management and reporting.
 * 
 * Features:
 * - Search, Display, Video, Shopping campaigns
 * - Keyword and audience targeting
 * - Conversion tracking
 * - Reporting and analytics
 * 
 * API Docs: https://developers.google.com/google-ads/api/docs/start
 * 
 * @package PHPFrarm\Core\SocialMedia\Ads
 */
class GoogleAdsConnector extends BaseAdsConnector
{
    protected string $platformName = 'Google Ads';
    
    private string $apiUrl = 'https://googleads.googleapis.com';
    private string $apiVersion = 'v15';
    
    protected function getConfigKey(): string
    {
        return 'google_ads';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['developer_token']) && 
               !empty($this->config['client_id']) && 
               !empty($this->config['client_secret']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $defaultScopes = [
            'https://www.googleapis.com/auth/adwords',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => bin2hex(random_bytes(16)),
        ];
        
        return $this->buildUrl('https://accounts.google.com/o/oauth2/v2/auth', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = 'https://oauth2.googleapis.com/token';
        
        $data = http_build_query([
            'code' => $code,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $url = 'https://oauth2.googleapis.com/token';
        
        $data = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        
        return $this->makeRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $data);
    }
    
    /**
     * Get API headers with authentication
     */
    private function getHeaders(string $accessToken): array
    {
        return [
            "Authorization: Bearer $accessToken",
            'developer-token: ' . $this->config['developer_token'],
            'Content-Type: application/json',
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Account Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdAccount(string $accessToken): array
    {
        $customerId = $this->config['customer_id'];
        
        $query = "SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone 
                  FROM customer 
                  WHERE customer.id = $customerId";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function getAdAccounts(string $accessToken): array
    {
        // List accessible customers
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers:listAccessibleCustomers";
        
        $response = $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
        
        return $response['resourceNames'] ?? [];
    }
    
    /**
     * Execute Google Ads Query Language (GAQL) query
     */
    private function executeQuery(string $accessToken, string $query): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/googleAds:search";
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), [
            'query' => $query,
        ]);
        
        return $response['results'] ?? [];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Management
    |--------------------------------------------------------------------------
    */
    
    public function getCampaigns(string $accessToken, array $filters = []): array
    {
        $query = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type,
                         campaign.start_date, campaign.end_date, campaign_budget.amount_micros
                  FROM campaign";
        
        if (!empty($filters['status'])) {
            $status = strtoupper($filters['status']);
            $query .= " WHERE campaign.status = '$status'";
        }
        
        $query .= " ORDER BY campaign.name";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function createCampaign(string $accessToken, array $campaignData): array
    {
        $customerId = $this->config['customer_id'];
        
        // First create budget
        $budgetId = $this->createCampaignBudget($accessToken, $campaignData['budget'] ?? []);
        
        if (!$budgetId) {
            return ['error' => 'Failed to create campaign budget'];
        }
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/campaigns:mutate";
        
        $data = [
            'operations' => [
                [
                    'create' => [
                        'name' => $campaignData['name'],
                        'advertisingChannelType' => $campaignData['channel_type'] ?? 'SEARCH',
                        'status' => $campaignData['status'] ?? 'PAUSED',
                        'campaignBudget' => "customers/{$customerId}/campaignBudgets/{$budgetId}",
                        'networkSettings' => [
                            'targetGoogleSearch' => $campaignData['target_google_search'] ?? true,
                            'targetSearchNetwork' => $campaignData['target_search_network'] ?? true,
                            'targetContentNetwork' => $campaignData['target_content_network'] ?? false,
                        ],
                    ],
                ],
            ],
        ];
        
        if (!empty($campaignData['start_date'])) {
            $data['operations'][0]['create']['startDate'] = $campaignData['start_date'];
        }
        
        if (!empty($campaignData['end_date'])) {
            $data['operations'][0]['create']['endDate'] = $campaignData['end_date'];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        Logger::info('Google Ads campaign created', [
            'campaign' => $response['results'][0] ?? null,
        ]);
        
        return $response;
    }
    
    /**
     * Create campaign budget
     */
    private function createCampaignBudget(string $accessToken, array $budgetData): ?string
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/campaignBudgets:mutate";
        
        $amountMicros = ($budgetData['amount'] ?? 100) * 1000000; // Convert to micros
        
        $data = [
            'operations' => [
                [
                    'create' => [
                        'name' => $budgetData['name'] ?? 'Budget ' . date('Y-m-d H:i:s'),
                        'amountMicros' => $amountMicros,
                        'deliveryMethod' => $budgetData['delivery_method'] ?? 'STANDARD',
                    ],
                ],
            ],
        ];
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        if (!empty($response['results'][0]['resourceName'])) {
            // Extract budget ID from resource name
            preg_match('/campaignBudgets\/(\d+)/', $response['results'][0]['resourceName'], $matches);
            return $matches[1] ?? null;
        }
        
        return null;
    }
    
    public function updateCampaign(string $accessToken, string $campaignId, array $updates): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/campaigns:mutate";
        
        $updateData = [
            'resourceName' => "customers/{$customerId}/campaigns/{$campaignId}",
        ];
        
        $updateMask = [];
        
        if (isset($updates['name'])) {
            $updateData['name'] = $updates['name'];
            $updateMask[] = 'name';
        }
        
        if (isset($updates['status'])) {
            $updateData['status'] = strtoupper($updates['status']);
            $updateMask[] = 'status';
        }
        
        $data = [
            'operations' => [
                [
                    'update' => $updateData,
                    'updateMask' => implode(',', $updateMask),
                ],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function pauseCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'PAUSED']);
        return !empty($response['results']);
    }
    
    public function resumeCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'ENABLED']);
        return !empty($response['results']);
    }
    
    public function deleteCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'REMOVED']);
        return !empty($response['results']);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Group Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdSets(string $accessToken, string $campaignId): array
    {
        $customerId = $this->config['customer_id'];
        
        $query = "SELECT ad_group.id, ad_group.name, ad_group.status, ad_group.type,
                         ad_group.cpc_bid_micros, ad_group.cpm_bid_micros
                  FROM ad_group
                  WHERE campaign.id = $campaignId
                  ORDER BY ad_group.name";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function createAdSet(string $accessToken, string $campaignId, array $adSetData): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/adGroups:mutate";
        
        $cpcBidMicros = ($adSetData['cpc_bid'] ?? 1) * 1000000;
        
        $data = [
            'operations' => [
                [
                    'create' => [
                        'name' => $adSetData['name'],
                        'campaign' => "customers/{$customerId}/campaigns/{$campaignId}",
                        'status' => $adSetData['status'] ?? 'PAUSED',
                        'type' => $adSetData['type'] ?? 'SEARCH_STANDARD',
                        'cpcBidMicros' => $cpcBidMicros,
                    ],
                ],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function updateAdSet(string $accessToken, string $adSetId, array $updates): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/adGroups:mutate";
        
        $updateData = [
            'resourceName' => "customers/{$customerId}/adGroups/{$adSetId}",
        ];
        
        $updateMask = [];
        
        if (isset($updates['name'])) {
            $updateData['name'] = $updates['name'];
            $updateMask[] = 'name';
        }
        
        if (isset($updates['status'])) {
            $updateData['status'] = strtoupper($updates['status']);
            $updateMask[] = 'status';
        }
        
        if (isset($updates['cpc_bid'])) {
            $updateData['cpcBidMicros'] = $updates['cpc_bid'] * 1000000;
            $updateMask[] = 'cpc_bid_micros';
        }
        
        $data = [
            'operations' => [
                [
                    'update' => $updateData,
                    'updateMask' => implode(',', $updateMask),
                ],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Management
    |--------------------------------------------------------------------------
    */
    
    public function getAds(string $accessToken, string $adGroupId): array
    {
        $query = "SELECT ad_group_ad.ad.id, ad_group_ad.ad.name, ad_group_ad.status,
                         ad_group_ad.ad.type, ad_group_ad.ad.final_urls
                  FROM ad_group_ad
                  WHERE ad_group.id = $adGroupId
                  ORDER BY ad_group_ad.ad.id";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function createAd(string $accessToken, string $adGroupId, array $adData): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/adGroupAds:mutate";
        
        // Create responsive search ad
        $ad = [
            'responsiveSearchAd' => [
                'headlines' => array_map(fn($h) => ['text' => $h], $adData['headlines'] ?? ['Headline 1', 'Headline 2', 'Headline 3']),
                'descriptions' => array_map(fn($d) => ['text' => $d], $adData['descriptions'] ?? ['Description 1', 'Description 2']),
            ],
            'finalUrls' => $adData['final_urls'] ?? [],
        ];
        
        if (!empty($adData['path1'])) {
            $ad['responsiveSearchAd']['path1'] = $adData['path1'];
        }
        
        if (!empty($adData['path2'])) {
            $ad['responsiveSearchAd']['path2'] = $adData['path2'];
        }
        
        $data = [
            'operations' => [
                [
                    'create' => [
                        'adGroup' => "customers/{$customerId}/adGroups/{$adGroupId}",
                        'status' => $adData['status'] ?? 'PAUSED',
                        'ad' => $ad,
                    ],
                ],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    public function updateAd(string $accessToken, string $adId, array $updates): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/adGroupAds:mutate";
        
        $updateData = [
            'resourceName' => "customers/{$customerId}/adGroupAds/{$adId}",
        ];
        
        $updateMask = [];
        
        if (isset($updates['status'])) {
            $updateData['status'] = strtoupper($updates['status']);
            $updateMask[] = 'status';
        }
        
        $data = [
            'operations' => [
                [
                    'update' => $updateData,
                    'updateMask' => implode(',', $updateMask),
                ],
            ],
        ];
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Keywords
    |--------------------------------------------------------------------------
    */
    
    /**
     * Add keywords to ad group
     */
    public function addKeywords(string $accessToken, string $adGroupId, array $keywords): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/customers/{$customerId}/adGroupCriteria:mutate";
        
        $operations = [];
        
        foreach ($keywords as $keyword) {
            $operations[] = [
                'create' => [
                    'adGroup' => "customers/{$customerId}/adGroups/{$adGroupId}",
                    'status' => 'ENABLED',
                    'keyword' => [
                        'text' => $keyword['text'],
                        'matchType' => $keyword['match_type'] ?? 'BROAD',
                    ],
                ],
            ];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), [
            'operations' => $operations,
        ]);
    }
    
    /**
     * Get keywords for ad group
     */
    public function getKeywords(string $accessToken, string $adGroupId): array
    {
        $query = "SELECT ad_group_criterion.criterion_id, ad_group_criterion.keyword.text,
                         ad_group_criterion.keyword.match_type, ad_group_criterion.status
                  FROM ad_group_criterion
                  WHERE ad_group.id = $adGroupId
                    AND ad_group_criterion.type = 'KEYWORD'";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Targeting
    |--------------------------------------------------------------------------
    */
    
    public function getTargetingOptions(string $accessToken, string $type): array
    {
        // Return targeting options based on type
        switch ($type) {
            case 'locations':
                return $this->searchLocations($accessToken, '');
            case 'languages':
                return $this->getLanguages($accessToken);
            default:
                return [];
        }
    }
    
    public function searchTargeting(string $accessToken, string $query, string $type = 'location'): array
    {
        if ($type === 'location') {
            return $this->searchLocations($accessToken, $query);
        }
        
        return [];
    }
    
    /**
     * Search for geo locations
     */
    private function searchLocations(string $accessToken, string $query): array
    {
        $customerId = $this->config['customer_id'];
        
        $url = "{$this->apiUrl}/{$this->apiVersion}/geoTargetConstants:suggest";
        
        $data = [
            'locale' => 'en',
            'countryCode' => 'US',
        ];
        
        if (!empty($query)) {
            $data['locationNames'] = ['names' => [$query]];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        return $response['geoTargetConstantSuggestions'] ?? [];
    }
    
    /**
     * Get available languages
     */
    private function getLanguages(string $accessToken): array
    {
        $query = "SELECT language_constant.id, language_constant.name, language_constant.code
                  FROM language_constant
                  WHERE language_constant.targetable = true";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Analytics & Reporting
    |--------------------------------------------------------------------------
    */
    
    public function getCampaignInsights(string $accessToken, string $campaignId, array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $query = "SELECT campaign.id, campaign.name, 
                         metrics.impressions, metrics.clicks, metrics.cost_micros,
                         metrics.ctr, metrics.average_cpc, metrics.conversions
                  FROM campaign
                  WHERE campaign.id = $campaignId
                    AND segments.date BETWEEN '$startDate' AND '$endDate'";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function getAdSetInsights(string $accessToken, string $adSetId, array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $query = "SELECT ad_group.id, ad_group.name,
                         metrics.impressions, metrics.clicks, metrics.cost_micros,
                         metrics.ctr, metrics.average_cpc, metrics.conversions
                  FROM ad_group
                  WHERE ad_group.id = $adSetId
                    AND segments.date BETWEEN '$startDate' AND '$endDate'";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function getAdInsights(string $accessToken, string $adId, array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $query = "SELECT ad_group_ad.ad.id,
                         metrics.impressions, metrics.clicks, metrics.cost_micros,
                         metrics.ctr, metrics.average_cpc, metrics.conversions
                  FROM ad_group_ad
                  WHERE ad_group_ad.ad.id = $adId
                    AND segments.date BETWEEN '$startDate' AND '$endDate'";
        
        return $this->executeQuery($accessToken, $query);
    }
    
    public function getAccountInsights(string $accessToken, array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $query = "SELECT customer.id, customer.descriptive_name,
                         metrics.impressions, metrics.clicks, metrics.cost_micros,
                         metrics.ctr, metrics.average_cpc, metrics.conversions
                  FROM customer
                  WHERE segments.date BETWEEN '$startDate' AND '$endDate'";
        
        return $this->executeQuery($accessToken, $query);
    }
}
