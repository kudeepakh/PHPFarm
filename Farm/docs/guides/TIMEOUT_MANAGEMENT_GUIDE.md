# Timeout Management Implementation Guide

## Overview
The Timeout Manager prevents long-running operations from hanging your application by enforcing time limits on operations.

## Features
- Function execution timeout
- Database query timeout
- HTTP request timeout
- Configurable per operation type
- Automatic logging and monitoring

## Usage Examples

### Basic Timeout
```php
<?php
use PHPFrarm\Core\Resilience\TimeoutManager;
use PHPFrarm\Core\Resilience\TimeoutException;

$timeout = new TimeoutManager(5); // 5 seconds max

try {
    $result = $timeout->execute(function() {
        // Your long-running operation
        return heavyComputation();
    });
    
    echo "Result: " . $result;
    
} catch (TimeoutException $e) {
    echo "Operation timed out: " . $e->getMessage();
}
```

### Database Query Timeout
```php
<?php
use PHPFrarm\Core\Resilience\TimeoutManager;

// Automatically applied in Database class
$results = TimeoutManager::forDatabase(function() use ($db) {
    return $db->callProcedure('sp_complex_report', []);
}, 10); // 10 second timeout
```

### HTTP Request Timeout
```php
<?php
use PHPFrarm\Core\Resilience\TimeoutManager;

$response = TimeoutManager::forHttpRequest(
    'https://external-api.com/data',
    [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ],
    30 // 30 second timeout
);

echo "HTTP Code: " . $response['http_code'];
echo "Response: " . $response['response'];
```

### Configuration-Based Timeout
```php
<?php
use PHPFrarm\Core\Resilience\TimeoutManager;

// Loads timeout from environment variable
$timeout = TimeoutManager::fromConfig('database'); // TIMEOUT_DATABASE
$result = $timeout->execute(function() {
    return performDatabaseOperation();
});
```

## Service Integration

### Payment Service Example
```php
<?php
namespace PHPFrarm\Modules\Payment\Services;

use PHPFrarm\Core\Resilience\TimeoutManager;
use PHPFrarm\Core\Resilience\TimeoutException;

class PaymentService
{
    public function processPayment(array $data): array
    {
        $timeout = TimeoutManager::fromConfig('api');
        
        try {
            return $timeout->execute(function() use ($data) {
                // Call payment gateway
                return $this->callPaymentGateway($data);
            });
            
        } catch (TimeoutException $e) {
            Logger::error('Payment processing timed out', [
                'transaction_id' => $data['transaction_id']
            ]);
            
            // Queue for retry
            $this->queueForRetry($data);
            
            throw new \Exception('Payment processing timed out. Will retry.');
        }
    }
}
```

### Report Generation Example
```php
<?php
namespace PHPFrarm\Modules\Reports\Services;

use PHPFrarm\Core\Resilience\TimeoutManager;

class ReportService
{
    public function generateLargeReport(array $filters): array
    {
        // Long timeout for reports
        $timeout = new TimeoutManager(300); // 5 minutes
        
        try {
            return $timeout->execute(function() use ($filters) {
                $data = $this->fetchReportData($filters);
                $processed = $this->processData($data);
                $formatted = $this->formatReport($processed);
                
                return $formatted;
            });
            
        } catch (TimeoutException $e) {
            // Generate async job instead
            return $this->queueReportJob($filters);
        }
    }
}
```

## Configuration

### Environment Variables
```env
# Timeout settings (seconds)
TIMEOUT_DEFAULT=30
TIMEOUT_DATABASE=10
TIMEOUT_HTTP=30
TIMEOUT_API=30
TIMEOUT_JOB=300

# Database specific
DB_QUERY_TIMEOUT=10
```

### Per-Operation Configuration
```php
<?php
// config/timeouts.php
return [
    'database' => [
        'select' => 10,
        'insert' => 5,
        'update' => 5,
        'delete' => 5,
        'procedure' => 30,
    ],
    'http' => [
        'get' => 30,
        'post' => 60,
        'upload' => 120,
    ],
    'jobs' => [
        'email' => 60,
        'report' => 300,
        'import' => 600,
    ],
];
```

