# Backend Architecture

## Purpose
Enterprise-grade modular API framework that enforces security, observability, and governance by default. All API flows pass through standardized routing, middleware, and response envelopes, with MySQL access restricted to stored procedures and MongoDB used for observability data.

## High-Level Design
- **Entry point**: public index uses attribute routing via `ControllerRegistry` and `Router`.
- **Controllers**: Stateless controllers expose REST endpoints. Admin controllers are segregated under an admin namespace.
- **Core**: Shared framework services (routing, response envelopes, observability, logging, cache, resilience, validation).
- **Modules**: Feature modules register controllers, data access, services, and DTOs (Auth, User, Storage).
- **Middleware**: Cross-cutting concerns enforced (auth, authorization, rate limits, DDoS, validation, headers, cache, retry).
- **Data layer**: DAO + stored procedures only. No raw SQL in controllers/services.
- **Observability**: Mandatory trace headers, metrics, and structured logging.

## Request Lifecycle (Simplified)
1. **Router** resolves a controller action via attributes.
2. **Middleware** enforces security, validation, rate limiting, headers.
3. **Controller** delegates to service/DAO.
4. **Response** returns standard envelope with trace IDs.
5. **Logging/metrics** emitted to observability stack (MongoDB).

## Component Interaction Flow (Diagram)
```mermaid
flowchart LR
	client[Client] --> entry[public/index.php]
	entry --> registry[ControllerRegistry]
	registry --> router[Router + Attribute Routes]
	router --> middleware[Middleware Pipeline]

	middleware --> auth[Authn/Authz]
	middleware --> validation[Validation & Security]
	middleware --> traffic[Rate Limit & Traffic]
	middleware --> resilience[Retry/Backpressure]
	middleware --> headers[Secure Headers]

	middleware --> controllers[Controllers]
	controllers --> services[Services]
	services --> daos[DAO Layer]
	daos --> db[MySQL Stored Procedures]

	controllers --> cache[Cache/Redis]
	controllers --> storage[Storage Drivers]
	controllers --> oauth[OAuth Providers]
	controllers --> notifications[Notifications]

	controllers --> response[Response Envelope]
	response --> trace[TraceContext Headers]
	response --> client

	middleware --> logging[Logger + Metrics]
	logging --> mongo[MongoDB (Logs/Audit/Metrics)]
```

## Class Tree (Backend)

### Console
- [backend/app/Console/Commands/GenerateDocsCommand.php](backend/app/Console/Commands/GenerateDocsCommand.php) — GenerateDocsCommand
- [backend/app/Console/Commands/MakeModuleCommand.php](backend/app/Console/Commands/MakeModuleCommand.php) — MakeModuleCommand
- [backend/app/Console/Commands/MakeModuleCommand.php](backend/app/Console/Commands/MakeModuleCommand.php) — Create
- [backend/app/Console/Commands/MakeModuleCommand.php](backend/app/Console/Commands/MakeModuleCommand.php) — Update
- [backend/app/Console/Commands/MigrateCommand.php](backend/app/Console/Commands/MigrateCommand.php) — MigrateCommand

### Controllers
- [backend/app/Controllers/DocsController.php](backend/app/Controllers/DocsController.php) — DocsController
- [backend/app/Controllers/HealthCheckController.php](backend/app/Controllers/HealthCheckController.php) — HealthCheckController
- [backend/app/Controllers/ResilienceController.php](backend/app/Controllers/ResilienceController.php) — ResilienceController
- [backend/app/Controllers/TrafficController.php](backend/app/Controllers/TrafficController.php) — TrafficController

### Controllers (Admin)
- [backend/app/Controllers/Admin/CacheController.php](backend/app/Controllers/Admin/CacheController.php) — CacheController
- [backend/app/Controllers/Admin/LockingController.php](backend/app/Controllers/Admin/LockingController.php) — LockingController
- [backend/app/Controllers/Admin/PermissionController.php](backend/app/Controllers/Admin/PermissionController.php) — PermissionController
- [backend/app/Controllers/Admin/ResilienceController.php](backend/app/Controllers/Admin/ResilienceController.php) — ResilienceController
- [backend/app/Controllers/Admin/RoleController.php](backend/app/Controllers/Admin/RoleController.php) — RoleController
- [backend/app/Controllers/Admin/SecurityController.php](backend/app/Controllers/Admin/SecurityController.php) — SecurityController
- [backend/app/Controllers/Admin/UserRoleController.php](backend/app/Controllers/Admin/UserRoleController.php) — UserRoleController

