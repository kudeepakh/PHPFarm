<?php

namespace Farm\Backend\App\Core\Testing;

use Farm\Backend\App\Core\Documentation\OpenApiGenerator;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

/**
 * Contract Tester
 * 
 * Validates API responses against OpenAPI specifications.
 * Ensures API contract compliance automatically.
 * 
 * Usage:
 * ```php
 * $tester = new ContractTester('/docs/openapi.json');
 * $tester->validateResponse('POST', '/api/v1/users', 201, $response);
 * ```
 */
class ContractTester
{
    private array $spec;
    private SchemaValidator $validator;

    /**
     * Constructor
     * 
     * @param string $specPath Path to OpenAPI spec file
     */
    public function __construct(string $specPath = null)
    {
        if ($specPath === null) {
            // Generate spec on-the-fly
            $configPath = __DIR__ . '/../../../config/documentation.php';
            $config = file_exists($configPath) ? require $configPath : [];

            try {
                $generator = new OpenApiGenerator($config);
                $this->spec = $generator->generate();
            } catch (\Throwable $e) {
                $this->spec = [];
            }

            if (empty($this->spec['paths'] ?? [])) {
                $this->spec = $this->fallbackSpec();
            }
        } else {
            $this->spec = $this->loadSpec($specPath);
        }
        
        $this->validator = new SchemaValidator();
    }

