# API Details (Parsed from Code)
Generated: 2026-01-24 09:20:04

## Coverage Summary
- Total routes: 158
- Attribute routes: 158
- routes.php routes: 0
- Controllers without routes: 0

## Required Headers
- X-Correlation-Id (ULID, required)
- X-Transaction-Id (ULID, required)
- X-Request-Id (ULID, required)
- Accept: application/json
- Authorization: Bearer <token> (required for protected endpoints)

## Standard Response Envelope
- success: boolean
- message: string
- data: object|array|null
- meta: object (timestamp, api_version, locale, pagination if applicable)
- trace: object (correlation_id, transaction_id, request_id)

## /account/deactivate
- **POST** — No description
  - Handler: AccountStatusController::deactivateOwnAccount
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/locking/config
- **GET** — No description
  - Handler: LockingController::getConfiguration
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/locking/conflicts/rate
- **GET** — No description
  - Handler: LockingController::getConflictRate
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/locking/conflicts/top
- **GET** — No description
  - Handler: LockingController::getTopConflicts
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/locking/conflicts/{entityType}/{entityId}
- **GET** — No description
  - Handler: LockingController::getEntityConflicts
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/locking/health
- **GET** — No description
  - Handler: LockingController::getHealthStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/locking/statistics
- **GET** — No description
  - Handler: LockingController::getStatistics
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/locking/statistics/reset
- **POST** — No description
  - Handler: LockingController::resetStatistics
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\LockingController.php

## /admin/otp/blacklist
- **GET** — No description
  - Handler: OTPAdminController::getBlacklist
  - Middleware: auth, permission:otp:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

- **POST** — No description
  - Handler: OTPAdminController::addToBlacklist
  - Middleware: auth, permission:otp:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

## /admin/otp/blacklist/{blacklistId}
- **DELETE** — No description
  - Handler: OTPAdminController::removeFromBlacklist
  - Middleware: auth, permission:otp:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

## /admin/otp/check-status
- **POST** — No description
  - Handler: OTPAdminController::checkStatus
  - Middleware: auth, permission:otp:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

## /admin/otp/cleanup
- **POST** — No description
  - Handler: OTPAdminController::cleanup
  - Middleware: auth, permission:otp:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

## /admin/otp/history
- **GET** — No description
  - Handler: OTPAdminController::getHistory
  - Middleware: auth, permission:otp:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

## /admin/otp/statistics
- **GET** — No description
  - Handler: OTPAdminController::getStatistics
  - Middleware: auth, permission:otp:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPAdminController.php

## /admin/security/anomaly/ip/{ip}
- **DELETE** — No description
  - Handler: SecurityController::clearAnomalyTracking
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

- **GET** — No description
  - Handler: SecurityController::getAnomalyAnalysis
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/bot/analyze
- **POST** — No description
  - Handler: SecurityController::analyzeBotTraffic
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/geo/allow
- **POST** — No description
  - Handler: SecurityController::allowCountry
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/geo/allowed
- **GET** — No description
  - Handler: SecurityController::listAllowedCountries
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/geo/block
- **POST** — No description
  - Handler: SecurityController::blockCountry
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/geo/block/{country}
- **DELETE** — No description
  - Handler: SecurityController::unblockCountry
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/geo/blocked
- **GET** — No description
  - Handler: SecurityController::listBlockedCountries
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/health
- **GET** — No description
  - Handler: SecurityController::healthCheck
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/ip/blacklist
- **DELETE** — No description
  - Handler: SecurityController::clearBlacklist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

- **GET** — No description
  - Handler: SecurityController::listBlacklist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

- **POST** — No description
  - Handler: SecurityController::addToBlacklist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/ip/blacklist/{ip}
- **DELETE** — No description
  - Handler: SecurityController::removeFromBlacklist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/ip/whitelist
- **GET** — No description
  - Handler: SecurityController::listWhitelist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

- **POST** — No description
  - Handler: SecurityController::addToWhitelist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/ip/whitelist/{ip}
- **DELETE** — No description
  - Handler: SecurityController::removeFromWhitelist
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/ip/{ip}
- **GET** — No description
  - Handler: SecurityController::getIpAnalysis
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/overview
- **GET** — No description
  - Handler: SecurityController::overview
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/waf/rule
- **POST** — No description
  - Handler: SecurityController::addWafRule
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/security/waf/scan
- **POST** — No description
  - Handler: SecurityController::testWafScan
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\SecurityController.php

## /admin/traffic/quota/custom
- **POST** — No description
  - Handler: TrafficController::setCustomQuota
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/quota/reset/{clientId}
- **POST** — No description
  - Handler: TrafficController::resetQuota
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/quota/stats
- **GET** — No description
  - Handler: TrafficController::getQuotaStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/quota/status/{clientId}
