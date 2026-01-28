<?php

/**
 * Distributed Tracing Configuration
 * 
 * Configure Jaeger, Zipkin, or OpenTelemetry for distributed tracing.
 * 
 * @package PHPFrarm
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Tracing
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable distributed tracing.
    | When disabled, no spans are created or sent.
    |
    */
    'enabled' => (bool) ($_ENV['TRACING_ENABLED'] ?? false),

    /*
    |--------------------------------------------------------------------------
    | Tracing Backend
    |--------------------------------------------------------------------------
    |
    | Supported backends: jaeger, zipkin, otlp (OpenTelemetry), none
    |
    */
    'backend' => $_ENV['TRACING_BACKEND'] ?? 'jaeger',

    /*
    |--------------------------------------------------------------------------
    | Service Name
    |--------------------------------------------------------------------------
    |
    | The name of this service as it appears in traces.
    |
    */
    'service_name' => $_ENV['SERVICE_NAME'] ?? 'phpfrarm-api',

    /*
    |--------------------------------------------------------------------------
    | Jaeger Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Jaeger tracing backend.
    | Supports both HTTP collector and UDP agent.
    |
    */
    'jaeger' => [
        // HTTP collector endpoint
        'endpoint' => $_ENV['JAEGER_ENDPOINT'] ?? 'http://jaeger:14268/api/traces',
        
        // UDP agent (alternative to HTTP)
        'agent_host' => $_ENV['JAEGER_AGENT_HOST'] ?? 'jaeger',
        'agent_port' => (int) ($_ENV['JAEGER_AGENT_PORT'] ?? 6831),
        
        // Use agent instead of HTTP collector
        'use_agent' => (bool) ($_ENV['JAEGER_USE_AGENT'] ?? false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zipkin Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Zipkin tracing backend.
    |
    */
    'zipkin' => [
        'endpoint' => $_ENV['ZIPKIN_ENDPOINT'] ?? 'http://zipkin:9411/api/v2/spans',
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry (OTLP) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenTelemetry Collector.
    | Compatible with any OTLP-compliant backend.
    |
    */
    'otlp' => [
        // HTTP endpoint (default)
        'endpoint' => $_ENV['OTLP_ENDPOINT'] ?? 'http://otel-collector:4318/v1/traces',
        
        // gRPC endpoint (alternative)
        'grpc_endpoint' => $_ENV['OTLP_GRPC_ENDPOINT'] ?? 'otel-collector:4317',
        
        // Protocol: http or grpc
        'protocol' => $_ENV['OTLP_PROTOCOL'] ?? 'http',
        
        // Headers for authentication
        'headers' => [
            // 'Authorization' => 'Bearer ' . ($_ENV['OTLP_AUTH_TOKEN'] ?? ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Configuration
    |--------------------------------------------------------------------------
    |
    | Control how traces are sampled to reduce volume.
    |
    */
    'sampling' => [
        // Sample rate (0.0 to 1.0)
        // 1.0 = sample all requests
        // 0.1 = sample 10% of requests
        'rate' => (float) ($_ENV['TRACING_SAMPLE_RATE'] ?? 1.0),
        
        // Sampling type: probabilistic, rate-limiting, always-on, always-off
        'type' => $_ENV['TRACING_SAMPLE_TYPE'] ?? 'probabilistic',
        
        // For rate-limiting: max traces per second
        'max_traces_per_second' => (int) ($_ENV['TRACING_MAX_TRACES_PER_SECOND'] ?? 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Propagation Headers
    |--------------------------------------------------------------------------
    |
    | Which header formats to use for trace context propagation.
    |
    */
    'propagation' => [
        // W3C Trace Context (traceparent, tracestate)
        'w3c' => true,
        
        // B3 Multi Headers (X-B3-TraceId, X-B3-SpanId, etc.)
        'b3_multi' => true,
        
        // B3 Single Header (b3)
        'b3_single' => false,
        
        // Jaeger (uber-trace-id)
        'jaeger' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Span Configuration
    |--------------------------------------------------------------------------
    |
    | Default span settings.
    |
    */
    'spans' => [
        // Maximum number of spans per trace
        'max_spans' => (int) ($_ENV['TRACING_MAX_SPANS'] ?? 1000),
        
        // Maximum tag value length
        'max_tag_length' => (int) ($_ENV['TRACING_MAX_TAG_LENGTH'] ?? 1024),
        
        // Include stack traces in error spans
        'include_stack_traces' => (bool) ($_ENV['TRACING_INCLUDE_STACK_TRACES'] ?? false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Instrumentation
    |--------------------------------------------------------------------------
    |
    | Automatically create spans for common operations.
    |
    */
    'auto_instrument' => [
        // HTTP requests (incoming)
        'http_server' => true,
        
        // HTTP requests (outgoing via cURL)
        'http_client' => true,
        
        // Database queries
        'database' => true,
        
        // Redis operations
        'redis' => true,
        
        // MongoDB operations
        'mongodb' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Paths
    |--------------------------------------------------------------------------
    |
    | Paths to exclude from tracing (e.g., health checks).
    |
    */
    'ignore_paths' => [
        '/health',
        '/health/live',
        '/health/ready',
        '/metrics',
        '/favicon.ico',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Attributes
    |--------------------------------------------------------------------------
    |
    | Additional attributes to attach to all spans.
    |
    */
    'resource_attributes' => [
        'deployment.environment' => $_ENV['APP_ENV'] ?? 'development',
        'service.version' => $_ENV['APP_VERSION'] ?? '1.0.0',
        'host.name' => gethostname(),
    ],
];
