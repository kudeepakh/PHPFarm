<?php

namespace Farm\Backend\App\Core\Documentation;

use Farm\Backend\App\Core\Documentation\Attributes\ApiDoc;
use Farm\Backend\App\Core\Documentation\Attributes\ApiParam;
use Farm\Backend\App\Core\Documentation\Attributes\ApiResponse;
use Farm\Backend\App\Core\Documentation\Attributes\ApiExample;
use ReflectionClass;
use ReflectionMethod;

/**
 * OpenAPI 3.0 Generator
 * 
 * Scans controller classes and generates OpenAPI 3.0 specification
 * from PHP attributes (#[ApiDoc], #[ApiParam], #[ApiResponse]).
 * 
 * Usage:
 * ```php
 * $generator = new OpenApiGenerator($config);
 * $spec = $generator->generate();
 * file_put_contents('openapi.json', json_encode($spec, JSON_PRETTY_PRINT));
 * ```
 */
class OpenApiGenerator
{
    private array $config;
    private array $routes = [];
    private SchemaExtractor $schemaExtractor;

    /**
     * @param array $config Documentation configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->schemaExtractor = new SchemaExtractor();
    }

    /**
     * Generate OpenAPI 3.0 specification
     * 
     * @return array OpenAPI spec
     */
    public function generate(): array
    {
        $spec = $this->getBaseSpec();
        
        // Scan controllers
        $this->scanControllers();
        
        // Build paths
        $spec['paths'] = $this->buildPaths();
        
        // Extract schemas from DTOs
        $spec['components']['schemas'] = $this->schemaExtractor->getSchemas();
        
        // Add security schemes
        $spec['components']['securitySchemes'] = $this->getSecuritySchemes();
        
        return $spec;
    }