- **GET** — No description
  - Handler: TrafficController::getQuotaStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/quota/tier
- **POST** — No description
  - Handler: TrafficController::setQuotaTier
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/quota/tiers
- **GET** — No description
  - Handler: TrafficController::getQuotaTiers
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/rate-limit/reset/{identifier}
- **POST** — No description
  - Handler: TrafficController::resetRateLimit
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/rate-limit/stats
- **GET** — No description
  - Handler: TrafficController::getRateLimitStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/rate-limit/status/{identifier}
- **GET** — No description
  - Handler: TrafficController::getRateLimitStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/reset-all/{identifier}
- **POST** — No description
  - Handler: TrafficController::resetAllTraffic
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/stats/summary
- **GET** — No description
  - Handler: TrafficController::getTrafficSummary
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/status/{identifier}
- **GET** — No description
  - Handler: TrafficController::getTrafficStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/throttle/reset/{identifier}
- **POST** — No description
  - Handler: TrafficController::resetThrottle
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/throttle/stats
- **GET** — No description
  - Handler: TrafficController::getThrottleStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/traffic/throttle/status/{identifier}
- **GET** — No description
  - Handler: TrafficController::getThrottleStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\TrafficController.php

## /admin/users/{userId}/activate
- **POST** — No description
  - Handler: AccountStatusController::activateAccount
  - Middleware: auth, permission:users:activate
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/users/{userId}/check-access
- **GET** — No description
  - Handler: AccountStatusController::checkAccess
  - Middleware: auth, permission:users:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/users/{userId}/identifiers
- **GET** — No description
  - Handler: AccountStatusController::getUserIdentifiers
  - Middleware: auth, permission:users:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/users/{userId}/lock
- **POST** — No description
  - Handler: AccountStatusController::lockAccount
  - Middleware: auth, permission:users:lock
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/users/{userId}/status-history
- **GET** — No description
  - Handler: AccountStatusController::getStatusHistory
  - Middleware: auth, permission:users:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/users/{userId}/suspend
- **POST** — No description
  - Handler: AccountStatusController::suspendAccount
  - Middleware: auth, permission:users:suspend
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /admin/users/{userId}/unlock
- **POST** — No description
  - Handler: AccountStatusController::unlockAccount
  - Middleware: auth, permission:users:unlock
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\AccountStatusController.php

## /api/v1/system/resilience/backpressure/limits
- **PUT** — No description
  - Handler: ResilienceController::updateBackpressureLimits
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **PUT** — No description
  - Handler: ResilienceController::updateBackpressureLimits
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/backpressure/reset
- **POST** — No description
  - Handler: ResilienceController::resetBackpressure
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **POST** — No description
  - Handler: ResilienceController::resetBackpressure
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/backpressure/stats
- **GET** — No description
  - Handler: ResilienceController::getBackpressureStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getBackpressureStats
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/backpressure/usage
- **GET** — No description
  - Handler: ResilienceController::getBackpressureUsage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getBackpressureUsage
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/circuit-breaker/force-open
- **POST** — No description
  - Handler: ResilienceController::forceOpenCircuitBreaker
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **POST** — No description
  - Handler: ResilienceController::forceOpenCircuitBreaker
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/circuit-breaker/reset
- **POST** — No description
  - Handler: ResilienceController::resetCircuitBreaker
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **POST** — No description
  - Handler: ResilienceController::resetCircuitBreaker
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/circuit-breaker/status
- **GET** — No description
  - Handler: ResilienceController::getCircuitBreakerStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getCircuitBreakerStatus
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/degradation/disable
- **POST** — No description
  - Handler: ResilienceController::disableDegradation
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **POST** — No description
  - Handler: ResilienceController::disableDegradation
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/degradation/enable
- **POST** — No description
  - Handler: ResilienceController::enableDegradation
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **POST** — No description
  - Handler: ResilienceController::enableDegradation
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/degradation/stats/{service}
- **GET** — No description
  - Handler: ResilienceController::getDegradationStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getDegradationStats
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/degradation/status
- **GET** — No description
  - Handler: ResilienceController::getDegradationStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getDegradationStatus
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/retry/reset
- **POST** — No description
  - Handler: ResilienceController::resetRetryStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **POST** — No description
  - Handler: ResilienceController::resetRetryStats
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/retry/stats
- **GET** — No description
  - Handler: ResilienceController::getRetryStats
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getRetryStats
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/retry/stats/{operationName}
- **GET** — No description
  - Handler: ResilienceController::getRetryStatsByOperation
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getRetryStatsByOperation
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/v1/system/resilience/status
- **GET** — No description
  - Handler: ResilienceController::getSystemStatus
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\ResilienceController.php