### Core
- [backend/app/Core/ApiVersion.php](backend/app/Core/ApiVersion.php) — ApiVersion
- [backend/app/Core/BaseController.php](backend/app/Core/BaseController.php) — BaseController
- [backend/app/Core/ControllerRegistry.php](backend/app/Core/ControllerRegistry.php) — ControllerRegistry
- [backend/app/Core/Database.php](backend/app/Core/Database.php) — Database
- [backend/app/Core/Logger.php](backend/app/Core/Logger.php) — Logger
- [backend/app/Core/ModuleLoader.php](backend/app/Core/ModuleLoader.php) — ModuleLoader
- [backend/app/Core/Response.php](backend/app/Core/Response.php) — Response
- [backend/app/Core/Router.php](backend/app/Core/Router.php) — Router
- [backend/app/Core/TraceContext.php](backend/app/Core/TraceContext.php) — TraceContext

### Core Attributes
- [backend/app/Core/Attributes/Route.php](backend/app/Core/Attributes/Route.php) — Route
- [backend/app/Core/Attributes/RouteGroup.php](backend/app/Core/Attributes/RouteGroup.php) — RouteGroup
- [backend/app/Core/Attributes/ValidateInput.php](backend/app/Core/Attributes/ValidateInput.php) — ValidateInput

### Core Auth (OAuth)
- [backend/app/Core/Auth/OAuth/AppleOAuthProvider.php](backend/app/Core/Auth/OAuth/AppleOAuthProvider.php) — AppleOAuthProvider
- [backend/app/Core/Auth/OAuth/FacebookOAuthProvider.php](backend/app/Core/Auth/OAuth/FacebookOAuthProvider.php) — FacebookOAuthProvider
- [backend/app/Core/Auth/OAuth/GithubOAuthProvider.php](backend/app/Core/Auth/OAuth/GithubOAuthProvider.php) — GithubOAuthProvider
- [backend/app/Core/Auth/OAuth/GoogleOAuthProvider.php](backend/app/Core/Auth/OAuth/GoogleOAuthProvider.php) — GoogleOAuthProvider
- [backend/app/Core/Auth/OAuth/LinkedInOAuthProvider.php](backend/app/Core/Auth/OAuth/LinkedInOAuthProvider.php) — LinkedInOAuthProvider
- [backend/app/Core/Auth/OAuth/MicrosoftOAuthProvider.php](backend/app/Core/Auth/OAuth/MicrosoftOAuthProvider.php) — MicrosoftOAuthProvider
- [backend/app/Core/Auth/OAuth/OAuthFactory.php](backend/app/Core/Auth/OAuth/OAuthFactory.php) — OAuthFactory
- [backend/app/Core/Auth/OAuth/TwitterOAuthProvider.php](backend/app/Core/Auth/OAuth/TwitterOAuthProvider.php) — TwitterOAuthProvider

### Core Authorization
- [backend/app/Core/Authorization/AuthorizationManager.php](backend/app/Core/Authorization/AuthorizationManager.php) — AuthorizationManager
- [backend/app/Core/Authorization/Permission.php](backend/app/Core/Authorization/Permission.php) — Permission
- [backend/app/Core/Authorization/Policy.php](backend/app/Core/Authorization/Policy.php) — Policy
- [backend/app/Core/Authorization/PolicyEngine.php](backend/app/Core/Authorization/PolicyEngine.php) — PolicyEngine
- [backend/app/Core/Authorization/PolicyRule.php](backend/app/Core/Authorization/PolicyRule.php) — PolicyRule
- [backend/app/Core/Authorization/PolicyRule.php](backend/app/Core/Authorization/PolicyRule.php) — TimeBasedPolicy
- [backend/app/Core/Authorization/PolicyRule.php](backend/app/Core/Authorization/PolicyRule.php) — ResourceQuotaPolicy
- [backend/app/Core/Authorization/PolicyRule.php](backend/app/Core/Authorization/PolicyRule.php) — AdvancedUserPolicy
- [backend/app/Core/Authorization/Role.php](backend/app/Core/Authorization/Role.php) — Role

