<?php

namespace Farm\Backend\App\Core\Security;

use App\Core\Cache\CacheManager;
use Farm\Backend\App\Core\Logging\LogManager;
use Farm\Backend\App\Core\Observability\TraceContext;

/**
 * GeoBlocker - Country-based access control
 * 
 * Features:
 * - IP geolocation (country detection)
 * - Country blacklist/whitelist
 * - Continent-based blocking
 * - Caching for performance
 * 
 * Integration options:
 * - MaxMind GeoIP2 (recommended)
 * - ip-api.com (free tier)
 * - Custom geolocation service
 * 
 * Thread-safe, cached geolocation results.
 */
class GeoBlocker
{
    private CacheManager $cache;
    private LogManager $logger;
    private array $config;
    
    // Cache keys
    private const GEO_CACHE_PREFIX = 'geo:ip:';
    private const BLOCKED_COUNTRIES_KEY = 'geo:blocked_countries';
    private const ALLOWED_COUNTRIES_KEY = 'geo:allowed_countries';
    
    // Continent codes
    private const CONTINENTS = [
        'AF' => 'Africa',
        'AS' => 'Asia',
        'EU' => 'Europe',
        'NA' => 'North America',
        'SA' => 'South America',
        'OC' => 'Oceania',
        'AN' => 'Antarctica',
    ];

