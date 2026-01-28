<?php

namespace Farm\Backend\App\Core\Documentation;

/**
 * Postman Exporter
 * 
 * Converts OpenAPI 3.0 specification to Postman Collection v2.1 format.
 * Generates importable .json file for Postman with all endpoints,
 * examples, authentication, and environment variables.
 * 
 * Usage:
 * ```php
 * $exporter = new PostmanExporter($config);
 * $collection = $exporter->export($openApiSpec);
 * file_put_contents('postman_collection.json', json_encode($collection, JSON_PRETTY_PRINT));
 * ```
 */
class PostmanExporter
{
    private array $config;

    /**
     * @param array $config Documentation configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Export OpenAPI spec to Postman Collection v2.1
     * 
     * @param array $openApiSpec OpenAPI 3.0 specification
     * @return array Postman collection
     */
    public function export(array $openApiSpec): array
    {
        $collection = [
            'info' => $this->buildInfo($openApiSpec),
            'item' => $this->buildItems($openApiSpec),
            'auth' => $this->buildAuth($openApiSpec),
            'variable' => $this->buildVariables($openApiSpec),
        ];
        
        return $collection;
    }

    /**
     * Build collection info
     * 
     * @param array $openApiSpec
     * @return array
     */
    private function buildInfo(array $openApiSpec): array
    {
        $info = $openApiSpec['info'] ?? [];
        
        return [
            'name' => $info['title'] ?? 'API Collection',
            'description' => $info['description'] ?? '',
            'version' => $info['version'] ?? '1.0.0',
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ];
    }