### Core Cache
- [backend/app/Core/Cache/Attributes/Cache.php](backend/app/Core/Cache/Attributes/Cache.php) — Cache
- [backend/app/Core/Cache/Attributes/CacheInvalidate.php](backend/app/Core/Cache/Attributes/CacheInvalidate.php) — CacheInvalidate
- [backend/app/Core/Cache/Attributes/NoCache.php](backend/app/Core/Cache/Attributes/NoCache.php) — NoCache
- [backend/app/Core/Cache/CacheManager.php](backend/app/Core/Cache/CacheManager.php) — CacheManager
- [backend/app/Core/Cache/CacheStatistics.php](backend/app/Core/Cache/CacheStatistics.php) — CacheStatistics
- [backend/app/Core/Cache/CacheWarmer.php](backend/app/Core/Cache/CacheWarmer.php) — CacheWarmer
- [backend/app/Core/Cache/Drivers/RedisDriver.php](backend/app/Core/Cache/Drivers/RedisDriver.php) — RedisDriver
- [backend/app/Core/Cache/QueryCache.php](backend/app/Core/Cache/QueryCache.php) — QueryCache

### Core Data
- [backend/app/Core/Data/DataIntegrityValidator.php](backend/app/Core/Data/DataIntegrityValidator.php) — DataIntegrityValidator
- [backend/app/Core/Data/OptimisticLockManager.php](backend/app/Core/Data/OptimisticLockManager.php) — OptimisticLockManager
- [backend/app/Core/Data/SchemaVersionManager.php](backend/app/Core/Data/SchemaVersionManager.php) — SchemaVersionManager
- [backend/app/Core/Data/SoftDeleteManager.php](backend/app/Core/Data/SoftDeleteManager.php) — SoftDeleteManager

### Core Database
- [backend/app/Core/Database/Attributes/OptimisticLock.php](backend/app/Core/Database/Attributes/OptimisticLock.php) — OptimisticLock
- [backend/app/Core/Database/DB.php](backend/app/Core/Database/DB.php) — DB
- [backend/app/Core/Database/MySQLConnection.php](backend/app/Core/Database/MySQLConnection.php) — MySQLConnection
- [backend/app/Core/Database/OptimisticLockManager.php](backend/app/Core/Database/OptimisticLockManager.php) — OptimisticLockManager
- [backend/app/Core/Database/OptimisticLockManager.php](backend/app/Core/Database/OptimisticLockManager.php) — OptimisticLockStatistics

### Core Documentation
- [backend/app/Core/Documentation/Attributes/ApiDoc.php](backend/app/Core/Documentation/Attributes/ApiDoc.php) — ApiDoc
- [backend/app/Core/Documentation/Attributes/ApiExample.php](backend/app/Core/Documentation/Attributes/ApiExample.php) — ApiExample
- [backend/app/Core/Documentation/Attributes/ApiParam.php](backend/app/Core/Documentation/Attributes/ApiParam.php) — ApiParam
- [backend/app/Core/Documentation/Attributes/ApiResponse.php](backend/app/Core/Documentation/Attributes/ApiResponse.php) — ApiResponse
- [backend/app/Core/Documentation/ErrorCatalogGenerator.php](backend/app/Core/Documentation/ErrorCatalogGenerator.php) — ErrorCatalogGenerator
- [backend/app/Core/Documentation/OpenApiGenerator.php](backend/app/Core/Documentation/OpenApiGenerator.php) — OpenApiGenerator
- [backend/app/Core/Documentation/PostmanExporter.php](backend/app/Core/Documentation/PostmanExporter.php) — PostmanExporter
- [backend/app/Core/Documentation/SchemaExtractor.php](backend/app/Core/Documentation/SchemaExtractor.php) — SchemaExtractor

### Core Exceptions
- [backend/app/Core/Exceptions/BusinessRuleViolationException.php](backend/app/Core/Exceptions/BusinessRuleViolationException.php) — BusinessRuleViolationException
- [backend/app/Core/Exceptions/ForbiddenException.php](backend/app/Core/Exceptions/ForbiddenException.php) — ForbiddenException
- [backend/app/Core/Exceptions/InsufficientFundsException.php](backend/app/Core/Exceptions/InsufficientFundsException.php) — InsufficientFundsException
- [backend/app/Core/Exceptions/NotFoundException.php](backend/app/Core/Exceptions/NotFoundException.php) — NotFoundException
- [backend/app/Core/Exceptions/OptimisticLockException.php](backend/app/Core/Exceptions/OptimisticLockException.php) — OptimisticLockException
- [backend/app/Core/Exceptions/TimeoutException.php](backend/app/Core/Exceptions/TimeoutException.php) — TimeoutException
- [backend/app/Core/Exceptions/TransientException.php](backend/app/Core/Exceptions/TransientException.php) — TransientException
- [backend/app/Core/Exceptions/UnauthorizedException.php](backend/app/Core/Exceptions/UnauthorizedException.php) — UnauthorizedException
- [backend/app/Core/Exceptions/ValidationException.php](backend/app/Core/Exceptions/ValidationException.php) — ValidationException