## Monitoring

### Check Execution Time
```php
$timeout = new TimeoutManager(30);
$timeout->execute(function() {
    performOperation();
});

$elapsed = $timeout->getElapsedTime();
echo "Operation took $elapsed seconds";
```

### Warning for Slow Operations
```php
$timeout->execute(function() use ($timeout) {
    $result = slowOperation();
    
    // Check if approaching timeout
    if ($timeout->getRemainingTime() < 5) {
        Logger::warning('Operation nearing timeout', [
            'remaining_seconds' => $timeout->getRemainingTime()
        ]);
    }
    
    return $result;
});
```

## Automatic Application

### Database Layer
All database queries automatically have timeout protection:
```php
// In Database class
$results = $db->callProcedure('sp_users_list'); 
// Automatically wrapped with TimeoutManager::forDatabase()
```

### HTTP Middleware (Optional)
```php
<?php
namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Resilience\TimeoutManager;
use PHPFrarm\Core\Response;

class RequestTimeoutMiddleware
{
    public static function handle(array $request, callable $next): mixed
    {
        $timeout = TimeoutManager::fromConfig('api');
        
        try {
            return $timeout->execute(function() use ($request, $next) {
                return $next($request);
            });
            
        } catch (TimeoutException $e) {
            Logger::error('Request timed out', [
                'path' => $request['path']
            ]);
            
            Response::error('Request timeout', 504, 'REQUEST_TIMEOUT');
            return null;
        }
    }
}
```

## Best Practices

1. **Set Appropriate Timeouts**: Different operations need different limits
   - Quick queries: 5-10 seconds
   - API calls: 30 seconds
   - Reports/jobs: 5-10 minutes

2. **Handle Timeouts Gracefully**: Don't just fail, provide alternatives
   - Queue for async processing
   - Return cached data
   - Provide partial results

3. **Log Slow Operations**: Track operations approaching timeout
   - Set warning threshold at 80% of timeout
   - Monitor patterns in logs
   - Optimize frequently timing out operations

4. **Use Circuit Breakers Together**: Combine with circuit breaker for external services
   ```php
   $breaker = new CircuitBreaker('payment_api');
   $timeout = TimeoutManager::fromConfig('api');
   
   $breaker->call(function() use ($timeout) {
       return $timeout->execute(function() {
           return callPaymentAPI();
       });
   });
   ```

5. **Database Connection Timeout**: Set at DB level too
   ```php
   // In Database class connection
   $options = [
       \PDO::ATTR_TIMEOUT => 10,
       \PDO::MYSQL_ATTR_READ_TIMEOUT => 10,
       \PDO::MYSQL_ATTR_WRITE_TIMEOUT => 10,
   ];
   ```

## Common Patterns

### Retry with Timeout
```php
$maxRetries = 3;
$retries = 0;

while ($retries < $maxRetries) {
    try {
        $timeout = new TimeoutManager(10);
        $result = $timeout->execute(function() {
            return externalAPICall();
        });
        
        break; // Success
        
    } catch (TimeoutException $e) {
        $retries++;
        if ($retries >= $maxRetries) {
            throw $e;
        }
        sleep(2); // Backoff
    }
}
```

### Fallback on Timeout
```php
try {
    $timeout = new TimeoutManager(5);
    $data = $timeout->execute(function() {
        return fetchFromAPI();
    });
} catch (TimeoutException $e) {
    // Use cached data as fallback
    $data = $this->getCachedData();
    Logger::info('Using cached data due to timeout');
}
```

## Troubleshooting

### Timeout Not Working
- Check PHP `max_execution_time` setting
- Verify timeout value is appropriate
- Check if operation is truly blocking

### False Positives
- Operations timing out too frequently
- Increase timeout or optimize operation
- Use async processing for long operations

### Logging
All timeouts are automatically logged:
- Warning when approaching timeout (80%)
- Error when timeout occurs
- Elapsed time tracked
