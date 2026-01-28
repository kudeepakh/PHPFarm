# 🧪 PHPFrarm Testing Guide

Complete guide to the Testing & Quality Infrastructure module.

---

## 📖 Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Test Structure](#test-structure)
4. [Writing Tests](#writing-tests)
5. [Test Data Factories](#test-data-factories)
6. [Contract Testing](#contract-testing)
7. [Security Testing](#security-testing)
8. [Load Testing](#load-testing)
9. [Mocking External Services](#mocking-external-services)
10. [Test Helpers](#test-helpers)
11. [Configuration](#configuration)
12. [Best Practices](#best-practices)
13. [CI/CD Integration](#cicd-integration)
14. [Troubleshooting](#troubleshooting)

---

## Overview

The Testing & Quality Infrastructure provides comprehensive testing capabilities:

- **Unit Testing** – Test individual components in isolation
- **Integration Testing** – Test component interactions
- **API Testing** – Test HTTP endpoints with assertions
- **Contract Testing** – Validate against OpenAPI specifications
- **Security Testing** – Automated vulnerability scanning
- **Load Testing** – Performance and stress testing
- **Mock Server** – HTTP mocking for external dependencies

### Key Features

✅ **Database Transaction Isolation** – Tests auto-rollback, no cleanup needed
✅ **Test Data Factories** – Generate realistic test data with states
✅ **OpenAPI Contract Validation** – Ensure API compliance automatically
✅ **Security Scanning** – Detect SQL injection, XSS, CSRF, auth bypass
✅ **Performance Testing** – Measure latency, throughput, concurrent handling
✅ **External Service Mocking** – Mock Stripe, SendGrid, Twilio, OAuth providers

---

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Test Environment

Create `.env.testing`:

```env
APP_ENV=testing
DB_DATABASE=phpfrarm_test
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=localhost
MONGO_DATABASE=phpfrarm_test

TESTING=true
DISABLE_EMAIL=true
DISABLE_SMS=true
```

### 3. Run Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite API
vendor/bin/phpunit --testsuite Security

# Run specific test file
vendor/bin/phpunit tests/Unit/UserTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html tests/coverage
```

---

## Test Structure

### Directory Organization

```
tests/
├── bootstrap.php            # Test environment initialization
├── TestCase.php             # Base test case
├── ApiTestCase.php          # API test case
├── Unit/                    # Unit tests
│   └── UserTest.php
├── Integration/             # Integration tests
│   └── AuthFlowTest.php
├── Api/                     # API endpoint tests
│   └── UserApiTest.php
├── Contract/                # OpenAPI contract tests
│   └── ContractTest.php
├── Security/                # Security vulnerability tests
│   └── SecurityTest.php
├── Load/                    # Load and performance tests
│   └── LoadTest.php
└── Factories/               # Test data factories
    ├── Factory.php
    ├── UserFactory.php
    └── FactoryRegistry.php
```

---

## Writing Tests

### Unit Tests

Test individual components in isolation:

```php
<?php

namespace Farm\Backend\Tests\Unit;

use Farm\Backend\Tests\TestCase;

class UserServiceTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_user()
    {
        $userData = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => 'password123'
        ];
        
        $service = new UserService();
        $user = $service->create($userData);
        
        $this->assertNotNull($user['id']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
```

### API Tests

Test HTTP endpoints with fluent API:

```php
<?php

namespace Farm\Backend\Tests\Api;

use Farm\Backend\Tests\ApiTestCase;

class UserApiTest extends ApiTestCase
{
    /**
     * @test
     */
    public function it_registers_new_user()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'name' => 'Test User'
        ]);
        
        $this->assertResponseCreated($response);
        $this->assertJsonHas($response, 'data.token');
        $this->assertHasTraceIds($response);
    }
    
    /**
     * @test
     */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/v1/users/me');
        
        $this->assertResponseUnauthorized($response);
    }
    
    /**
     * @test
     */
    public function it_returns_authenticated_user_profile()
    {
        $user = $this->factory('User')->create();
        
        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me');
        
        $this->assertResponseOk($response);
        $this->assertJsonHas($response, 'data.email');
        $this->assertEquals($user['email'], $response['body']['data']['email']);
    }
}
```

### Available HTTP Methods

```php
// GET request
$response = $this->get('/api/users');
$response = $this->getJson('/api/users');

// POST request
$response = $this->post('/api/users', $data);
$response = $this->postJson('/api/users', $data);

// PUT request
$response = $this->put('/api/users/123', $data);
$response = $this->putJson('/api/users/123', $data);

// DELETE request
$response = $this->delete('/api/users/123');
$response = $this->deleteJson('/api/users/123');

// With authentication
$response = $this->actingAs($user)->getJson('/api/users/me');
$response = $this->withToken($token)->getJson('/api/users/me');

// With custom headers
$response = $this->withHeader('X-Custom', 'value')
    ->getJson('/api/users');

$response = $this->withHeaders([
    'X-Custom' => 'value',
    'X-Another' => 'value2'
])->getJson('/api/users');
```

### Response Assertions

```php
// Status code assertions
$this->assertResponseOk($response);              // 200
$this->assertResponseCreated($response);         // 201
$this->assertResponseNoContent($response);       // 204
$this->assertResponseBadRequest($response);      // 400
$this->assertResponseUnauthorized($response);    // 401
$this->assertResponseForbidden($response);       // 403
$this->assertResponseNotFound($response);        // 404
$this->assertResponseServerError($response);     // 500

// JSON assertions
$this->assertJsonHas($response, 'data.id');
$this->assertJsonHas($response, 'data.user.email');

$this->assertJsonStructure($response['body'], [
    'data' => [
        'id',
        'email',
        'name'
    ],
    'meta' => [
        'correlation_id',
        'transaction_id'
    ]
]);

// Trace ID assertions
$this->assertHasTraceIds($response);

// Database assertions
$this->assertDatabaseHas('users', ['email' => 'test@example.com']);
$this->assertDatabaseMissing('users', ['email' => 'deleted@example.com']);
$this->assertDatabaseCount('users', 5);
```

---

## Test Data Factories

### Creating Test Data

```php
// Create single user
$user = $this->factory('User')->create();

// Create with specific attributes
$user = $this->factory('User')->create([
    'email' => 'specific@example.com',
    'name' => 'Specific Name'
]);

// Create multiple users
$users = $this->factory('User')->createMany(10);

// Make without persisting
$userData = $this->factory('User')->make();
$usersData = $this->factory('User')->makeMany(10);
```

### Using States

```php
// Admin user
$admin = $this->factory('User')->withState('admin')->create();

// Verified user
$verified = $this->factory('User')->withState('verified')->create();

// Email verified only
$emailVerified = $this->factory('User')->withState('emailVerified')->create();

// Suspended user
$suspended = $this->factory('User')->withState('suspended')->create();

// Multiple states
$user = $this->factory('User')
    ->withState('admin')
    ->withState('verified')
    ->create();
```

### Custom Attributes

```php
// Fluent setters
$user = UserFactory::new()
    ->withEmail('custom@example.com')
    ->withPhone('+1234567890')
    ->withPassword('CustomPassword123!')
    ->withRole('moderator')
    ->create();
```

### Creating Custom Factories

```php
<?php

namespace Farm\Backend\Tests\Factories;

class ProductFactory extends Factory
{
    protected function definition(): array
    {
        return [
            'id' => $this->ulid(),
            'name' => $this->randomString(20),
            'price' => rand(1000, 50000) / 100,
            'stock' => rand(0, 100),
            'status' => 'active',
            'created_at' => $this->now(),
            'updated_at' => $this->now()
        ];
    }
    
    protected function model(): string
    {
        return 'Farm\Backend\App\Models\Product';
    }
    
    // Custom state
    public function outOfStock(): self
    {
        $this->attributes['stock'] = 0;
        $this->attributes['status'] = 'out_of_stock';
        return $this;
    }
    
    // Custom setter
    public function withPrice(float $price): self
    {
        $this->attributes['price'] = $price;
        return $this;
    }
}
```

Register factory in `FactoryRegistry`:

```php
private function registerDefaultFactories(): void
{
    $this->register(UserFactory::class);
    $this->register(ProductFactory::class);
}
```

---

## Contract Testing

### Automatic API Contract Validation

Contract testing ensures your API responses match the OpenAPI specification:

```php
<?php

namespace Farm\Backend\Tests\Contract;

use Farm\Backend\Tests\ApiTestCase;
use Farm\Backend\App\Core\Testing\ContractTester;

class UserContractTest extends ApiTestCase
{
    private ContractTester $contractTester;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->contractTester = new ContractTester();
    }
    
    /**
     * @test
     */
    public function it_validates_user_registration_contract()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'name' => 'Test User'
        ]);
        
        // Validate request
        $requestValidation = $this->contractTester->validateRequest(
            'POST',
            '/api/v1/auth/register',
            [],
            [
                'email' => 'test@example.com',
                'password' => 'SecurePassword123!',
                'name' => 'Test User'
            ]
        );
        
        $this->assertTrue($requestValidation->isValid());
        
        // Validate response
        $responseValidation = $this->contractTester->validateResponse(
            'POST',
            '/api/v1/auth/register',
            $response['status'],
            $response['body']
        );
        
        $this->assertTrue(
            $responseValidation->isValid(),
            $responseValidation->getFirstError()
        );
    }
}
```

### Contract Validation Features

- ✅ Request schema validation
- ✅ Response schema validation
- ✅ Required field checking
- ✅ Type validation
- ✅ Format validation (email, uuid, date-time)
- ✅ Constraint validation (min, max, pattern, enum)
- ✅ $ref resolution
- ✅ Nested object validation

---

## Security Testing

### Automated Security Scanning

```php
<?php

namespace Farm\Backend\Tests\Security;

use Farm\Backend\Tests\ApiTestCase;
use Farm\Backend\App\Core\Testing\SecurityTester;

class SecurityTest extends ApiTestCase
{
    private SecurityTester $securityTester;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->securityTester = new SecurityTester();
    }
    
    /**
     * @test
     */
    public function it_scans_for_vulnerabilities()
    {
        $result = $this->securityTester->scan('/api/v1/users');
        
        $this->assertTrue(
            $result->passed(),
            $result->report()
        );
        
        // Check specific vulnerabilities
        $this->assertEmpty($result->getCritical());
        $this->assertEmpty($result->getHigh());
    }
}
```

### Security Tests Included

- ✅ **SQL Injection** – Payloads: `' OR '1'='1`, `'; DROP TABLE`, `UNION SELECT`
- ✅ **XSS** – Payloads: `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>`
- ✅ **Authentication Bypass** – Test endpoints without/with invalid tokens
- ✅ **Rate Limiting** – Send 100+ requests, verify throttling
- ✅ **Input Validation** – Test large payloads, invalid types
- ✅ **Authorization** – Verify resource-level access control
- ✅ **Password Complexity** – Test weak passwords
- ✅ **CSRF Protection** – Verify state-changing requests require tokens
- ✅ **Security Headers** – Check X-Content-Type-Options, X-Frame-Options, etc.

---

## Load Testing

### Performance Testing

```php
<?php

namespace Farm\Backend\Tests\Load;

use Farm\Backend\Tests\ApiTestCase;
use Farm\Backend\App\Core\Testing\LoadTester;

class LoadTest extends ApiTestCase
{
    private LoadTester $loadTester;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadTester = new LoadTester();
    }
    
    /**
     * @test
     */
    public function it_handles_concurrent_requests()
    {
        $result = $this->loadTester->test('/api/v1/users', [
            'method' => 'GET',
            'concurrent_users' => 50,
            'requests_per_user' => 10
        ]);
        
        $this->assertTrue(
            $result->passed([
                'min_success_rate' => 99.0,
                'max_avg_latency' => 200,
                'max_p95_latency' => 500
            ])
        );
        
        echo $result->report();
    }
    
    /**
     * @test
     */
    public function it_handles_stress()
    {
        $result = $this->loadTester->stressTest('/api/v1/health', 60);
        
        $this->assertGreaterThan(90.0, $result->getSuccessRate());
        $this->assertGreaterThan(100, $result->getThroughput());
    }
    
    /**
     * @test
     */
    public function it_handles_traffic_spikes()
    {
        $result = $this->loadTester->spikeTest('/api/v1/health', 1000);
        
        $this->assertGreaterThan(90.0, $result->getSuccessRate());
        $this->assertLessThan(1000, $result->getP99Latency());
    }
}
```

### Load Test Metrics

```php
$result->getTotalRequests();       // Total requests made
$result->getSuccessCount();        // Successful requests
$result->getFailureCount();        // Failed requests
$result->getSuccessRate();         // Success rate (%)

$result->getAverageLatency();      // Average latency (ms)
$result->getMedianLatency();       // Median latency (ms)
$result->getP95Latency();          // 95th percentile (ms)
$result->getP99Latency();          // 99th percentile (ms)
$result->getMinLatency();          // Minimum latency (ms)
$result->getMaxLatency();          // Maximum latency (ms)

$result->getThroughput();          // Requests per second
$result->getTotalTime();           // Test duration (seconds)

$result->report();                 // Generate text report
```

---

## Mocking External Services

### Mock Server

```php
use Farm\Backend\App\Core\Testing\MockServer;

$mock = new MockServer();
$mock->start();

// Configure mock response
$mock->when('POST', '/api/charge')
     ->withBody(['amount' => 5000])
     ->thenReturn(200, [
         'id' => 'ch_123',
         'status' => 'succeeded'
     ]);

// Your code makes HTTP request...

// Assert request was made
$mock->assertCalled('POST', '/api/charge');
$mock->assertCalledTimes('POST', '/api/charge', 1);
$mock->assertNotCalled('POST', '/api/refund');

$mock->stop();
```

### Pre-configured Service Mocks

#### Stripe

```php
use Farm\Backend\App\Core\Testing\ExternalServiceMock;

$stripe = ExternalServiceMock::stripe();

$stripe->mockChargeSuccess('ch_123', 5000);
$stripe->mockChargeFailure('card_declined', 'Your card was declined');
$stripe->mockCustomerCreate('cus_123', 'customer@example.com');

// Your code calls Stripe API...

$stripe->getServer()->assertCalled('POST', '/v1/charges');
```

#### SendGrid (Email)

```php
$sendGrid = ExternalServiceMock::sendGrid();

$sendGrid->mockSendSuccess();
$sendGrid->mockSendFailure('Invalid email address');

// Your code sends email...

$sendGrid->assertSentTo('recipient@example.com');
```

#### Twilio (SMS)

```php
$twilio = ExternalServiceMock::twilio();

$twilio->mockSmsSuccess('SM123');
$twilio->mockSmsFailure(21614, 'To number is not a valid mobile number');

// Your code sends SMS...

$twilio->assertSentTo('+1234567890');
```

#### OAuth Providers

```php
$google = ExternalServiceMock::oauth('google');

$google->mockTokenSuccess('ya29.a0ARrdaM...');
$google->mockUserInfo([
    'id' => '1234567890',
    'email' => 'user@gmail.com',
    'name' => 'John Doe'
]);

// Your code exchanges OAuth code for token...

$google->getServer()->assertCalled('POST', '/token');
```

---

## Test Helpers

### Time Mocking

```php
use Farm\Backend\App\Core\Testing\TestHelper;

// Mock current time
TestHelper::mockTime('2026-01-18 10:00:00');

// Your code uses time()...

// Reset time
TestHelper::resetTime();

// Get mocked current time
$now = TestHelper::now();
```

### Database Seeding

```php
// Seed table with data
TestHelper::seed('users', [
    'email' => 'test@example.com',
    'name' => 'Test User'
]);

// Seed multiple rows
TestHelper::seed('users', [
    ['email' => 'user1@example.com', 'name' => 'User 1'],
    ['email' => 'user2@example.com', 'name' => 'User 2']
]);

// Clear all test data
TestHelper::clearDatabase();

// Clear except specific tables
TestHelper::clearDatabase(['migrations', 'settings']);
```

### Redis Helpers

```php
// Clear Redis cache
TestHelper::clearRedis();
```

### Random Data Generators

```php
// Random string
$string = TestHelper::randomString(10);

// Random email
$email = TestHelper::randomEmail();

// Random phone
$phone = TestHelper::randomPhone();

// ULID
$ulid = TestHelper::ulid();
```

### Temporary Files

```php
// Create temp file
$path = TestHelper::createTempFile('file content', 'txt');

// Use file...

// Clean up
TestHelper::deleteTempFile($path);
```

### Wait for Condition

```php
// Wait up to 5 seconds for condition
$success = TestHelper::waitUntil(function () {
    return JobQueue::isEmpty();
}, 5);

$this->assertTrue($success, 'Queue did not empty in time');
```

---

## Configuration

### Test Configuration File

See `config/testing.php` for full configuration options:

```php
return [
    'test_database' => [
        'database' => env('TEST_DB_DATABASE', 'phpfrarm_test')
    ],
    
    'contract_testing' => [
        'enabled' => true,
        'auto_generate_spec' => true
    ],
    
    'security_testing' => [
        'enabled' => true,
        'tests' => [
            'sql_injection' => true,
            'xss' => true
        ]
    ],
    
    'load_testing' => [
        'performance_criteria' => [
            'min_success_rate' => 99.0,
            'max_avg_latency' => 200
        ]
    ]
];
```

### PHPUnit Configuration

See `phpunit.xml` for test suite configuration:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory suffix="Test.php">./tests/Unit</directory>
    </testsuite>
    
    <testsuite name="API">
        <directory suffix="Test.php">./tests/Api</directory>
    </testsuite>
    
    <testsuite name="Security">
        <directory suffix="Test.php">./tests/Security</directory>
    </testsuite>
</testsuites>
```

---

## Best Practices

### Test Organization

✅ **DO:**
- One test class per class/feature under test
- Use descriptive test method names: `it_creates_user_successfully()`
- Group related tests in same file
- Use `@test` annotation or `test` prefix

❌ **DON'T:**
- Mix unit and integration tests in same file
- Write tests dependent on execution order
- Share state between tests

### Test Data

✅ **DO:**
- Use factories for test data
- Create minimal data needed for test
- Use transactions for automatic rollback
- Use descriptive factory states

❌ **DON'T:**
- Hardcode test data IDs
- Reuse data between tests
- Leave test data in database

### Assertions

✅ **DO:**
- Use specific assertions: `assertResponseCreated()` not `assertEquals(201, $status)`
- Assert expected behavior, not implementation
- Add assertion messages for clarity
- Use database assertions to verify persistence

❌ **DON'T:**
- Use generic `assertTrue()` for everything
- Assert too many things in one test
- Write tests without assertions

### Mocking

✅ **DO:**
- Mock external services
- Use framework-provided mocks
- Assert mock interactions
- Reset mocks between tests

❌ **DON'T:**
- Mock internal classes unnecessarily
- Forget to assert mock was called
- Share mock instances between tests

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: phpfrarm_test
        ports:
          - 3306:3306
      
      redis:
        image: redis:7
        ports:
          - 6379:6379
      
      mongodb:
        image: mongo:6
        ports:
          - 27017:27017
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: pdo_mysql, redis, mongodb
      
      - name: Install Dependencies
        run: composer install
      
      - name: Run Unit Tests
        run: vendor/bin/phpunit --testsuite Unit
      
      - name: Run API Tests
        run: vendor/bin/phpunit --testsuite API
      
      - name: Run Security Tests
        run: vendor/bin/phpunit --testsuite Security
      
      - name: Run Contract Tests
        run: vendor/bin/phpunit --testsuite Contract
      
      - name: Generate Coverage
        run: vendor/bin/phpunit --coverage-clover coverage.xml
      
      - name: Upload Coverage
        uses: codecov/codecov-action@v3
```

---

## Troubleshooting

### Common Issues

#### Issue: "Database connection failed"

**Solution:**
- Check `.env.testing` database credentials
- Ensure test database exists: `CREATE DATABASE phpfrarm_test`
- Verify MySQL service is running

#### Issue: "Factory not found"

**Solution:**
- Register factory in `FactoryRegistry::registerDefaultFactories()`
- Check factory class namespace
- Ensure factory extends `Factory`

#### Issue: "Contract validation failed"

**Solution:**
- Generate OpenAPI spec: `php artisan docs:generate`
- Check endpoint exists in spec
- Verify response structure matches schema
- Check required fields are present

#### Issue: "Tests are slow"

**Solution:**
- Use transactions for database isolation
- Reduce factory data volume
- Mock external services
- Run specific test suites only
- Enable parallel testing in phpunit.xml

#### Issue: "Intermittent test failures"

**Solution:**
- Check for shared state between tests
- Ensure database is cleared between tests
- Reset time mocking after tests
- Check for race conditions in async code

---

## Advanced Topics

### Parallel Testing

Enable in `config/testing.php`:

```php
'parallel' => [
    'enabled' => true,
    'processes' => 4
]
```

### Custom Assertions

Add to `TestCase.php`:

```php
protected function assertValidUlid(string $value): void
{
    $this->assertMatchesRegularExpression(
        '/^[0-9A-HJKMNP-TV-Z]{26}$/',
        $value,
        'Invalid ULID format'
    );
}
```

### Test Listeners

Create listener in `tests/Listeners/FailureLogger.php`:

```php
class FailureLogger implements TestListener
{
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        // Log to file or external service
        file_put_contents(
            'tests/failures.log',
            sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $test->getName(), $e->getMessage()),
            FILE_APPEND
        );
    }
}
```

---

## Summary

You now have a comprehensive testing infrastructure with:

✅ **5 Test Types** – Unit, Integration, API, Contract, Security, Load
✅ **Test Data Factories** – Generate realistic data with states
✅ **OpenAPI Contract Testing** – Auto-validate against spec
✅ **Security Scanning** – Detect 7+ vulnerability types
✅ **Load Testing** – Measure performance under load
✅ **External Service Mocking** – Stripe, SendGrid, Twilio, OAuth
✅ **80+ Helper Methods** – Assertions, factories, time mocking, seeding

### Next Steps

1. Write tests for existing endpoints
2. Configure CI/CD pipeline
3. Set coverage thresholds
4. Add custom factories for your models
5. Create domain-specific assertions

---

**For questions or support, consult the framework documentation or contact the team.**