### Core I18n
- [backend/app/Core/I18n/LocaleContext.php](backend/app/Core/I18n/LocaleContext.php) — LocaleContext
- [backend/app/Core/I18n/Translator.php](backend/app/Core/I18n/Translator.php) — Translator

### Core Logging
- [backend/app/Core/Logging/Logger.php](backend/app/Core/Logging/Logger.php) — Logger
- [backend/app/Core/Logging/LogManager.php](backend/app/Core/Logging/LogManager.php) — LogManager

### Core Notifications
- [backend/app/Core/Notifications/EmailService.php](backend/app/Core/Notifications/EmailService.php) — EmailService
- [backend/app/Core/Notifications/NotificationFactory.php](backend/app/Core/Notifications/NotificationFactory.php) — NotificationFactory
- [backend/app/Core/Notifications/SMSService.php](backend/app/Core/Notifications/SMSService.php) — SMSService
- [backend/app/Core/Notifications/Services/AmazonSESService.php](backend/app/Core/Notifications/Services/AmazonSESService.php) — AmazonSESService
- [backend/app/Core/Notifications/Services/MailgunService.php](backend/app/Core/Notifications/Services/MailgunService.php) — MailgunService
- [backend/app/Core/Notifications/Services/MSG91Service.php](backend/app/Core/Notifications/Services/MSG91Service.php) — MSG91Service
- [backend/app/Core/Notifications/Services/PostmarkService.php](backend/app/Core/Notifications/Services/PostmarkService.php) — PostmarkService
- [backend/app/Core/Notifications/Services/VonageService.php](backend/app/Core/Notifications/Services/VonageService.php) — VonageService
- [backend/app/Core/Notifications/Services/WhatsAppService.php](backend/app/Core/Notifications/Services/WhatsAppService.php) — WhatsAppService

### Core Observability
- [backend/app/Core/Observability/DistributedTracer.php](backend/app/Core/Observability/DistributedTracer.php) — DistributedTracer
- [backend/app/Core/Observability/MetricsCollector.php](backend/app/Core/Observability/MetricsCollector.php) — MetricsCollector
- [backend/app/Core/Observability/TraceContext.php](backend/app/Core/Observability/TraceContext.php) — TraceContext

### Core Resilience
- [backend/app/Core/Resilience/Attributes/Retry.php](backend/app/Core/Resilience/Attributes/Retry.php) — Retry
- [backend/app/Core/Resilience/BackpressureHandler.php](backend/app/Core/Resilience/BackpressureHandler.php) — BackpressureHandler
- [backend/app/Core/Resilience/CircuitBreaker.php](backend/app/Core/Resilience/CircuitBreaker.php) — CircuitBreaker
- [backend/app/Core/Resilience/CircuitBreaker.php](backend/app/Core/Resilience/CircuitBreaker.php) — CircuitBreakerException
- [backend/app/Core/Resilience/ExponentialBackoff.php](backend/app/Core/Resilience/ExponentialBackoff.php) — ExponentialBackoff
- [backend/app/Core/Resilience/FibonacciBackoff.php](backend/app/Core/Resilience/FibonacciBackoff.php) — FibonacciBackoff
- [backend/app/Core/Resilience/FixedBackoff.php](backend/app/Core/Resilience/FixedBackoff.php) — FixedBackoff
- [backend/app/Core/Resilience/GracefulDegradation.php](backend/app/Core/Resilience/GracefulDegradation.php) — GracefulDegradation
- [backend/app/Core/Resilience/IdempotencyKey.php](backend/app/Core/Resilience/IdempotencyKey.php) — IdempotencyKey
- [backend/app/Core/Resilience/LinearBackoff.php](backend/app/Core/Resilience/LinearBackoff.php) — LinearBackoff
- [backend/app/Core/Resilience/RetryPolicy.php](backend/app/Core/Resilience/RetryPolicy.php) — RetryPolicy
- [backend/app/Core/Resilience/RetryStatistics.php](backend/app/Core/Resilience/RetryStatistics.php) — RetryStatistics
- [backend/app/Core/Resilience/TimeoutManager.php](backend/app/Core/Resilience/TimeoutManager.php) — TimeoutManager
- [backend/app/Core/Resilience/TimeoutManager.php](backend/app/Core/Resilience/TimeoutManager.php) — TimeoutException

