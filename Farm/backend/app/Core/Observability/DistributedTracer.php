<?php

namespace PHPFrarm\Core\Observability;

use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\Logger;

/**
 * Distributed Tracing Manager
 * 
 * Integrates with Jaeger, Zipkin, and OpenTelemetry for distributed tracing.
 * Automatically propagates trace context across service boundaries.
 * 
 * Features:
 * - Jaeger integration (Thrift over HTTP)
 * - Zipkin integration (JSON over HTTP)
 * - OpenTelemetry compatible
 * - W3C Trace Context propagation
 * - B3 header propagation (Zipkin)
 * - Span creation and management
 * - Performance metrics collection
 * 
 * @package PHPFrarm\Core\Observability
 */
class DistributedTracer
{
    private static ?self $instance = null;
    private array $config;
    private array $spans = [];
    private ?array $rootSpan = null;
    private string $serviceName;
    private bool $enabled;
    
    /**
     * Tracer backends
     */
    private const BACKEND_JAEGER = 'jaeger';
    private const BACKEND_ZIPKIN = 'zipkin';
    private const BACKEND_OTLP = 'otlp';
    private const BACKEND_NONE = 'none';
    
    private function __construct()
    {
        $this->config = $this->loadConfig();
        $this->serviceName = $this->config['service_name'] ?? 'phpfrarm-api';
        $this->enabled = $this->config['enabled'] ?? false;
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load tracing configuration
     */
    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/tracing.php';
        return file_exists($configPath) ? require $configPath : [
            'enabled' => (bool) ($_ENV['TRACING_ENABLED'] ?? false),
            'backend' => $_ENV['TRACING_BACKEND'] ?? 'jaeger',
            'service_name' => $_ENV['SERVICE_NAME'] ?? 'phpfrarm-api',
            'jaeger' => [
                'endpoint' => $_ENV['JAEGER_ENDPOINT'] ?? 'http://jaeger:14268/api/traces',
                'agent_host' => $_ENV['JAEGER_AGENT_HOST'] ?? 'jaeger',
                'agent_port' => (int) ($_ENV['JAEGER_AGENT_PORT'] ?? 6831),
            ],
            'zipkin' => [
                'endpoint' => $_ENV['ZIPKIN_ENDPOINT'] ?? 'http://zipkin:9411/api/v2/spans',
            ],
            'otlp' => [
                'endpoint' => $_ENV['OTLP_ENDPOINT'] ?? 'http://otel-collector:4318/v1/traces',
            ],
            'sampling' => [
                'rate' => (float) ($_ENV['TRACING_SAMPLE_RATE'] ?? 1.0),
                'type' => 'probabilistic', // probabilistic, rate-limiting, always-on
            ],
        ];
    }
    