    private function fallbackSpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'PHPFrarm API',
                'version' => '1.0.0'
            ],
            'paths' => [
                '/api/v1/auth/register' => [
                    'post' => [
                        'responses' => [
                            '201' => [
                                'description' => 'Created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->successSchema(['user_id', 'email'])
                                    ]
                                ]
                            ],
                            '400' => [
                                'description' => 'Bad Request',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->errorSchema()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/v1/auth/login' => [
                    'post' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->successSchema(['token', 'refresh_token'])
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => 'Unauthorized',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->errorSchema()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/v1/users/me' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->successSchema(['id', 'email'])
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => 'Unauthorized',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->errorSchema()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/v1/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->successSchema([])
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => 'Unauthorized',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->errorSchema()
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/v1/health' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->successSchema([])
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => []
            ]
        ];
    }

    private function successSchema(array $requiredDataKeys): array
    {
        $dataProperties = [];
        foreach ($requiredDataKeys as $key) {
            $dataProperties[$key] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'required' => ['success', 'data', 'meta'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => [
                    'type' => 'object',
                    'properties' => $dataProperties
                ],
                'meta' => [
                    'type' => 'object'
                ]
            ]
        ];
    }

    private function errorSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'error', 'meta'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'error' => [
                    'type' => 'object',
                    'required' => ['code', 'message'],
                    'properties' => [
                        'code' => ['type' => 'string'],
                        'message' => ['type' => 'string']
                    ]
                ],
                'meta' => [
                    'type' => 'object'
                ]
            ]
        ];
    }

    /**
     * Load OpenAPI specification
     * 
     * @param string $path
     * @return array
     */
    private function loadSpec(string $path): array
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Spec file not found: $path");
        }
        
        $content = file_get_contents($path);
        $spec = json_decode($content, true);
        
        if ($spec === null) {
            throw new \InvalidArgumentException("Invalid JSON in spec file");
        }
        
        return $spec;
    }

    /**
     * Validate API response against spec
     * 
     * @param string $method HTTP method
     * @param string $path API path
     * @param int $statusCode Response status code
     * @param mixed $responseBody Response body
     * @return ValidationResult
     */
    public function validateResponse(
        string $method,
        string $path,
        int $statusCode,
        $responseBody
    ): ValidationResult {
        $method = strtolower($method);
        
        // Find path in spec
        $pathItem = $this->findPath($path);
        if ($pathItem === null) {
            return ValidationResult::fail("Path not found in spec: $path");
        }
        
        // Find operation
        if (!isset($pathItem[$method])) {
            return ValidationResult::fail("Method $method not defined for path $path");
        }
        
        $operation = $pathItem[$method];
        
        // Find response definition
        if (!isset($operation['responses'][$statusCode])) {
            // Try default response
            if (isset($operation['responses']['default'])) {
                $responseSpec = $operation['responses']['default'];
            } else {
                return ValidationResult::fail("Status code $statusCode not defined in spec");
            }
        } else {
            $responseSpec = $operation['responses'][$statusCode];
        }
        
        // Validate response body
        if (isset($responseSpec['content']['application/json']['schema'])) {
            $schema = $responseSpec['content']['application/json']['schema'];
            return $this->validator->validate($responseBody, $schema, $this->spec);
        }
        
        // No schema defined, response is valid
        return ValidationResult::pass();
    }

    /**
     * Validate request against spec
     * 
     * @param string $method HTTP method
     * @param string $path API path
     * @param array $params Query/path parameters
     * @param mixed $body Request body
     * @return ValidationResult
     */
    public function validateRequest(
        string $method,
        string $path,
        array $params = [],
        $body = null
    ): ValidationResult {
        $method = strtolower($method);
        
        // Find path in spec
        $pathItem = $this->findPath($path);
        if ($pathItem === null) {
            return ValidationResult::fail("Path not found in spec: $path");
        }
        
        // Find operation
        if (!isset($pathItem[$method])) {
            return ValidationResult::fail("Method $method not defined for path $path");
        }
        
        $operation = $pathItem[$method];
        
        // Validate parameters
        if (isset($operation['parameters'])) {
            foreach ($operation['parameters'] as $paramSpec) {
                $paramName = $paramSpec['name'];
                $required = $paramSpec['required'] ?? false;
                
                if ($required && !isset($params[$paramName])) {
                    return ValidationResult::fail("Required parameter missing: $paramName");
                }
                
                // Validate parameter schema
                if (isset($params[$paramName]) && isset($paramSpec['schema'])) {
                    $result = $this->validator->validate(
                        $params[$paramName],
                        $paramSpec['schema'],
                        $this->spec
                    );
                    
                    if (!$result->isValid()) {
                        return $result;
                    }
                }
            }
        }
        
        // Validate request body
        if ($body !== null && isset($operation['requestBody'])) {
            $required = $operation['requestBody']['required'] ?? false;
            
            if ($required && empty($body)) {
                return ValidationResult::fail("Request body is required");
            }
            
            if (isset($operation['requestBody']['content']['application/json']['schema'])) {
                $schema = $operation['requestBody']['content']['application/json']['schema'];
                return $this->validator->validate($body, $schema, $this->spec);
            }
        }
        
        return ValidationResult::pass();
    }

    /**
     * Find path in spec (handles path parameters)
     * 
     * @param string $path
     * @return array|null
     */
    private function findPath(string $path): ?array
    {
        // Try exact match first
        if (isset($this->spec['paths'][$path])) {
            return $this->spec['paths'][$path];
        }
        
        // Try pattern matching for path parameters
        foreach ($this->spec['paths'] as $specPath => $pathItem) {
            if ($this->matchPath($path, $specPath)) {
                return $pathItem;
            }
        }
        
        return null;
    }

    /**
     * Match actual path against spec path pattern
     * 
     * @param string $actualPath
     * @param string $specPath
     * @return bool
     */
    private function matchPath(string $actualPath, string $specPath): bool
    {
        // Convert {param} to regex pattern
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $specPath);
        $pattern = '#^' . $pattern . '$#';
        
        return (bool)preg_match($pattern, $actualPath);
    }

    /**
     * Get all endpoints from spec
     * 
     * @return array
     */
    public function getEndpoints(): array
    {
        $endpoints = [];
        
        foreach ($this->spec['paths'] as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($pathItem[$method])) {
                    $endpoints[] = [
                        'method' => strtoupper($method),
                        'path' => $path,
                        'summary' => $pathItem[$method]['summary'] ?? '',
                        'tags' => $pathItem[$method]['tags'] ?? []
                    ];
                }
            }
        }
        
        return $endpoints;
    }
}

/**
 * Validation Result
 */
class ValidationResult
{
    private bool $valid;
    private array $errors;

    private function __construct(bool $valid, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $error): self
    {
        return new self(false, [$error]);
    }

    public static function failMultiple(array $errors): self
    {
        return new self(false, $errors);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
}