### Core Security
- [backend/app/Core/Security/AnomalyDetector.php](backend/app/Core/Security/AnomalyDetector.php) — AnomalyDetector
- [backend/app/Core/Security/Attributes/BotProtection.php](backend/app/Core/Security/Attributes/BotProtection.php) — BotProtection
- [backend/app/Core/Security/BotDetector.php](backend/app/Core/Security/BotDetector.php) — BotDetector
- [backend/app/Core/Security/CSRFProtection.php](backend/app/Core/Security/CSRFProtection.php) — CSRFProtection
- [backend/app/Core/Security/GeoBlocker.php](backend/app/Core/Security/GeoBlocker.php) — GeoBlocker
- [backend/app/Core/Security/IpReputationManager.php](backend/app/Core/Security/IpReputationManager.php) — IpReputationManager
- [backend/app/Core/Security/SecretsManager.php](backend/app/Core/Security/SecretsManager.php) — SecretsManager
- [backend/app/Core/Security/WafEngine.php](backend/app/Core/Security/WafEngine.php) — WafEngine
- [backend/app/Core/Security/XSSProtection.php](backend/app/Core/Security/XSSProtection.php) — XSSProtection

### Core Social Media
- [backend/app/Core/SocialMedia/Ads/AdsFactory.php](backend/app/Core/SocialMedia/Ads/AdsFactory.php) — AdsFactory
- [backend/app/Core/SocialMedia/Ads/BaseAdsConnector.php](backend/app/Core/SocialMedia/Ads/BaseAdsConnector.php) — BaseAdsConnector
- [backend/app/Core/SocialMedia/Ads/GoogleAdsConnector.php](backend/app/Core/SocialMedia/Ads/GoogleAdsConnector.php) — GoogleAdsConnector
- [backend/app/Core/SocialMedia/Ads/LinkedInAdsConnector.php](backend/app/Core/SocialMedia/Ads/LinkedInAdsConnector.php) — LinkedInAdsConnector
- [backend/app/Core/SocialMedia/Ads/MetaAdsConnector.php](backend/app/Core/SocialMedia/Ads/MetaAdsConnector.php) — MetaAdsConnector
- [backend/app/Core/SocialMedia/Ads/TikTokAdsConnector.php](backend/app/Core/SocialMedia/Ads/TikTokAdsConnector.php) — TikTokAdsConnector
- [backend/app/Core/SocialMedia/Connectors/BasePlatformConnector.php](backend/app/Core/SocialMedia/Connectors/BasePlatformConnector.php) — BasePlatformConnector
- [backend/app/Core/SocialMedia/Connectors/DiscordConnector.php](backend/app/Core/SocialMedia/Connectors/DiscordConnector.php) — DiscordConnector
- [backend/app/Core/SocialMedia/Connectors/FacebookConnector.php](backend/app/Core/SocialMedia/Connectors/FacebookConnector.php) — FacebookConnector
- [backend/app/Core/SocialMedia/Connectors/InstagramConnector.php](backend/app/Core/SocialMedia/Connectors/InstagramConnector.php) — InstagramConnector
- [backend/app/Core/SocialMedia/Connectors/LinkedInConnector.php](backend/app/Core/SocialMedia/Connectors/LinkedInConnector.php) — LinkedInConnector
- [backend/app/Core/SocialMedia/Connectors/MediumConnector.php](backend/app/Core/SocialMedia/Connectors/MediumConnector.php) — MediumConnector
- [backend/app/Core/SocialMedia/Connectors/PinterestConnector.php](backend/app/Core/SocialMedia/Connectors/PinterestConnector.php) — PinterestConnector
- [backend/app/Core/SocialMedia/Connectors/RedditConnector.php](backend/app/Core/SocialMedia/Connectors/RedditConnector.php) — RedditConnector
- [backend/app/Core/SocialMedia/Connectors/SlackConnector.php](backend/app/Core/SocialMedia/Connectors/SlackConnector.php) — SlackConnector
- [backend/app/Core/SocialMedia/Connectors/TelegramConnector.php](backend/app/Core/SocialMedia/Connectors/TelegramConnector.php) — TelegramConnector
- [backend/app/Core/SocialMedia/Connectors/TikTokConnector.php](backend/app/Core/SocialMedia/Connectors/TikTokConnector.php) — TikTokConnector
- [backend/app/Core/SocialMedia/Connectors/TwitterConnector.php](backend/app/Core/SocialMedia/Connectors/TwitterConnector.php) — TwitterConnector
- [backend/app/Core/SocialMedia/Connectors/WordPressConnector.php](backend/app/Core/SocialMedia/Connectors/WordPressConnector.php) — WordPressConnector
- [backend/app/Core/SocialMedia/Connectors/YouTubeConnector.php](backend/app/Core/SocialMedia/Connectors/YouTubeConnector.php) — YouTubeConnector
- [backend/app/Core/SocialMedia/SocialMediaManager.php](backend/app/Core/SocialMedia/SocialMediaManager.php) — SocialMediaManager
- [backend/app/Core/SocialMedia/SocialPlatformFactory.php](backend/app/Core/SocialMedia/SocialPlatformFactory.php) — SocialPlatformFactory
- [backend/app/Core/SocialMedia/Webhooks/WebhookHandler.php](backend/app/Core/SocialMedia/Webhooks/WebhookHandler.php) — WebhookHandler

