# Circuit Breaker Implementation Guide

## Overview
The Circuit Breaker pattern prevents cascading failures by stopping requests to failing external services.

## How It Works

### States
1. **CLOSED**: Normal operation, requests pass through
2. **OPEN**: Service is failing, all requests fail fast without trying
3. **HALF_OPEN**: Testing if service has recovered

### State Transitions
```
CLOSED --[failures >= threshold]--> OPEN
OPEN --[timeout elapsed]--> HALF_OPEN
HALF_OPEN --[success >= threshold]--> CLOSED
HALF_OPEN --[any failure]--> OPEN
```

## Usage Examples

### Basic Usage
```php
<?php
use PHPFrarm\Core\Resilience\CircuitBreaker;
use PHPFrarm\Core\Resilience\CircuitBreakerException;

$breaker = new CircuitBreaker(
    name: 'payment_gateway',
    failureThreshold: 5,      // Open after 5 failures
    timeout: 60,              // Wait 60s before trying again
    successThreshold: 2       // Need 2 successes to close
);

try {
    $result = $breaker->call(function() {
        // Your external API call
        return callPaymentGateway();
    });
    
    echo "Payment successful: " . $result;
    
} catch (CircuitBreakerException $e) {
    // Circuit is OPEN - service is down
    echo "Service temporarily unavailable: " . $e->getMessage();
    
} catch (\Exception $e) {
    // Other errors from the actual call
    echo "Payment failed: " . $e->getMessage();
}
```

### Service Layer Integration
```php
<?php
namespace PHPFrarm\Modules\Payment\Services;

use PHPFrarm\Core\Resilience\CircuitBreaker;

class PaymentService
{
    private CircuitBreaker $breaker;
    
    public function __construct()
    {
        $this->breaker = new CircuitBreaker(
            'payment_api',
            failureThreshold: 5,
            timeout: 120
        );
    }
    
    public function processPayment(array $data): array
    {
        try {
            return $this->breaker->call(function() use ($data) {
                // Call external payment API
                $ch = curl_init('https://payment-api.example.com/charge');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    throw new \Exception("Payment API returned $httpCode");
                }
                
                return json_decode($response, true);
            });
            
        } catch (CircuitBreakerException $e) {
            // Circuit is open - use fallback
            Logger::warning('Payment service unavailable, using fallback');
            return $this->fallbackPaymentMethod($data);
        }
    }
    
    private function fallbackPaymentMethod(array $data): array
    {
        // Queue for later processing or use backup gateway
        return [
            'status' => 'queued',
            'message' => 'Payment queued for processing'
        ];
    }
}
```

### Multiple Circuit Breakers
```php
<?php
class ExternalServiceManager
{
    private array $breakers = [];
    
    public function __construct()
    {
        // Different circuit breakers for different services
        $this->breakers['payment'] = new CircuitBreaker('payment_api', 5, 60);
        $this->breakers['email'] = new CircuitBreaker('email_api', 10, 30);
        $this->breakers['sms'] = new CircuitBreaker('sms_api', 3, 120);
    }
    
    public function callService(string $service, callable $callback): mixed
    {
        if (!isset($this->breakers[$service])) {
            throw new \Exception("Unknown service: $service");
        }
        
        return $this->breakers[$service]->call($callback);
    }
    
    public function getServiceStatus(string $service): array
    {
        return $this->breakers[$service]->getStats();
    }
}
```

### Monitoring Endpoint
```php
<?php
namespace PHPFrarm\Modules\Admin\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Response;

#[RouteGroup('/api/v1/system/circuit-breakers')]
class CircuitBreakerController
{
    #[Route('/', method: 'GET', middleware: ['auth', 'adminOnly'])]
    public function listAll(array $request): void
    {
        $breakers = [
            $this->getBreaker('payment_api'),
            $this->getBreaker('email_api'),
            $this->getBreaker('sms_api'),
        ];
        
        Response::success($breakers);
    }
    
    #[Route('/{name}/reset', method: 'POST', middleware: ['auth', 'adminOnly'])]
    public function reset(array $request, string $name): void
    {
        $breaker = new CircuitBreaker($name);
        $breaker->reset();
        
        Response::success(null, 'Circuit breaker reset successfully');
    }
    
    private function getBreaker(string $name): array
    {
        $breaker = new CircuitBreaker($name);
        return $breaker->getStats();
    }
}
```

## Configuration

### Environment Variables
```env
# Circuit Breaker Defaults
CIRCUIT_BREAKER_FAILURE_THRESHOLD=5
CIRCUIT_BREAKER_TIMEOUT=60
CIRCUIT_BREAKER_SUCCESS_THRESHOLD=2
```

### Per-Service Configuration
```php
<?php
// config/circuit_breakers.php
return [
    'payment_api' => [
        'failure_threshold' => 5,
        'timeout' => 120,
        'success_threshold' => 2,
    ],
    'email_api' => [
        'failure_threshold' => 10,
        'timeout' => 30,
        'success_threshold' => 3,
    ],
];
```

## Monitoring & Metrics

### Get Statistics
```php
$breaker = new CircuitBreaker('payment_api');
$stats = $breaker->getStats();

/*
Returns:
[
    'name' => 'payment_api',
    'state' => 'closed',
    'failure_count' => 2,
    'consecutive_failures' => 0,
    'consecutive_successes' => 5,
    'total_successes' => 150,
    'total_failures' => 3,
    'last_failure_time' => 1234567890,
    'last_success_time' => 1234567900,
    'last_exception' => 'ConnectionException: Timeout',
    'state_changed_at' => 1234567800
]
*/
```

### Logging
Circuit breaker automatically logs state changes:
- Opening (service failing)
- Closing (service recovered)
- Transitioning to half-open (testing recovery)

## Best Practices

1. **Use Descriptive Names**: Name circuit breakers after the service they protect
2. **Tune Thresholds**: Adjust based on service SLA and tolerance
3. **Implement Fallbacks**: Provide degraded functionality when circuit is open
4. **Monitor Metrics**: Track state changes and failure rates
5. **Alert on Open Circuits**: Set up alerts when circuits open
6. **Manual Reset**: Provide admin interface to manually reset circuits
7. **Different Settings**: Use different thresholds for critical vs non-critical services

## Common Patterns

### With Retry Logic
```php
$breaker->call(function() use ($maxRetries) {
    $retries = 0;
    while ($retries < $maxRetries) {
        try {
            return callExternalAPI();
        } catch (\Exception $e) {
            $retries++;
            if ($retries >= $maxRetries) {
                throw $e;
            }
            sleep(1);
        }
    }
});
```

### With Timeout
```php
$breaker->call(function() {
    $context = stream_context_create([
        'http' => ['timeout' => 5] // 5 second timeout
    ]);
    return file_get_contents('https://api.example.com/data', false, $context);
});
```

## Storage
Circuit breaker state is stored in filesystem at:
```
/tmp/phpfrarm/circuit_breakers/{md5_hash}.json
```

For production, consider using Redis for shared state across servers.
