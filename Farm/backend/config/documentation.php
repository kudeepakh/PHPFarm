<?php

/**
 * Documentation Configuration
 * 
 * Configuration for API documentation generation including:
 * - OpenAPI specification settings
 * - Controller and exception scanning paths
 * - Swagger UI configuration
 * - Output directories
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Title
    |--------------------------------------------------------------------------
    |
    | The title that appears in the OpenAPI spec and Swagger UI.
    |
    */
    'title' => env('DOCS_TITLE', 'PHPFrarm API'),

    /*
    |--------------------------------------------------------------------------
    | Documentation Description
    |--------------------------------------------------------------------------
    |
    | A brief description of your API that appears in the documentation.
    |
    */
    'description' => env('DOCS_DESCRIPTION', 'Enterprise-grade API development framework with built-in security, observability, and governance.'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The version of your API (semantic versioning recommended).
    |
    */
    'version' => env('API_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Contact Information
    |--------------------------------------------------------------------------
    |
    | Contact information for the API maintainers.
    |
    */
    'contact' => [
        'name' => env('DOCS_CONTACT_NAME', 'API Team'),
        'email' => env('DOCS_CONTACT_EMAIL', 'api@example.com'),
        'url' => env('DOCS_CONTACT_URL', 'https://example.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | License Information
    |--------------------------------------------------------------------------
    |
    | License information for the API.
    |
    */
    'license' => [
        'name' => env('DOCS_LICENSE_NAME', 'MIT'),
        'url' => env('DOCS_LICENSE_URL', 'https://opensource.org/licenses/MIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | API server URLs for different environments.
    |
    */
    'servers' => [
        [
            'url' => env('DOCS_SERVER_DEV_URL', 'http://localhost:8000/api/v1'),
            'description' => 'Development server',
        ],
        [
            'url' => env('DOCS_SERVER_STAGING_URL', 'https://staging-api.example.com/api/v1'),
            'description' => 'Staging server',
        ],
        [
            'url' => env('DOCS_SERVER_PROD_URL', 'https://api.example.com/api/v1'),
            'description' => 'Production server',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    |
    | Tag definitions for grouping endpoints in the documentation.
    |
    */
    'tags' => [
        'Authentication' => 'User authentication and token management',
        'Users' => 'User management operations',
        'Admin' => 'Administrative operations',
        'Public' => 'Publicly accessible endpoints',
        'Internal' => 'Internal API endpoints',
    ],

    /*
    |--------------------------------------------------------------------------
    | Controller Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for controller classes with API documentation.
    |
    */
    'controller_paths' => [
        __DIR__ . '/../app/Controllers',
        __DIR__ . '/../app/Controllers/Admin',
        __DIR__ . '/../app/Controllers/Auth',
        __DIR__ . '/../modules',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for exception classes for error catalog generation.
    |
    */
    'exception_paths' => [
        __DIR__ . '/../app/Exceptions',
        __DIR__ . '/../app/Core/Exceptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | Directory where generated documentation files will be saved.
    |
    */
    'output_dir' => env('DOCS_OUTPUT_DIR', __DIR__ . '/../storage/docs'),

    /*
    |--------------------------------------------------------------------------
    | Swagger UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Swagger UI display.
    |
    */
    'swagger' => [
        'enabled' => env('DOCS_SWAGGER_ENABLED', true),
        'route' => env('DOCS_SWAGGER_ROUTE', '/docs'),
        'version' => '5.10.5',
        'persist_authorization' => true,
        'doc_expansion' => 'list', // 'none', 'list', 'full'
        'default_models_expand_depth' => 1,
        'default_model_expand_depth' => 1,
        'show_extensions' => true,
        'show_common_extensions' => true,
        'filter' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Generation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for automatic documentation generation.
    |
    */
    'auto_generate' => [
        'enabled' => env('DOCS_AUTO_GENERATE', false),
        'on_deploy' => true,
        'schedule' => 'daily', // 'hourly', 'daily', 'weekly'
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Schemes
    |--------------------------------------------------------------------------
    |
    | Security schemes available in your API.
    |
    */
    'security_schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'JWT access token obtained from /auth/login',
        ],
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
            'description' => 'API key for service-to-service authentication',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Examples
    |--------------------------------------------------------------------------
    |
    | Default response examples for common scenarios.
    |
    */
    'default_responses' => [
        '400' => [
            'description' => 'Bad request - validation failed',
            'example' => [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Request validation failed',
                    'details' => [
                        'email' => ['The email field is required'],
                    ],
                ],
                'meta' => [
                    'correlation_id' => '01HQZK1234567890',
                    'timestamp' => '2026-01-18T10:30:00Z',
                ],
            ],
        ],
        '401' => [
            'description' => 'Unauthorized - invalid or missing authentication',
            'example' => [
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required',
                ],
                'meta' => [
                    'correlation_id' => '01HQZK1234567890',
                    'timestamp' => '2026-01-18T10:30:00Z',
                ],
            ],
        ],
        '403' => [
            'description' => 'Forbidden - insufficient permissions',
            'example' => [
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this resource',
                ],
                'meta' => [
                    'correlation_id' => '01HQZK1234567890',
                    'timestamp' => '2026-01-18T10:30:00Z',
                ],
            ],
        ],
        '404' => [
            'description' => 'Not found - resource does not exist',
            'example' => [
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'The requested resource was not found',
                ],
                'meta' => [
                    'correlation_id' => '01HQZK1234567890',
                    'timestamp' => '2026-01-18T10:30:00Z',
                ],
            ],
        ],
        '429' => [
            'description' => 'Too many requests - rate limit exceeded',
            'example' => [
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                ],
                'meta' => [
                    'correlation_id' => '01HQZK1234567890',
                    'timestamp' => '2026-01-18T10:30:00Z',
                    'retry_after' => 60,
                ],
            ],
        ],
        '500' => [
            'description' => 'Internal server error',
            'example' => [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred',
                ],
                'meta' => [
                    'correlation_id' => '01HQZK1234567890',
                    'timestamp' => '2026-01-18T10:30:00Z',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Postman Export Settings
    |--------------------------------------------------------------------------
    |
    | Settings for Postman collection export.
    |
    */
    'postman' => [
        'collection_name' => env('POSTMAN_COLLECTION_NAME', 'PHPFrarm API'),
        'schema_version' => '2.1.0',
        'include_examples' => true,
        'include_tests' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Catalog Settings
    |--------------------------------------------------------------------------
    |
    | Settings for error catalog generation.
    |
    */
    'error_catalog' => [
        'enabled' => true,
        'output_file' => 'ERROR_CATALOG.md',
        'group_by' => 'category', // 'category', 'http_status', 'none'
        'include_examples' => true,
    ],
];