    /**
     * Start a new root span for the request
     */
    public function startRootSpan(string $operationName, array $tags = []): string
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return '';
        }
        
        $traceId = $this->extractTraceId() ?? $this->generateTraceId();
        $spanId = $this->generateSpanId();
        $parentSpanId = $this->extractParentSpanId();
        
        $this->rootSpan = [
            'traceId' => $traceId,
            'spanId' => $spanId,
            'parentSpanId' => $parentSpanId,
            'operationName' => $operationName,
            'serviceName' => $this->serviceName,
            'startTime' => $this->microtime(),
            'duration' => null,
            'tags' => array_merge([
                'http.method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'http.url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'http.host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'correlation_id' => TraceContext::getCorrelationId(),
                'transaction_id' => TraceContext::getTransactionId(),
                'request_id' => TraceContext::getRequestId(),
                'span.kind' => 'server',
            ], $tags),
            'logs' => [],
            'status' => 'OK',
        ];
        
        $this->spans[$spanId] = &$this->rootSpan;
        
        // Set response headers for downstream propagation
        $this->setResponseHeaders($traceId, $spanId);
        
        return $spanId;
    }
    
    /**
     * Start a child span
     */
    public function startSpan(string $operationName, ?string $parentSpanId = null, array $tags = []): string
    {
        if (!$this->enabled || $this->rootSpan === null) {
            return '';
        }
        
        $spanId = $this->generateSpanId();
        $parentId = $parentSpanId ?? $this->rootSpan['spanId'];
        
        $span = [
            'traceId' => $this->rootSpan['traceId'],
            'spanId' => $spanId,
            'parentSpanId' => $parentId,
            'operationName' => $operationName,
            'serviceName' => $this->serviceName,
            'startTime' => $this->microtime(),
            'duration' => null,
            'tags' => array_merge([
                'span.kind' => 'internal',
            ], $tags),
            'logs' => [],
            'status' => 'OK',
        ];
        
        $this->spans[$spanId] = $span;
        
        return $spanId;
    }
    
    /**
     * Finish a span
     */
    public function finishSpan(string $spanId, ?int $httpStatus = null): void
    {
        if (!$this->enabled || !isset($this->spans[$spanId])) {
            return;
        }
        
        $endTime = $this->microtime();
        $this->spans[$spanId]['duration'] = $endTime - $this->spans[$spanId]['startTime'];
        
        if ($httpStatus !== null) {
            $this->spans[$spanId]['tags']['http.status_code'] = $httpStatus;
            
            if ($httpStatus >= 400) {
                $this->spans[$spanId]['status'] = $httpStatus >= 500 ? 'ERROR' : 'CLIENT_ERROR';
                $this->spans[$spanId]['tags']['error'] = true;
            }
        }
    }
    
    /**
     * Add a log/event to a span
     */
    public function logEvent(string $spanId, string $event, array $fields = []): void
    {
        if (!$this->enabled || !isset($this->spans[$spanId])) {
            return;
        }
        
        $this->spans[$spanId]['logs'][] = [
            'timestamp' => $this->microtime(),
            'event' => $event,
            'fields' => $fields,
        ];
    }
    
    /**
     * Add tags to a span
     */
    public function setTags(string $spanId, array $tags): void
    {
        if (!$this->enabled || !isset($this->spans[$spanId])) {
            return;
        }
        
        $this->spans[$spanId]['tags'] = array_merge(
            $this->spans[$spanId]['tags'],
            $tags
        );
    }
    
    /**
     * Set span as error
     */
    public function setError(string $spanId, \Throwable $exception): void
    {
        if (!$this->enabled || !isset($this->spans[$spanId])) {
            return;
        }
        
        $this->spans[$spanId]['status'] = 'ERROR';
        $this->spans[$spanId]['tags']['error'] = true;
        $this->spans[$spanId]['tags']['error.type'] = get_class($exception);
        $this->spans[$spanId]['tags']['error.message'] = $exception->getMessage();
        
        $this->logEvent($spanId, 'error', [
            'error.kind' => get_class($exception),
            'message' => $exception->getMessage(),
            'stack' => $exception->getTraceAsString(),
        ]);
    }
    
    /**
     * Finish root span and send all spans to backend
     */
    public function flush(?int $httpStatus = null): void
    {
        if (!$this->enabled || $this->rootSpan === null) {
            return;
        }
        
        // Finish root span
        $this->finishSpan($this->rootSpan['spanId'], $httpStatus);
        
        // Send spans to backend
        $this->sendSpans();
        
        // Clear spans
        $this->spans = [];
        $this->rootSpan = null;
    }
    
    /**
     * Send spans to configured backend
     */
    private function sendSpans(): void
    {
        $backend = $this->config['backend'] ?? self::BACKEND_JAEGER;
        
        try {
            switch ($backend) {
                case self::BACKEND_JAEGER:
                    $this->sendToJaeger();
                    break;
                    
                case self::BACKEND_ZIPKIN:
                    $this->sendToZipkin();
                    break;
                    
                case self::BACKEND_OTLP:
                    $this->sendToOtlp();
                    break;
                    
                default:
                    // No backend configured
                    break;
            }
        } catch (\Exception $e) {
            Logger::error('Failed to send traces', [
                'backend' => $backend,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Send spans to Jaeger
     */
    private function sendToJaeger(): void
    {
        $endpoint = $this->config['jaeger']['endpoint'] ?? 'http://jaeger:14268/api/traces';
        
        // Convert spans to Jaeger Thrift format
        $batch = [
            'process' => [
                'serviceName' => $this->serviceName,
                'tags' => [
                    ['key' => 'hostname', 'vType' => 'STRING', 'vStr' => gethostname()],
                    ['key' => 'ip', 'vType' => 'STRING', 'vStr' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'],
                ],
            ],
            'spans' => array_map([$this, 'formatSpanForJaeger'], array_values($this->spans)),
        ];
        
        $this->httpPost($endpoint, $batch, [
            'Content-Type: application/x-thrift',
        ]);
    }
    
    /**
     * Format span for Jaeger
     */
    private function formatSpanForJaeger(array $span): array
    {
        $tags = [];
        foreach ($span['tags'] as $key => $value) {
            $tags[] = [
                'key' => $key,
                'vType' => is_bool($value) ? 'BOOL' : (is_int($value) ? 'LONG' : 'STRING'),
                'vBool' => is_bool($value) ? $value : null,
                'vLong' => is_int($value) ? $value : null,
                'vStr' => is_string($value) ? $value : (string) $value,
            ];
        }
        
        $logs = array_map(function ($log) {
            $fields = [['key' => 'event', 'vType' => 'STRING', 'vStr' => $log['event']]];
            foreach ($log['fields'] as $key => $value) {
                $fields[] = ['key' => $key, 'vType' => 'STRING', 'vStr' => (string) $value];
            }
            return [
                'timestamp' => (int) ($log['timestamp'] * 1000000), // microseconds
                'fields' => $fields,
            ];
        }, $span['logs']);
        
        return [
            'traceIdLow' => $this->hexToLong(substr($span['traceId'], 16)),
            'traceIdHigh' => $this->hexToLong(substr($span['traceId'], 0, 16)),
            'spanId' => $this->hexToLong($span['spanId']),
            'parentSpanId' => $span['parentSpanId'] ? $this->hexToLong($span['parentSpanId']) : 0,
            'operationName' => $span['operationName'],
            'references' => [],
            'flags' => 1,
            'startTime' => (int) ($span['startTime'] * 1000000), // microseconds
            'duration' => (int) (($span['duration'] ?? 0) * 1000000), // microseconds
            'tags' => $tags,
            'logs' => $logs,
        ];
    }
    
    /**
     * Send spans to Zipkin
     */
    private function sendToZipkin(): void
    {
        $endpoint = $this->config['zipkin']['endpoint'] ?? 'http://zipkin:9411/api/v2/spans';
        
        $spans = array_map([$this, 'formatSpanForZipkin'], array_values($this->spans));
        
        $this->httpPost($endpoint, $spans, [
            'Content-Type: application/json',
        ]);
    }
    
    /**
     * Format span for Zipkin
     */
    private function formatSpanForZipkin(array $span): array
    {
        return [
            'traceId' => $span['traceId'],
            'id' => $span['spanId'],
            'parentId' => $span['parentSpanId'],
            'name' => $span['operationName'],
            'kind' => $this->mapSpanKind($span['tags']['span.kind'] ?? 'internal'),
            'timestamp' => (int) ($span['startTime'] * 1000000), // microseconds
            'duration' => (int) (($span['duration'] ?? 0) * 1000000), // microseconds
            'localEndpoint' => [
                'serviceName' => $this->serviceName,
                'ipv4' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            ],
            'tags' => array_map('strval', $span['tags']),
            'annotations' => array_map(function ($log) {
                return [
                    'timestamp' => (int) ($log['timestamp'] * 1000000),
                    'value' => $log['event'],
                ];
            }, $span['logs']),
        ];
    }
    
    /**
     * Send spans to OpenTelemetry Collector
     */
    private function sendToOtlp(): void
    {
        $endpoint = $this->config['otlp']['endpoint'] ?? 'http://otel-collector:4318/v1/traces';
        
        $resourceSpans = [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                            ['key' => 'host.name', 'value' => ['stringValue' => gethostname()]],
                        ],
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => [
                                'name' => 'phpfrarm-tracer',
                                'version' => '1.0.0',
                            ],
                            'spans' => array_map([$this, 'formatSpanForOtlp'], array_values($this->spans)),
                        ],
                    ],
                ],
            ],
        ];
        
        $this->httpPost($endpoint, $resourceSpans, [
            'Content-Type: application/json',
        ]);
    }
    
    /**
     * Format span for OpenTelemetry
     */
    private function formatSpanForOtlp(array $span): array
    {
        $attributes = [];
        foreach ($span['tags'] as $key => $value) {
            $attributes[] = [
                'key' => $key,
                'value' => is_bool($value) 
                    ? ['boolValue' => $value]
                    : (is_int($value) 
                        ? ['intValue' => $value]
                        : ['stringValue' => (string) $value]),
            ];
        }
        
        return [
            'traceId' => base64_encode(hex2bin($span['traceId'])),
            'spanId' => base64_encode(hex2bin($span['spanId'])),
            'parentSpanId' => $span['parentSpanId'] 
                ? base64_encode(hex2bin($span['parentSpanId'])) 
                : '',
            'name' => $span['operationName'],
            'kind' => $this->mapOtlpSpanKind($span['tags']['span.kind'] ?? 'internal'),
            'startTimeUnixNano' => (int) ($span['startTime'] * 1000000000),
            'endTimeUnixNano' => (int) (($span['startTime'] + ($span['duration'] ?? 0)) * 1000000000),
            'attributes' => $attributes,
            'status' => [
                'code' => $span['status'] === 'ERROR' ? 2 : 1,
            ],
            'events' => array_map(function ($log) {
                return [
                    'timeUnixNano' => (int) ($log['timestamp'] * 1000000000),
                    'name' => $log['event'],
                    'attributes' => array_map(function ($key, $value) {
                        return ['key' => $key, 'value' => ['stringValue' => (string) $value]];
                    }, array_keys($log['fields']), array_values($log['fields'])),
                ];
            }, $span['logs']),
        ];
    }
    
    /**
     * Map span kind for Zipkin
     */
    private function mapSpanKind(string $kind): string
    {
        return match ($kind) {
            'server' => 'SERVER',
            'client' => 'CLIENT',
            'producer' => 'PRODUCER',
            'consumer' => 'CONSUMER',
            default => 'SERVER',
        };
    }
    
    /**
     * Map span kind for OTLP
     */
    private function mapOtlpSpanKind(string $kind): int
    {
        return match ($kind) {
            'internal' => 1,
            'server' => 2,
            'client' => 3,
            'producer' => 4,
            'consumer' => 5,
            default => 1,
        };
    }
    
    /**
     * Extract trace ID from incoming headers
     */
    private function extractTraceId(): ?string
    {
        // W3C Trace Context
        if (isset($_SERVER['HTTP_TRACEPARENT'])) {
            $parts = explode('-', $_SERVER['HTTP_TRACEPARENT']);
            if (count($parts) >= 2) {
                return $parts[1];
            }
        }
        
        // B3 Single Header
        if (isset($_SERVER['HTTP_B3'])) {
            $parts = explode('-', $_SERVER['HTTP_B3']);
            return $parts[0] ?? null;
        }
        
        // B3 Multi Header
        if (isset($_SERVER['HTTP_X_B3_TRACEID'])) {
            return $_SERVER['HTTP_X_B3_TRACEID'];
        }
        
        // Jaeger
        if (isset($_SERVER['HTTP_UBER_TRACE_ID'])) {
            $parts = explode(':', $_SERVER['HTTP_UBER_TRACE_ID']);
            return $parts[0] ?? null;
        }
        
        return null;
    }
    
    /**
     * Extract parent span ID from incoming headers
     */
    private function extractParentSpanId(): ?string
    {
        // W3C Trace Context
        if (isset($_SERVER['HTTP_TRACEPARENT'])) {
            $parts = explode('-', $_SERVER['HTTP_TRACEPARENT']);
            if (count($parts) >= 3) {
                return $parts[2];
            }
        }
        
        // B3 Single Header
        if (isset($_SERVER['HTTP_B3'])) {
            $parts = explode('-', $_SERVER['HTTP_B3']);
            return $parts[1] ?? null;
        }
        
        // B3 Multi Header
        if (isset($_SERVER['HTTP_X_B3_SPANID'])) {
            return $_SERVER['HTTP_X_B3_SPANID'];
        }
        
        // Jaeger
        if (isset($_SERVER['HTTP_UBER_TRACE_ID'])) {
            $parts = explode(':', $_SERVER['HTTP_UBER_TRACE_ID']);
            return $parts[1] ?? null;
        }
        
        return null;
    }
    
    /**
     * Set response headers for propagation
     */
    private function setResponseHeaders(string $traceId, string $spanId): void
    {
        // W3C Trace Context
        header("traceparent: 00-{$traceId}-{$spanId}-01");
        
        // B3 headers
        header("X-B3-TraceId: {$traceId}");
        header("X-B3-SpanId: {$spanId}");
        header("X-B3-Sampled: 1");
    }
    
    /**
     * Generate propagation headers for outgoing requests
     */
    public function getPropagationHeaders(): array
    {
        if ($this->rootSpan === null) {
            return [];
        }
        
        $traceId = $this->rootSpan['traceId'];
        $spanId = $this->rootSpan['spanId'];
        
        return [
            // W3C Trace Context
            'traceparent' => "00-{$traceId}-{$spanId}-01",
            
            // B3 Multi Headers
            'X-B3-TraceId' => $traceId,
            'X-B3-SpanId' => $spanId,
            'X-B3-Sampled' => '1',
            
            // Jaeger
            'uber-trace-id' => "{$traceId}:{$spanId}:0:1",
            
            // App-specific
            'X-Correlation-Id' => TraceContext::getCorrelationId(),
            'X-Transaction-Id' => TraceContext::getTransactionId(),
        ];
    }
    
    /**
     * Determine if request should be sampled
     */
    private function shouldSample(): bool
    {
        $rate = $this->config['sampling']['rate'] ?? 1.0;
        
        if ($rate >= 1.0) {
            return true;
        }
        
        if ($rate <= 0.0) {
            return false;
        }
        
        return (mt_rand() / mt_getrandmax()) <= $rate;
    }
    
    /**
     * Generate 32-character trace ID
     */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Generate 16-character span ID
     */
    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
    
    /**
     * Get current microtime as float
     */
    private function microtime(): float
    {
        return microtime(true);
    }
    
    /**
     * Convert hex string to long
     */
    private function hexToLong(string $hex): int
    {
        return (int) hexdec($hex);
    }
    
    /**
     * HTTP POST request
     */
    private function httpPost(string $url, array $data, array $headers = []): void
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1, // 1 second timeout (fire and forget)
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Get all spans (for debugging)
     */
    public function getSpans(): array
    {
        return $this->spans;
    }
    
    /**
     * Check if tracing is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