### Core Storage
- [backend/app/Core/Storage/Drivers/AzureDriver.php](backend/app/Core/Storage/Drivers/AzureDriver.php) — AzureDriver
- [backend/app/Core/Storage/Drivers/LocalDriver.php](backend/app/Core/Storage/Drivers/LocalDriver.php) — LocalDriver
- [backend/app/Core/Storage/Drivers/S3Driver.php](backend/app/Core/Storage/Drivers/S3Driver.php) — S3Driver
- [backend/app/Core/Storage/Storage.php](backend/app/Core/Storage/Storage.php) — Storage
- [backend/app/Core/Storage/StorageManager.php](backend/app/Core/Storage/StorageManager.php) — StorageManager
- [backend/app/Core/Storage/StorageManager.php](backend/app/Core/Storage/StorageManager.php) — CategoryStorage

### Core Testing
- [backend/app/Core/Testing/ContractTester.php](backend/app/Core/Testing/ContractTester.php) — ContractTester
- [backend/app/Core/Testing/ContractTester.php](backend/app/Core/Testing/ContractTester.php) — ValidationResult
- [backend/app/Core/Testing/ExternalServiceMock.php](backend/app/Core/Testing/ExternalServiceMock.php) — ExternalServiceMock
- [backend/app/Core/Testing/ExternalServiceMock.php](backend/app/Core/Testing/ExternalServiceMock.php) — StripeMock
- [backend/app/Core/Testing/ExternalServiceMock.php](backend/app/Core/Testing/ExternalServiceMock.php) — SendGridMock
- [backend/app/Core/Testing/ExternalServiceMock.php](backend/app/Core/Testing/ExternalServiceMock.php) — TwilioMock
- [backend/app/Core/Testing/ExternalServiceMock.php](backend/app/Core/Testing/ExternalServiceMock.php) — OAuthMock
- [backend/app/Core/Testing/LoadTester.php](backend/app/Core/Testing/LoadTester.php) — LoadTester
- [backend/app/Core/Testing/LoadTester.php](backend/app/Core/Testing/LoadTester.php) — LoadTestResult
- [backend/app/Core/Testing/MockServer.php](backend/app/Core/Testing/MockServer.php) — MockServer
- [backend/app/Core/Testing/MockServer.php](backend/app/Core/Testing/MockServer.php) — MockExpectation
- [backend/app/Core/Testing/MockServer.php](backend/app/Core/Testing/MockServer.php) — MockResponse
- [backend/app/Core/Testing/SchemaValidator.php](backend/app/Core/Testing/SchemaValidator.php) — SchemaValidator
- [backend/app/Core/Testing/SecurityTester.php](backend/app/Core/Testing/SecurityTester.php) — SecurityTester
- [backend/app/Core/Testing/SecurityTester.php](backend/app/Core/Testing/SecurityTester.php) — SecurityScanResult
- [backend/app/Core/Testing/TestHelper.php](backend/app/Core/Testing/TestHelper.php) — TestHelper