    /**
     * Build collection items (folders and requests)
     * 
     * @param array $openApiSpec
     * @return array
     */
    private function buildItems(array $openApiSpec): array
    {
        $paths = $openApiSpec['paths'] ?? [];
        $items = [];
        
        // Group by tags
        $byTag = [];
        
        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $tags = $operation['tags'] ?? ['Uncategorized'];
                $tag = $tags[0]; // Use first tag
                
                if (!isset($byTag[$tag])) {
                    $byTag[$tag] = [];
                }
                
                $byTag[$tag][] = [
                    'path' => $path,
                    'method' => $method,
                    'operation' => $operation,
                ];
            }
        }
        
        // Build folders
        foreach ($byTag as $tag => $operations) {
            $folder = [
                'name' => $tag,
                'item' => [],
            ];
            
            foreach ($operations as $op) {
                $folder['item'][] = $this->buildRequest(
                    $op['path'],
                    $op['method'],
                    $op['operation'],
                    $openApiSpec
                );
            }
            
            $items[] = $folder;
        }
        
        return $items;
    }

    /**
     * Build single request
     * 
     * @param string $path
     * @param string $method
     * @param array $operation
     * @param array $openApiSpec
     * @return array
     */
    private function buildRequest(string $path, string $method, array $operation, array $openApiSpec): array
    {
        $request = [
            'name' => $operation['summary'] ?? $path,
            'request' => [
                'method' => strtoupper($method),
                'url' => $this->buildUrl($path, $operation),
                'header' => $this->buildHeaders($operation),
                'description' => $operation['description'] ?? '',
            ],
            'response' => $this->buildExampleResponses($operation),
        ];
        
        // Add request body
        $body = $this->buildBody($operation);
        if ($body !== null) {
            $request['request']['body'] = $body;
        }
        
        // Add auth
        $auth = $this->buildRequestAuth($operation);
        if ($auth !== null) {
            $request['request']['auth'] = $auth;
        }
        
        return $request;
    }

    /**
     * Build request URL
     * 
     * @param string $path
     * @param array $operation
     * @return array
     */
    private function buildUrl(string $path, array $operation): array
    {
        // Replace path parameters with Postman variables
        $pathWithVars = preg_replace('/\{([^}]+)\}/', ':$1', $path);
        
        // Parse URL
        $url = [
            'raw' => '{{baseUrl}}' . $pathWithVars,
            'host' => ['{{baseUrl}}'],
            'path' => array_filter(explode('/', $pathWithVars)),
        ];
        
        // Add query parameters
        $queryParams = [];
        foreach ($operation['parameters'] ?? [] as $param) {
            if ($param['in'] === 'query') {
                $queryParams[] = [
                    'key' => $param['name'],
                    'value' => $param['schema']['example'] ?? '',
                    'description' => $param['description'] ?? '',
                    'disabled' => !($param['required'] ?? false),
                ];
            }
        }
        
        if (!empty($queryParams)) {
            $url['query'] = $queryParams;
        }
        
        // Add path variables
        $pathVars = [];
        foreach ($operation['parameters'] ?? [] as $param) {
            if ($param['in'] === 'path') {
                $pathVars[] = [
                    'key' => $param['name'],
                    'value' => $param['schema']['example'] ?? '',
                    'description' => $param['description'] ?? '',
                ];
            }
        }
        
        if (!empty($pathVars)) {
            $url['variable'] = $pathVars;
        }
        
        return $url;
    }

    /**
     * Build request headers
     * 
     * @param array $operation
     * @return array
     */
    private function buildHeaders(array $operation): array
    {
        $headers = [];
        
        // Add header parameters
        foreach ($operation['parameters'] ?? [] as $param) {
            if ($param['in'] === 'header') {
                $headers[] = [
                    'key' => $param['name'],
                    'value' => $param['schema']['example'] ?? '',
                    'description' => $param['description'] ?? '',
                    'disabled' => !($param['required'] ?? false),
                ];
            }
        }
        
        // Add default headers
        $headers[] = [
            'key' => 'X-Correlation-Id',
            'value' => '{{$guid}}',
            'description' => 'Correlation ID for request tracing',
        ];
        
        $headers[] = [
            'key' => 'X-Transaction-Id',
            'value' => '{{$guid}}',
            'description' => 'Transaction ID for request tracing',
        ];
        
        return $headers;
    }

    /**
     * Build request body
     * 
     * @param array $operation
     * @return array|null
     */
    private function buildBody(array $operation): ?array
    {
        $requestBody = $operation['requestBody'] ?? null;
        
        if ($requestBody === null) {
            return null;
        }
        
        $content = $requestBody['content'] ?? [];
        
        // Support JSON only for now
        if (isset($content['application/json'])) {
            $jsonContent = $content['application/json'];
            
            // Get example
            $example = null;
            
            if (isset($jsonContent['examples'])) {
                // Use first example
                $examples = $jsonContent['examples'];
                $firstExample = reset($examples);
                $example = $firstExample['value'] ?? null;
            } elseif (isset($jsonContent['example'])) {
                $example = $jsonContent['example'];
            }
            
            return [
                'mode' => 'raw',
                'raw' => json_encode($example, JSON_PRETTY_PRINT),
                'options' => [
                    'raw' => [
                        'language' => 'json',
                    ],
                ],
            ];
        }
        
        return null;
    }

    /**
     * Build request auth
     * 
     * @param array $operation
     * @return array|null
     */
    private function buildRequestAuth(array $operation): ?array
    {
        $security = $operation['security'] ?? [];
        
        if (empty($security)) {
            return null;
        }
        
        // Get first security scheme
        $firstScheme = array_keys($security[0])[0] ?? null;
        
        if ($firstScheme === null) {
            return null;
        }
        
        return match ($firstScheme) {
            'bearerAuth' => [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{accessToken}}', 'type' => 'string'],
                ],
            ],
            'apiKey' => [
                'type' => 'apikey',
                'apikey' => [
                    ['key' => 'key', 'value' => 'X-API-Key', 'type' => 'string'],
                    ['key' => 'value', 'value' => '{{apiKey}}', 'type' => 'string'],
                    ['key' => 'in', 'value' => 'header', 'type' => 'string'],
                ],
            ],
            default => null,
        };
    }

    /**
     * Build example responses
     * 
     * @param array $operation
     * @return array
     */
    private function buildExampleResponses(array $operation): array
    {
        $responses = [];
        
        foreach ($operation['responses'] ?? [] as $status => $response) {
            $content = $response['content']['application/json'] ?? null;
            
            if ($content === null) {
                continue;
            }
            
            // Get example
            $example = null;
            
            if (isset($content['examples'])) {
                // Create response for each example
                foreach ($content['examples'] as $exampleName => $exampleData) {
                    $responses[] = [
                        'name' => $exampleName,
                        'originalRequest' => [
                            'method' => 'GET',
                            'url' => '{{baseUrl}}/example',
                        ],
                        'status' => $this->getStatusText((int)$status),
                        'code' => (int)$status,
                        '_postman_previewlanguage' => 'json',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                        ],
                        'body' => json_encode($exampleData['value'] ?? [], JSON_PRETTY_PRINT),
                    ];
                }
            } elseif (isset($content['example'])) {
                $example = $content['example'];
                
                $responses[] = [
                    'name' => $status . ' response',
                    'originalRequest' => [
                        'method' => 'GET',
                        'url' => '{{baseUrl}}/example',
                    ],
                    'status' => $this->getStatusText((int)$status),
                    'code' => (int)$status,
                    '_postman_previewlanguage' => 'json',
                    'header' => [
                        ['key' => 'Content-Type', 'value' => 'application/json'],
                    ],
                    'body' => json_encode($example, JSON_PRETTY_PRINT),
                ];
            }
        }
        
        return $responses;
    }

    /**
     * Build collection auth
     * 
     * @param array $openApiSpec
     * @return array|null
     */
    private function buildAuth(array $openApiSpec): ?array
    {
        $securitySchemes = $openApiSpec['components']['securitySchemes'] ?? [];
        
        if (isset($securitySchemes['bearerAuth'])) {
            return [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{accessToken}}', 'type' => 'string'],
                ],
            ];
        }
        
        return null;
    }

    /**
     * Build collection variables
     * 
     * @param array $openApiSpec
     * @return array
     */
    private function buildVariables(array $openApiSpec): array
    {
        $servers = $openApiSpec['servers'] ?? [];
        $baseUrl = $servers[0]['url'] ?? 'http://localhost:8000';
        
        return [
            [
                'key' => 'baseUrl',
                'value' => $baseUrl,
                'type' => 'string',
            ],
            [
                'key' => 'accessToken',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'apiKey',
                'value' => '',
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get HTTP status text
     * 
     * @param int $code
     * @return string
     */
    private function getStatusText(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Response',
        };
    }
}