- **GET** — No description
  - Handler: ResilienceController::getResilienceStatus
  - Middleware: auth, adminOnly
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\ResilienceController.php

## /api/auth/social/providers
- **GET** — List linked providers
  - Handler: SocialAuthController::listProviders
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\SocialAuthController.php

## /api/auth/social/{provider}
- **GET** — Start OAuth flow
  - Handler: SocialAuthController::authorize
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\SocialAuthController.php

## /api/auth/social/{provider}/callback
- **GET** — OAuth callback
  - Handler: SocialAuthController::callback
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\SocialAuthController.php

## /api/auth/social/{provider}/unlink
- **POST** — Unlink provider
  - Handler: SocialAuthController::unlink
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\SocialAuthController.php

## /api/status
- **GET** — No description
  - Handler: HealthCheckController::status
  - Middleware: logRequest
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\HealthCheckController.php

## /api/users/health
- **GET** — User module health
  - Handler: UserHealthController::health
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\UserHealthController.php

## /api/v1/system/cache/check/{key}
- **GET** — No description
  - Handler: CacheController::checkKey
  - Middleware: auth, permission:cache:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/clear
- **POST** — No description
  - Handler: CacheController::clearAll
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/clear-pattern
- **POST** — No description
  - Handler: CacheController::clearPattern
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/clear-tags
- **POST** — No description
  - Handler: CacheController::clearTags
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/config
- **GET** — No description
  - Handler: CacheController::getConfig
  - Middleware: auth, permission:cache:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/invalidate-table
- **POST** — No description
  - Handler: CacheController::invalidateTable
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/keys
- **GET** — No description
  - Handler: CacheController::getKeys
  - Middleware: auth, permission:cache:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/keys/{key}
- **DELETE** — No description
  - Handler: CacheController::clearKey
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/statistics
- **GET** — No description
  - Handler: CacheController::getStatistics
  - Middleware: auth, permission:cache:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/toggle
- **POST** — No description
  - Handler: CacheController::toggle
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/cache/warm
- **POST** — No description
  - Handler: CacheController::warm
  - Middleware: auth, permission:cache:manage
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\CacheController.php

## /api/v1/system/permissions
- **GET** — No description
  - Handler: PermissionController::listPermissions
  - Middleware: auth, permission:permissions:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\PermissionController.php

- **POST** — No description
  - Handler: PermissionController::createPermission
  - Middleware: auth, permission:permissions:create
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\PermissionController.php

## /api/v1/system/permissions/resource/{resource}
- **GET** — No description
  - Handler: PermissionController::getPermissionsByResource
  - Middleware: auth, permission:permissions:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\PermissionController.php

## /api/v1/system/permissions/{permissionId}
- **DELETE** — No description
  - Handler: PermissionController::deletePermission
  - Middleware: auth, permission:permissions:delete
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\PermissionController.php

- **GET** — No description
  - Handler: PermissionController::getPermission
  - Middleware: auth, permission:permissions:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\PermissionController.php

- **PUT** — No description
  - Handler: PermissionController::updatePermission
  - Middleware: auth, permission:permissions:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\PermissionController.php

## /api/v1/system/roles
- **GET** — No description
  - Handler: RoleController::listRoles
  - Middleware: auth, permission:roles:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

- **POST** — No description
  - Handler: RoleController::createRole
  - Middleware: auth, permission:roles:create
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

## /api/v1/system/roles/{roleId}
- **DELETE** — No description
  - Handler: RoleController::deleteRole
  - Middleware: auth, permission:roles:delete
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

- **GET** — No description
  - Handler: RoleController::getRole
  - Middleware: auth, permission:roles:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

- **PUT** — No description
  - Handler: RoleController::updateRole
  - Middleware: auth, permission:roles:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

## /api/v1/system/roles/{roleId}/permissions/{permissionId}
- **DELETE** — No description
  - Handler: RoleController::removePermission
  - Middleware: auth, permission:roles:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

- **POST** — No description
  - Handler: RoleController::assignPermission
  - Middleware: auth, permission:roles:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\RoleController.php

## /api/v1/system/users/{userId}/roles
- **GET** — No description
  - Handler: UserRoleController::getUserRoles
  - Middleware: auth, permission:users:read
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\UserRoleController.php

## /api/v1/system/users/{userId}/roles/bulk
- **POST** — No description
  - Handler: UserRoleController::bulkAssignRoles
  - Middleware: auth, permission:users:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\UserRoleController.php

## /api/v1/system/users/{userId}/roles/sync
- **PUT** — No description
  - Handler: UserRoleController::syncRoles
  - Middleware: auth, permission:users:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\UserRoleController.php