    public function __construct(CacheManager $cache, LogManager $logger, array $config = [])
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->config = array_merge([
            'enabled' => true,
            'mode' => 'blacklist',            // 'blacklist' or 'whitelist'
            'blocked_countries' => [],        // ISO 3166-1 alpha-2 codes
            'allowed_countries' => [],        // ISO 3166-1 alpha-2 codes
            'cache_ttl' => 86400,             // Cache geolocation for 24 hours
            'provider' => 'ip-api',           // 'ip-api', 'maxmind', 'custom'
            'fallback_country' => null,       // Country if detection fails
            'log_blocks' => true,
        ], $config);
    }

    /**
     * Check if IP is blocked by geo-location
     * 
     * @param string $ip
     * @return bool True if blocked
     */
    public function isBlocked(string $ip): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }
        
        $country = $this->getCountry($ip);
        
        if ($country === null) {
            // Detection failed - allow or block based on fallback
            return $this->config['fallback_country'] === null ? false : true;
        }
        
        // Whitelist mode: Only allow specific countries
        if ($this->config['mode'] === 'whitelist') {
            $allowed = $this->getAllowedCountries();
            $isBlocked = !in_array($country, $allowed, true);
            
            if ($isBlocked && $this->config['log_blocks']) {
                $this->logBlock($ip, $country, 'not_in_whitelist');
            }
            
            return $isBlocked;
        }
        
        // Blacklist mode: Block specific countries
        $blocked = $this->getBlockedCountries();
        $isBlocked = in_array($country, $blocked, true);
        
        if ($isBlocked && $this->config['log_blocks']) {
            $this->logBlock($ip, $country, 'in_blacklist');
        }
        
        return $isBlocked;
    }

    /**
     * Get country code for IP address
     * 
     * @param string $ip
     * @return string|null ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB')
     */
    public function getCountry(string $ip): ?string
    {
        // Check cache first
        $cacheKey = self::GEO_CACHE_PREFIX . $ip;
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Perform geolocation lookup
        $country = $this->lookupCountry($ip);
        
        // Cache result
        if ($country !== null) {
            $this->cache->set($cacheKey, $country, $this->config['cache_ttl']);
        }
        
        return $country;
    }

    /**
     * Lookup country code using configured provider
     * 
     * @param string $ip
     * @return string|null Country code
     */
    private function lookupCountry(string $ip): ?string
    {
        // Localhost / private IPs
        if ($this->isPrivateIp($ip)) {
            return $this->config['fallback_country'];
        }
        
        switch ($this->config['provider']) {
            case 'ip-api':
                return $this->lookupIpApi($ip);
            
            case 'maxmind':
                return $this->lookupMaxMind($ip);
            
            case 'custom':
                // Implement custom provider logic
                return $this->config['fallback_country'];
            
            default:
                return null;
        }
    }

    /**
     * Lookup country using ip-api.com (free tier)
     * 
     * Rate limit: 45 requests per minute
     * 
     * @param string $ip
     * @return string|null
     */
    private function lookupIpApi(string $ip): ?string
    {
        try {
            $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 2]
            ]));
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if ($data['status'] === 'success') {
                return $data['countryCode'];
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('geo_lookup_failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Lookup country using MaxMind GeoIP2
     * 
     * Requires: composer require geoip2/geoip2
     * 
     * @param string $ip
     * @return string|null
     */
    private function lookupMaxMind(string $ip): ?string
    {
        // NOTE: MaxMind GeoIP2 is an optional premium feature
        // To enable:
        // 1. Install: composer require geoip2/geoip2
        // 2. Download MaxMind GeoLite2 database or use API key
        // 3. Configure in config/ddos.php: 'geoip_provider' => 'maxmind'
        // 4. Set database path or API credentials
        // Currently uses fallback country from config
        
        return $this->config['fallback_country'];
    }

    /**
     * Check if IP is private/local
     * 
     * @param string $ip
     * @return bool
     */
    private function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Add country to block list
     * 
     * @param string $countryCode ISO 3166-1 alpha-2 code
     */
    public function blockCountry(string $countryCode): void
    {
        $countryCode = strtoupper($countryCode);
        $this->cache->sAdd(self::BLOCKED_COUNTRIES_KEY, $countryCode);
        
        $this->logger->security('country_blocked', [
            'country_code' => $countryCode,
            'correlation_id' => TraceContext::getCorrelationId(),
        ]);
    }

    /**
     * Remove country from block list
     * 
     * @param string $countryCode
     */
    public function unblockCountry(string $countryCode): void
    {
        $countryCode = strtoupper($countryCode);
        $this->cache->sRem(self::BLOCKED_COUNTRIES_KEY, $countryCode);
    }

    /**
     * Add country to allow list (whitelist mode)
     * 
     * @param string $countryCode
     */
    public function allowCountry(string $countryCode): void
    {
        $countryCode = strtoupper($countryCode);
        $this->cache->sAdd(self::ALLOWED_COUNTRIES_KEY, $countryCode);
    }

    /**
     * Get list of blocked countries
     * 
     * @return array Country codes
     */
    public function getBlockedCountries(): array
    {
        $cached = $this->cache->sMembers(self::BLOCKED_COUNTRIES_KEY);
        $config = $this->config['blocked_countries'];
        
        return array_unique(array_merge($cached ?? [], $config));
    }

    /**
     * Get list of allowed countries (whitelist mode)
     * 
     * @return array Country codes
     */
    public function getAllowedCountries(): array
    {
        $cached = $this->cache->sMembers(self::ALLOWED_COUNTRIES_KEY);
        $config = $this->config['allowed_countries'];
        
        return array_unique(array_merge($cached ?? [], $config));
    }

    /**
     * Get geolocation details for IP
     * 
     * @param string $ip
     * @return array Geo information
     */
    public function getGeoInfo(string $ip): array
    {
        $country = $this->getCountry($ip);
        $isBlocked = $this->isBlocked($ip);
        
        return [
            'ip' => $ip,
            'country_code' => $country,
            'is_blocked' => $isBlocked,
            'mode' => $this->config['mode'],
            'provider' => $this->config['provider'],
        ];
    }

    /**
     * Log blocked request
     * 
     * @param string $ip
     * @param string $country
     * @param string $reason
     */
    private function logBlock(string $ip, string $country, string $reason): void
    {
        $this->logger->security('geo_blocked', [
            'ip' => $ip,
            'country' => $country,
            'reason' => $reason,
            'mode' => $this->config['mode'],
            'correlation_id' => TraceContext::getCorrelationId(),
            'timestamp' => time(),
        ]);
    }

    /**
     * Clear geo cache for specific IP
     * 
     * @param string $ip
     */
    public function clearCache(string $ip): void
    {
        $this->cache->delete(self::GEO_CACHE_PREFIX . $ip);
    }

    /**
     * Get statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'enabled' => $this->config['enabled'],
            'mode' => $this->config['mode'],
            'blocked_countries_count' => count($this->getBlockedCountries()),
            'allowed_countries_count' => count($this->getAllowedCountries()),
            'provider' => $this->config['provider'],
            'cache_ttl' => $this->config['cache_ttl'],
        ];
    }
}