### Core Traffic
- [backend/app/Core/Traffic/Attributes/RateLimit.php](backend/app/Core/Traffic/Attributes/RateLimit.php) — RateLimit
- [backend/app/Core/Traffic/QuotaManager.php](backend/app/Core/Traffic/QuotaManager.php) — QuotaManager
- [backend/app/Core/Traffic/RateLimiter.php](backend/app/Core/Traffic/RateLimiter.php) — RateLimiter
- [backend/app/Core/Traffic/Throttler.php](backend/app/Core/Traffic/Throttler.php) — Throttler

### Core Utils
- [backend/app/Core/Utils/IdGenerator.php](backend/app/Core/Utils/IdGenerator.php) — IdGenerator
- [backend/app/Core/Utils/UlidGenerator.php](backend/app/Core/Utils/UlidGenerator.php) — UlidGenerator
- [backend/app/Core/Utils/UuidGenerator.php](backend/app/Core/Utils/UuidGenerator.php) — UuidGenerator

### Core Validation
- [backend/app/Core/Validation/InputValidator.php](backend/app/Core/Validation/InputValidator.php) — InputValidator

### DAO
- [backend/app/DAO/AccountStatusDAO.php](backend/app/DAO/AccountStatusDAO.php) — AccountStatusDAO
- [backend/app/DAO/EmailVerificationDAO.php](backend/app/DAO/EmailVerificationDAO.php) — EmailVerificationDAO
- [backend/app/DAO/IdentifierDAO.php](backend/app/DAO/IdentifierDAO.php) — IdentifierDAO
- [backend/app/DAO/PermissionDAO.php](backend/app/DAO/PermissionDAO.php) — PermissionDAO
- [backend/app/DAO/RoleDAO.php](backend/app/DAO/RoleDAO.php) — RoleDAO

### Middleware
- [backend/app/Middleware/AccountStatusMiddleware.php](backend/app/Middleware/AccountStatusMiddleware.php) — AccountStatusMiddleware
- [backend/app/Middleware/AuthorizationMiddleware.php](backend/app/Middleware/AuthorizationMiddleware.php) — AuthorizationMiddleware
- [backend/app/Middleware/BackpressureMiddleware.php](backend/app/Middleware/BackpressureMiddleware.php) — BackpressureMiddleware
- [backend/app/Middleware/CommonMiddleware.php](backend/app/Middleware/CommonMiddleware.php) — CommonMiddleware
- [backend/app/Middleware/CSRFMiddleware.php](backend/app/Middleware/CSRFMiddleware.php) — CSRFMiddleware
- [backend/app/Middleware/DDoSProtectionMiddleware.php](backend/app/Middleware/DDoSProtectionMiddleware.php) — DDoSProtectionMiddleware
- [backend/app/Middleware/InputValidationMiddleware.php](backend/app/Middleware/InputValidationMiddleware.php) — InputValidationMiddleware
- [backend/app/Middleware/OptimisticLockMiddleware.php](backend/app/Middleware/OptimisticLockMiddleware.php) — OptimisticLockMiddleware
- [backend/app/Middleware/OTPRateLimitMiddleware.php](backend/app/Middleware/OTPRateLimitMiddleware.php) — OTPRateLimitMiddleware
- [backend/app/Middleware/PayloadSizeLimitMiddleware.php](backend/app/Middleware/PayloadSizeLimitMiddleware.php) — PayloadSizeLimitMiddleware
- [backend/app/Middleware/ResponseCacheMiddleware.php](backend/app/Middleware/ResponseCacheMiddleware.php) — ResponseCacheMiddleware
- [backend/app/Middleware/RetryMiddleware.php](backend/app/Middleware/RetryMiddleware.php) — RetryMiddleware
- [backend/app/Middleware/SecureHeadersMiddleware.php](backend/app/Middleware/SecureHeadersMiddleware.php) — SecureHeadersMiddleware
- [backend/app/Middleware/TrafficMiddleware.php](backend/app/Middleware/TrafficMiddleware.php) — TrafficMiddleware
- [backend/app/Middleware/XSSMiddleware.php](backend/app/Middleware/XSSMiddleware.php) — XSSMiddleware