## /api/v1/system/users/{userId}/roles/{roleId}
- **DELETE** — No description
  - Handler: UserRoleController::removeRole
  - Middleware: auth, permission:users:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\UserRoleController.php

- **POST** — No description
  - Handler: UserRoleController::assignRole
  - Middleware: auth, permission:users:update
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\Admin\UserRoleController.php

## /api/v1/auth/login
- **POST** — User login
  - Handler: AuthController::login
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\AuthController.php

## /api/v1/auth/logout
- **POST** — User logout
  - Handler: AuthController::logout
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\AuthController.php

## /api/v1/auth/otp/request
- **POST** — Request OTP
  - Handler: OTPController::requestOTP
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPController.php

## /api/v1/auth/otp/verify
- **POST** — Verify OTP
  - Handler: OTPController::verifyOTP
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPController.php

## /api/v1/auth/password/forgot
- **POST** — Request password reset OTP
  - Handler: OTPController::requestPasswordReset
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPController.php

## /api/v1/auth/password/reset
- **POST** — Reset password
  - Handler: OTPController::resetPassword
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\OTPController.php

## /api/v1/auth/refresh
- **POST** — Refresh JWT token
  - Handler: AuthController::refreshToken
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\AuthController.php

## /api/v1/auth/register
- **POST** — Register new user
  - Handler: AuthController::register
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\AuthController.php

## /api/v1/storage/config
- **GET** — Get storage config
  - Handler: StorageController::config
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/list
- **GET** — List files
  - Handler: StorageController::listFiles
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/list/{category}
- **GET** — List files by category
  - Handler: StorageController::listFiles
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/metadata/{category}/{path}
- **GET** — Get file metadata
  - Handler: StorageController::metadata
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/presigned-download
- **POST** — Get pre-signed download URL
  - Handler: StorageController::presignedDownload
  - Middleware: auth, jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/presigned-upload
- **POST** — Get pre-signed upload URL
  - Handler: StorageController::presignedUpload
  - Middleware: auth, jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/public-config
- **GET** — Get public storage config
  - Handler: StorageController::publicConfig
  - Middleware: cors
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/upload
- **POST** — Upload file
  - Handler: StorageController::upload
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/storage/{category}/{path}
- **DELETE** — Delete file
  - Handler: StorageController::delete
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Storage\Controllers\StorageController.php

## /api/v1/user/me
- **GET** — Get current authenticated user
  - Handler: UserContextController::me
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\Auth\Controllers\UserContextController.php

## /api/v1/users/
- **GET** — List users
  - Handler: UserController::list
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\UserController.php

## /api/v1/users/me
- **GET** — Get current user
  - Handler: UserController::me
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\UserController.php

## /api/v1/users/profile
- **GET** — Get user profile
  - Handler: UserController::getProfile
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\UserController.php

- **PUT** — Update user profile
  - Handler: UserController::updateProfile
  - Middleware: jsonParser
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\UserController.php

## /api/v1/users/search
- **GET** — Search users
  - Handler: UserController::search
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\UserController.php

## /docs
- **GET** — Swagger UI
  - Handler: DocsController::index
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\DocsController.php

## /docs/errors
- **GET** — Error catalog
  - Handler: DocsController::errors
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\DocsController.php

## /docs/health
- **GET** — Docs health
  - Handler: DocsController::health
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\DocsController.php

## /docs/openapi.json
- **GET** — OpenAPI spec
  - Handler: DocsController::openapi
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\DocsController.php

## /docs/postman
- **GET** — Postman collection
  - Handler: DocsController::postman
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\DocsController.php

## /health
- **GET** — No description
  - Handler: HealthCheckController::health
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\HealthCheckController.php

## /health/live
- **GET** — No description
  - Handler: HealthCheckController::live
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\HealthCheckController.php

## /health/ready
- **GET** — No description
  - Handler: HealthCheckController::ready
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\app\Controllers\HealthCheckController.php

## /resend-verification
- **POST** — No description
  - Handler: VerificationController::resendVerification
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\VerificationController.php

## /verification-status
- **GET** — No description
  - Handler: VerificationController::getVerificationStatus
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\VerificationController.php

## /verify-email
- **POST** — No description
  - Handler: VerificationController::verifyEmail
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\VerificationController.php

## /verify-phone
- **POST** — No description
  - Handler: VerificationController::verifyPhone
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\VerificationController.php

## /verify-phone/send-otp
- **POST** — No description
  - Handler: VerificationController::sendPhoneVerificationOtp
  - Middleware: auth
  - Source: C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\backend\modules\User\Controllers\VerificationController.php