    /**
     * Get base OpenAPI specification
     * 
     * @return array
     */
    private function getBaseSpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->config['title'] ?? 'API Documentation',
                'description' => $this->config['description'] ?? 'Auto-generated API documentation',
                'version' => $this->config['version'] ?? '1.0.0',
                'contact' => $this->config['contact'] ?? [],
                'license' => $this->config['license'] ?? [],
            ],
            'servers' => $this->getServers(),
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [],
            ],
            'tags' => $this->getTags(),
        ];
    }

    /**
     * Get server configurations
     * 
     * @return array
     */
    private function getServers(): array
    {
        $servers = [];
        
        foreach ($this->config['servers'] ?? [] as $server) {
            $servers[] = [
                'url' => $server['url'],
                'description' => $server['description'] ?? '',
            ];
        }
        
        return $servers;
    }

    /**
     * Get tag definitions
     * 
     * @return array
     */
    private function getTags(): array
    {
        $tags = [];
        
        foreach ($this->config['tags'] ?? [] as $name => $description) {
            $tags[] = [
                'name' => $name,
                'description' => $description,
            ];
        }
        
        return $tags;
    }

    /**
     * Scan all controllers for API documentation
     * 
     * @return void
     */
    private function scanControllers(): void
    {
        $controllerPaths = $this->config['controller_paths'] ?? [];
        
        foreach ($controllerPaths as $path) {
            $this->scanDirectory($path);
        }
    }

    /**
     * Recursively scan directory for controllers
     * 
     * @param string $directory
     * @return void
     */
    private function scanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = scandir($directory);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($filePath)) {
                $this->scanDirectory($filePath);
            } elseif (str_ends_with($file, 'Controller.php')) {
                $this->scanController($filePath);
            }
        }
    }

    /**
     * Scan single controller file
     * 
     * @param string $filePath
     * @return void
     */
    private function scanController(string $filePath): void
    {
        // Extract namespace and class name
        $content = file_get_contents($filePath);
        
        if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            return;
        }
        
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return;
        }
        
        $namespace = $nsMatch[1];
        $className = $classMatch[1];
        $fullClassName = $namespace . '\\' . $className;
        
        // Check if class exists
        if (!class_exists($fullClassName)) {
            return;
        }
        
        $this->scanControllerClass($fullClassName);
    }

    /**
     * Scan controller class methods
     * 
     * @param string $className
     * @return void
     */
    private function scanControllerClass(string $className): void
    {
        $reflection = new ReflectionClass($className);
        
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip constructor and magic methods
            if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }
            
            // Check for ApiDoc attribute
            $attributes = $method->getAttributes(ApiDoc::class);
            
            if (empty($attributes)) {
                continue;
            }
            
            $this->processMethod($method);
        }
    }

    /**
     * Process controller method and extract documentation
     * 
     * @param ReflectionMethod $method
     * @return void
     */
    private function processMethod(ReflectionMethod $method): void
    {
        // Get route from method name or Route attribute
        $route = $this->extractRoute($method);
        
        if ($route === null) {
            return;
        }
        
        // Extract ApiDoc
        $apiDocAttr = $method->getAttributes(ApiDoc::class)[0] ?? null;
        if ($apiDocAttr === null) {
            return;
        }
        
        $apiDoc = $apiDocAttr->newInstance();
        
        // Extract parameters
        $parameters = [];
        foreach ($method->getAttributes(ApiParam::class) as $attr) {
            $param = $attr->newInstance();
            $parameters[] = $param->toOpenApi();
            
            // Register schema if referenced
            if ($param->schema !== null) {
                $this->schemaExtractor->extractFromClass($param->schema);
            }
        }
        
        // Extract responses
        $responses = [];
        foreach ($method->getAttributes(ApiResponse::class) as $attr) {
            $response = $attr->newInstance();
            $responses[$response->status] = $response->toOpenApi();
            
            // Register schema if referenced
            if ($response->schema !== null) {
                $this->schemaExtractor->extractFromClass($response->schema);
            }
        }
        
        // Extract examples
        $examples = [];
        foreach ($method->getAttributes(ApiExample::class) as $attr) {
            $example = $attr->newInstance();
            $examples[$example->name] = $example;
        }
        
        // Build operation
        $operation = $apiDoc->toOpenApi();
        
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }
        
        if (!empty($responses)) {
            $operation['responses'] = $responses;
        } else {
            // Default response
            $operation['responses'] = [
                '200' => ['description' => 'Successful response'],
            ];
        }
        
        // Add examples to request body
        if (!empty($examples)) {
            $this->addExamplesToOperation($operation, $examples);
        }
        
        // Store route
        $this->routes[] = [
            'path' => $route['path'],
            'method' => $route['method'],
            'operation' => $operation,
        ];
    }

    /**
     * Extract route from method
     * 
     * @param ReflectionMethod $method
     * @return array|null ['path' => string, 'method' => string]
     */
    private function extractRoute(ReflectionMethod $method): ?array
    {
        // Try to extract from Route attribute (if available)
        $routeAttrs = $method->getAttributes();
        
        foreach ($routeAttrs as $attr) {
            $attrName = $attr->getName();
            
            if (str_contains($attrName, 'Route')) {
                $instance = $attr->newInstance();
                
                // Try common Route attribute patterns
                if (isset($instance->path) && isset($instance->method)) {
                    return [
                        'path' => $instance->path,
                        'method' => strtolower($instance->method),
                    ];
                }
            }
        }
        
        // Fallback: Generate from method name
        $methodName = $method->getName();
        
        // Extract HTTP method from method name
        $httpMethod = 'get';
        if (preg_match('/^(get|post|put|patch|delete|options|head)/', $methodName, $matches)) {
            $httpMethod = strtolower($matches[1]);
        }
        
        // Generate path from class and method name
        $className = $method->getDeclaringClass()->getShortName();
        $resourceName = strtolower(str_replace('Controller', '', $className));
        
        return [
            'path' => '/' . $resourceName . '/{id}',
            'method' => $httpMethod,
        ];
    }

    /**
     * Add examples to operation
     * 
     * @param array &$operation
     * @param array $examples ApiExample instances
     * @return void
     */
    private function addExamplesToOperation(array &$operation, array $examples): void
    {
        $requestExamples = [];
        $responseExamples = [];
        
        foreach ($examples as $name => $example) {
            if ($example->hasRequest()) {
                $requestExamples[$name] = $example->toOpenApiRequestExample();
            }
            
            if ($example->hasResponse()) {
                $status = $example->responseStatus;
                
                if (!isset($responseExamples[$status])) {
                    $responseExamples[$status] = [];
                }
                
                $responseExamples[$status][$name] = $example->toOpenApiResponseExample();
            }
        }
        
        // Add request examples to requestBody
        if (!empty($requestExamples)) {
            if (!isset($operation['requestBody'])) {
                $operation['requestBody'] = [
                    'content' => [
                        'application/json' => [],
                    ],
                ];
            }
            
            $operation['requestBody']['content']['application/json']['examples'] = $requestExamples;
        }
        
        // Add response examples
        foreach ($responseExamples as $status => $examples) {
            if (isset($operation['responses'][$status])) {
                if (!isset($operation['responses'][$status]['content'])) {
                    $operation['responses'][$status]['content'] = [
                        'application/json' => [],
                    ];
                }
                
                $operation['responses'][$status]['content']['application/json']['examples'] = $examples;
            }
        }
    }

    /**
     * Build OpenAPI paths from collected routes
     * 
     * @return array
     */
    private function buildPaths(): array
    {
        $paths = [];
        
        foreach ($this->routes as $route) {
            $path = $route['path'];
            $method = $route['method'];
            $operation = $route['operation'];
            
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            
            $paths[$path][$method] = $operation;
        }
        
        return $paths;
    }

    /**
     * Get security schemes
     * 
     * @return array
     */
    private function getSecuritySchemes(): array
    {
        return [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
            'apiKey' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
            ],
        ];
    }
}