### Policies
- [backend/app/Policies/PostPolicy.php](backend/app/Policies/PostPolicy.php) — PostPolicy
- [backend/app/Policies/UserPolicy.php](backend/app/Policies/UserPolicy.php) — UserPolicy

### Services
- [backend/app/Services/AccountStatusService.php](backend/app/Services/AccountStatusService.php) — AccountStatusService
- [backend/app/Services/AuthorizationService.php](backend/app/Services/AuthorizationService.php) — AuthorizationService
- [backend/app/Services/EmailVerificationService.php](backend/app/Services/EmailVerificationService.php) — EmailVerificationService
- [backend/app/Services/IdentifierService.php](backend/app/Services/IdentifierService.php) — IdentifierService

## Class Tree (Modules)

### Auth Module
- [backend/modules/Auth/Controllers/AuthController.php](backend/modules/Auth/Controllers/AuthController.php) — AuthController
- [backend/modules/Auth/Controllers/OTPAdminController.php](backend/modules/Auth/Controllers/OTPAdminController.php) — OTPAdminController
- [backend/modules/Auth/Controllers/OTPController.php](backend/modules/Auth/Controllers/OTPController.php) — OTPController
- [backend/modules/Auth/Controllers/SocialAuthController.php](backend/modules/Auth/Controllers/SocialAuthController.php) — SocialAuthController
- [backend/modules/Auth/DAO/OTPBlacklistDAO.php](backend/modules/Auth/DAO/OTPBlacklistDAO.php) — OTPBlacklistDAO
- [backend/modules/Auth/DAO/OTPDAO.php](backend/modules/Auth/DAO/OTPDAO.php) — OTPDAO
- [backend/modules/Auth/DAO/OTPHistoryDAO.php](backend/modules/Auth/DAO/OTPHistoryDAO.php) — OTPHistoryDAO
- [backend/modules/Auth/DAO/SessionDAO.php](backend/modules/Auth/DAO/SessionDAO.php) — SessionDAO
- [backend/modules/Auth/DAO/UserDAO.php](backend/modules/Auth/DAO/UserDAO.php) — UserDAO
- [backend/modules/Auth/DTO/LoginRequestDTO.php](backend/modules/Auth/DTO/LoginRequestDTO.php) — LoginRequestDTO
- [backend/modules/Auth/DTO/OTPRequestDTO.php](backend/modules/Auth/DTO/OTPRequestDTO.php) — OTPRequestDTO
- [backend/modules/Auth/DTO/OTPVerifyDTO.php](backend/modules/Auth/DTO/OTPVerifyDTO.php) — OTPVerifyDTO
- [backend/modules/Auth/DTO/RegisterRequestDTO.php](backend/modules/Auth/DTO/RegisterRequestDTO.php) — RegisterRequestDTO
- [backend/modules/Auth/Services/AuthService.php](backend/modules/Auth/Services/AuthService.php) — AuthService
- [backend/modules/Auth/Services/OTPService.php](backend/modules/Auth/Services/OTPService.php) — OTPService
- [backend/modules/Auth/Services/SocialAuthService.php](backend/modules/Auth/Services/SocialAuthService.php) — SocialAuthService

### Storage Module
- [backend/modules/Storage/Controllers/StorageController.php](backend/modules/Storage/Controllers/StorageController.php) — StorageController

### User Module
- [backend/modules/User/Controllers/AccountStatusController.php](backend/modules/User/Controllers/AccountStatusController.php) — AccountStatusController
- [backend/modules/User/Controllers/UserController.php](backend/modules/User/Controllers/UserController.php) — UserController
- [backend/modules/User/Controllers/VerificationController.php](backend/modules/User/Controllers/VerificationController.php) — VerificationController
- [backend/modules/User/DAO/UserDAO.php](backend/modules/User/DAO/UserDAO.php) — UserDAO
- [backend/modules/User/DTO/UpdateProfileDTO.php](backend/modules/User/DTO/UpdateProfileDTO.php) — UpdateProfileDTO
- [backend/modules/User/Services/UserService.php](backend/modules/User/Services/UserService.php) — UserService

## Key Enforcement Points
- **Authentication required** for all API routes except explicitly public endpoints.
- **Trace headers** are generated and propagated for every response.
- **Rate limiting** and **traffic control** are enforced at middleware level.
- **MySQL access** only via stored procedures through DAO/DB abstractions.
- **MongoDB** used for logs, audit trails, and observability data.
