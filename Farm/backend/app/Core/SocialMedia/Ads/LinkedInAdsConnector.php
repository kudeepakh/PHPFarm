<?php

namespace PHPFrarm\Core\SocialMedia\Ads;

use PHPFrarm\Core\Logger;

/**
 * LinkedIn Ads Connector
 * 
 * LinkedIn Marketing API integration for B2B advertising campaigns.
 * 
 * Features:
 * - Sponsored Content campaigns
 * - Lead Gen Forms
 * - Professional audience targeting
 * - Company & Job function targeting
 * - Conversion tracking
 * 
 * API Docs: https://learn.microsoft.com/en-us/linkedin/marketing/
 * 
 * @package PHPFrarm\Core\SocialMedia\Ads
 */
class LinkedInAdsConnector extends BaseAdsConnector
{
    protected string $platformName = 'LinkedIn Ads';
    
    private string $apiUrl = 'https://api.linkedin.com/v2';
    private string $restApiUrl = 'https://api.linkedin.com/rest';
    
    protected function getConfigKey(): string
    {
        return 'linkedin_ads';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && 
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
            'r_ads',
            'rw_ads',
            'r_ads_reporting',
            'r_organization_admin',
        ];
        
        $scopes = array_merge($defaultScopes, $scopes);
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'state' => bin2hex(random_bytes(16)),
            'scope' => implode(' ', $scopes),
        ];
        
        return $this->buildUrl('https://www.linkedin.com/oauth/v2/authorization', $params);
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $url = 'https://www.linkedin.com/oauth/v2/accessToken';
        
        $data = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $redirectUri,
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
    
    /**
     * Get API headers
     */
    private function getHeaders(string $accessToken): array
    {
        return [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: 202401',
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Account Management
    |--------------------------------------------------------------------------
    */
    
    public function getAdAccount(string $accessToken): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/adAccountsV2/{$accountId}";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    public function getAdAccounts(string $accessToken): array
    {
        $url = "{$this->apiUrl}/adAccountsV2";
        
        $params = [
            'q' => 'search',
            'search' => '(status:(values:List(ACTIVE)))',
            'count' => 100,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Groups (Campaign Level in LinkedIn)
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get campaign groups
     */
    public function getCampaignGroups(string $accessToken, array $filters = []): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/adCampaignGroupsV2";
        
        $params = [
            'q' => 'search',
            'search' => "(account:(values:List(urn:li:sponsoredAccount:{$accountId})))",
            'count' => $filters['limit'] ?? 50,
        ];
        
        if (!empty($filters['status'])) {
            $params['search'] .= ",(status:(values:List({$filters['status']})))";
        }
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Create campaign group
     */
    public function createCampaignGroup(string $accessToken, array $groupData): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/adCampaignGroupsV2";
        
        $data = [
            'account' => "urn:li:sponsoredAccount:{$accountId}",
            'name' => $groupData['name'],
            'status' => $groupData['status'] ?? 'DRAFT',
        ];
        
        if (!empty($groupData['total_budget'])) {
            $data['totalBudget'] = [
                'amount' => (string)($groupData['total_budget'] * 100),
                'currencyCode' => $groupData['currency'] ?? 'USD',
            ];
        }
        
        if (!empty($groupData['run_schedule'])) {
            $data['runSchedule'] = $groupData['run_schedule'];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Management (Campaign in LinkedIn = Ad Set level)
    |--------------------------------------------------------------------------
    */
    
    public function getCampaigns(string $accessToken, array $filters = []): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/adCampaignsV2";
        
        $search = "(account:(values:List(urn:li:sponsoredAccount:{$accountId})))";
        
        if (!empty($filters['campaign_group_id'])) {
            $search .= ",(campaignGroup:(values:List(urn:li:sponsoredCampaignGroup:{$filters['campaign_group_id']})))";
        }
        
        if (!empty($filters['status'])) {
            $search .= ",(status:(values:List({$filters['status']})))";
        }
        
        $params = [
            'q' => 'search',
            'search' => $search,
            'count' => $filters['limit'] ?? 50,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function createCampaign(string $accessToken, array $campaignData): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/adCampaignsV2";
        
        $data = [
            'account' => "urn:li:sponsoredAccount:{$accountId}",
            'name' => $campaignData['name'],
            'status' => $campaignData['status'] ?? 'DRAFT',
            'type' => $campaignData['type'] ?? 'SPONSORED_UPDATES',
            'costType' => $campaignData['cost_type'] ?? 'CPM',
            'objectiveType' => $campaignData['objective'] ?? 'BRAND_AWARENESS',
        ];
        
        // Campaign group
        if (!empty($campaignData['campaign_group_id'])) {
            $data['campaignGroup'] = "urn:li:sponsoredCampaignGroup:{$campaignData['campaign_group_id']}";
        }
        
        // Budget
        if (!empty($campaignData['daily_budget'])) {
            $data['dailyBudget'] = [
                'amount' => (string)($campaignData['daily_budget'] * 100),
                'currencyCode' => $campaignData['currency'] ?? 'USD',
            ];
        }
        
        // Bid amount
        if (!empty($campaignData['bid_amount'])) {
            $data['unitCost'] = [
                'amount' => (string)($campaignData['bid_amount'] * 100),
                'currencyCode' => $campaignData['currency'] ?? 'USD',
            ];
        }
        
        // Targeting
        if (!empty($campaignData['targeting'])) {
            $data['targetingCriteria'] = $this->buildTargetingCriteria($campaignData['targeting']);
        }
        
        // Schedule
        if (!empty($campaignData['run_schedule'])) {
            $data['runSchedule'] = $campaignData['run_schedule'];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        Logger::info('LinkedIn campaign created', [
            'campaign_id' => $response['id'] ?? null,
        ]);
        
        return $response;
    }
    
    public function updateCampaign(string $accessToken, string $campaignId, array $updates): array
    {
        $url = "{$this->apiUrl}/adCampaignsV2/{$campaignId}";
        
        $data = [];
        
        if (isset($updates['name'])) {
            $data['name'] = $updates['name'];
        }
        
        if (isset($updates['status'])) {
            $data['status'] = strtoupper($updates['status']);
        }
        
        if (isset($updates['daily_budget'])) {
            $data['dailyBudget'] = [
                'amount' => (string)($updates['daily_budget'] * 100),
                'currencyCode' => $updates['currency'] ?? 'USD',
            ];
        }
        
        // LinkedIn uses partial update with POST
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), [
            'patch' => ['$set' => $data],
        ]);
    }
    
    public function pauseCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'PAUSED']);
        return !empty($response);
    }
    
    public function resumeCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'ACTIVE']);
        return !empty($response);
    }
    
    public function deleteCampaign(string $accessToken, string $campaignId): bool
    {
        $response = $this->updateCampaign($accessToken, $campaignId, ['status' => 'ARCHIVED']);
        return !empty($response);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Sets (Mapped to LinkedIn Campaigns)
    |--------------------------------------------------------------------------
    */
    
    public function getAdSets(string $accessToken, string $campaignGroupId): array
    {
        return $this->getCampaigns($accessToken, [
            'campaign_group_id' => $campaignGroupId,
        ]);
    }
    
    public function createAdSet(string $accessToken, string $campaignGroupId, array $adSetData): array
    {
        $adSetData['campaign_group_id'] = $campaignGroupId;
        return $this->createCampaign($accessToken, $adSetData);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Ad Creative Management
    |--------------------------------------------------------------------------
    */
    
    public function getAds(string $accessToken, string $campaignId): array
    {
        $url = "{$this->apiUrl}/adCreativesV2";
        
        $params = [
            'q' => 'search',
            'search' => "(campaign:(values:List(urn:li:sponsoredCampaign:{$campaignId})))",
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function createAd(string $accessToken, string $campaignId, array $adData): array
    {
        $url = "{$this->apiUrl}/adCreativesV2";
        
        $data = [
            'campaign' => "urn:li:sponsoredCampaign:{$campaignId}",
            'status' => $adData['status'] ?? 'ACTIVE',
            'type' => $adData['type'] ?? 'SPONSORED_STATUS_UPDATE',
        ];
        
        // For sponsored content
        if (!empty($adData['share_urn'])) {
            $data['reference'] = $adData['share_urn'];
        }
        
        // For direct sponsored content
        if (!empty($adData['content'])) {
            $data['variables'] = [
                'data' => [
                    'com.linkedin.ads.SponsoredUpdateCreativeVariables' => [
                        'activity' => $this->createShare($accessToken, $adData['content']),
                    ],
                ],
            ];
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
    }
    
    /**
     * Create a share (post) for ad creative
     */
    private function createShare(string $accessToken, array $content): string
    {
        $url = "{$this->apiUrl}/shares";
        
        $data = [
            'owner' => "urn:li:organization:{$this->config['organization_id']}",
            'text' => ['text' => $content['text'] ?? ''],
            'distribution' => [
                'linkedInDistributionTarget' => new \stdClass(),
            ],
        ];
        
        if (!empty($content['url'])) {
            $data['content'] = [
                'contentEntities' => [
                    [
                        'entityLocation' => $content['url'],
                    ],
                ],
            ];
        }
        
        $response = $this->makeRequest('POST', $url, $this->getHeaders($accessToken), $data);
        
        return $response['activity'] ?? '';
    }
    
    public function updateAd(string $accessToken, string $adId, array $updates): array
    {
        $url = "{$this->apiUrl}/adCreativesV2/{$adId}";
        
        $data = [];
        
        if (isset($updates['status'])) {
            $data['status'] = strtoupper($updates['status']);
        }
        
        return $this->makeRequest('POST', $url, $this->getHeaders($accessToken), [
            'patch' => ['$set' => $data],
        ]);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Lead Gen Forms
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get lead gen forms
     */
    public function getLeadGenForms(string $accessToken): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/leadFormResponsesV2";
        
        $params = [
            'q' => 'account',
            'account' => "urn:li:sponsoredAccount:{$accountId}",
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Get lead gen form responses
     */
    public function getLeadGenResponses(string $accessToken, string $formId, array $filters = []): array
    {
        $url = "{$this->apiUrl}/leadFormResponsesV2";
        
        $params = [
            'q' => 'form',
            'form' => "urn:li:leadGenForm:{$formId}",
            'count' => $filters['limit'] ?? 100,
        ];
        
        if (!empty($filters['start'])) {
            $params['start'] = $filters['start'];
        }
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Targeting
    |--------------------------------------------------------------------------
    */
    
    public function getTargetingOptions(string $accessToken, string $type): array
    {
        $endpoints = [
            'locations' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:locations',
            'industries' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:industries',
            'skills' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:skills',
            'degrees' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:degrees',
            'jobFunctions' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:jobFunctions',
            'seniorities' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:seniorities',
            'titles' => 'adTargetingEntities?q=adTargetingFacet&adTargetingFacet=urn:li:adTargetingFacet:titles',
        ];
        
        $endpoint = $endpoints[$type] ?? $endpoints['locations'];
        
        $url = "{$this->apiUrl}/{$endpoint}";
        
        return $this->makeRequest('GET', $url, $this->getHeaders($accessToken));
    }
    
    public function searchTargeting(string $accessToken, string $query, string $type = 'locations'): array
    {
        $facetUrns = [
            'locations' => 'urn:li:adTargetingFacet:locations',
            'companies' => 'urn:li:adTargetingFacet:employers',
            'skills' => 'urn:li:adTargetingFacet:skills',
            'titles' => 'urn:li:adTargetingFacet:titles',
            'schools' => 'urn:li:adTargetingFacet:schools',
        ];
        
        $facetUrn = $facetUrns[$type] ?? $facetUrns['locations'];
        
        $url = "{$this->apiUrl}/adTargetingEntities";
        
        $params = [
            'q' => 'adTargetingFacetWithTypeahead',
            'adTargetingFacet' => $facetUrn,
            'query' => $query,
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Build targeting criteria from simplified array
     */
    private function buildTargetingCriteria(array $targeting): array
    {
        $criteria = [
            'include' => [
                'and' => [],
            ],
        ];
        
        // Locations
        if (!empty($targeting['locations'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:locations' => $targeting['locations'],
                ],
            ];
        }
        
        // Industries
        if (!empty($targeting['industries'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:industries' => $targeting['industries'],
                ],
            ];
        }
        
        // Job functions
        if (!empty($targeting['job_functions'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:jobFunctions' => $targeting['job_functions'],
                ],
            ];
        }
        
        // Seniorities
        if (!empty($targeting['seniorities'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:seniorities' => $targeting['seniorities'],
                ],
            ];
        }
        
        // Company size
        if (!empty($targeting['company_sizes'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:staffCountRanges' => $targeting['company_sizes'],
                ],
            ];
        }
        
        // Titles
        if (!empty($targeting['titles'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:titles' => $targeting['titles'],
                ],
            ];
        }
        
        // Skills
        if (!empty($targeting['skills'])) {
            $criteria['include']['and'][] = [
                'or' => [
                    'urn:li:adTargetingFacet:skills' => $targeting['skills'],
                ],
            ];
        }
        
        // Exclude
        if (!empty($targeting['exclude'])) {
            $criteria['exclude'] = $targeting['exclude'];
        }
        
        return $criteria;
    }
    
    /**
     * Get reach estimate for targeting criteria
     */
    public function getReachEstimate(string $accessToken, array $targeting): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/adAudienceCount";
        
        $params = [
            'account' => "urn:li:sponsoredAccount:{$accountId}",
            'targetingCriteria' => json_encode($this->buildTargetingCriteria($targeting)),
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Matched Audiences
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get matched audiences (custom audiences)
     */
    public function getCustomAudiences(string $accessToken): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/dmpSegments";
        
        $params = [
            'q' => 'account',
            'account' => "urn:li:sponsoredAccount:{$accountId}",
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Create matched audience from contact list
     */
    public function createCustomAudience(string $accessToken, array $audienceData): array
    {
        $accountId = $this->config['account_id'];
        
        $url = "{$this->apiUrl}/dmpSegments";
        
        $data = [
            'account' => "urn:li:sponsoredAccount:{$accountId}",
            'name' => $audienceData['name'],
            'type' => $audienceData['type'] ?? 'CONTACT_LIST',
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
        $accountId = $this->config['account_id'];
        
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $url = "{$this->apiUrl}/adAnalyticsV2";
        
        $params = [
            'q' => 'analytics',
            'pivot' => 'CAMPAIGN',
            'dateRange' => "(start:(year:" . date('Y', strtotime($startDate)) . ",month:" . (int)date('m', strtotime($startDate)) . ",day:" . (int)date('d', strtotime($startDate)) . "),end:(year:" . date('Y', strtotime($endDate)) . ",month:" . (int)date('m', strtotime($endDate)) . ",day:" . (int)date('d', strtotime($endDate)) . "))",
            'campaigns' => "List(urn:li:sponsoredCampaign:{$campaignId})",
            'fields' => 'impressions,clicks,costInUsd,dateRange,pivotValue',
            'timeGranularity' => 'DAILY',
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function getAdSetInsights(string $accessToken, string $adSetId, array $dateRange = []): array
    {
        // In LinkedIn, campaigns are at the ad set level
        return $this->getCampaignInsights($accessToken, $adSetId, $dateRange);
    }
    
    public function getAdInsights(string $accessToken, string $adId, array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $url = "{$this->apiUrl}/adAnalyticsV2";
        
        $params = [
            'q' => 'analytics',
            'pivot' => 'CREATIVE',
            'dateRange' => "(start:(year:" . date('Y', strtotime($startDate)) . ",month:" . (int)date('m', strtotime($startDate)) . ",day:" . (int)date('d', strtotime($startDate)) . "),end:(year:" . date('Y', strtotime($endDate)) . ",month:" . (int)date('m', strtotime($endDate)) . ",day:" . (int)date('d', strtotime($endDate)) . "))",
            'creatives' => "List(urn:li:sponsoredCreative:{$adId})",
            'fields' => 'impressions,clicks,costInUsd,dateRange,pivotValue,externalWebsiteConversions,leadGenerationMailContactInfoShares',
            'timeGranularity' => 'DAILY',
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    public function getAccountInsights(string $accessToken, array $dateRange = []): array
    {
        $accountId = $this->config['account_id'];
        
        $startDate = $dateRange['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $dateRange['end'] ?? date('Y-m-d');
        
        $url = "{$this->apiUrl}/adAnalyticsV2";
        
        $params = [
            'q' => 'analytics',
            'pivot' => 'ACCOUNT',
            'dateRange' => "(start:(year:" . date('Y', strtotime($startDate)) . ",month:" . (int)date('m', strtotime($startDate)) . ",day:" . (int)date('d', strtotime($startDate)) . "),end:(year:" . date('Y', strtotime($endDate)) . ",month:" . (int)date('m', strtotime($endDate)) . ",day:" . (int)date('d', strtotime($endDate)) . "))",
            'accounts' => "List(urn:li:sponsoredAccount:{$accountId})",
            'fields' => 'impressions,clicks,costInUsd,dateRange,pivotValue,externalWebsiteConversions,leadGenerationMailContactInfoShares',
            'timeGranularity' => 'DAILY',
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
    
    /**
     * Get demographic insights
     */
    public function getDemographicInsights(string $accessToken, string $campaignId, string $pivot = 'MEMBER_INDUSTRY'): array
    {
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        
        $url = "{$this->apiUrl}/adAnalyticsV2";
        
        // Valid pivots: MEMBER_COMPANY, MEMBER_INDUSTRY, MEMBER_SENIORITY, MEMBER_JOB_TITLE, MEMBER_JOB_FUNCTION
        
        $params = [
            'q' => 'analytics',
            'pivot' => $pivot,
            'dateRange' => "(start:(year:" . date('Y', strtotime($startDate)) . ",month:" . (int)date('m', strtotime($startDate)) . ",day:" . (int)date('d', strtotime($startDate)) . "),end:(year:" . date('Y', strtotime($endDate)) . ",month:" . (int)date('m', strtotime($endDate)) . ",day:" . (int)date('d', strtotime($endDate)) . "))",
            'campaigns' => "List(urn:li:sponsoredCampaign:{$campaignId})",
            'fields' => 'impressions,clicks,costInUsd,pivotValue',
        ];
        
        return $this->makeRequest('GET', $this->buildUrl($url, $params), $this->getHeaders($accessToken));
    }
}
