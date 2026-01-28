<?php

namespace PHPFrarm\Modules\System\Controllers;

use Farm\Backend\App\Core\Documentation\OpenApiGenerator;
use Farm\Backend\App\Core\Documentation\ErrorCatalogGenerator;
use Farm\Backend\App\Core\Documentation\PostmanExporter;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\TraceContext;

/**
 * Documentation Controller
 * 
 * Serves API documentation including:
 * - Swagger UI at /docs
 * - OpenAPI JSON spec at /docs/openapi.json
 * - Error catalog at /docs/errors
 * - Postman collection at /docs/postman
 * 
 * Usage:
 * ```
 * GET /docs              - Swagger UI interface
 * GET /docs/openapi.json - OpenAPI 3.0 specification
 * GET /docs/errors       - Error catalog (markdown)
 * GET /docs/postman      - Postman collection (JSON)
 * ```
 */
#[RouteGroup('/docs', middleware: ['cors'])]
class DocsController
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/documentation.php';
    }

    /**
     * Serve Swagger UI
     * 
     * GET /docs
     * 
     * @return void
     */
    #[Route('', method: 'GET', description: 'Swagger UI')]
    public function index(): void
    {
        $swaggerHtml = $this->getSwaggerHtml();

        TraceContext::setResponseHeaders();
        header('Content-Type: text/html');
        echo $swaggerHtml;
    }

    /**
     * Get OpenAPI specification
     * 
     * GET /docs/openapi.json
     * 
     * @return void
     */
    #[Route('/openapi.json', method: 'GET', description: 'OpenAPI spec')]
    public function openapi(): void
    {
        $generator = new OpenApiGenerator($this->config);
        $spec = $generator->generate();

        TraceContext::setResponseHeaders();
        header('Content-Type: application/json');
        echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get error catalog
     * 
     * GET /docs/errors
     * 
     * @return void
     */
    #[Route('/errors', method: 'GET', description: 'Error catalog')]
    public function errors(): void
    {
        $generator = new ErrorCatalogGenerator($this->config);
        $markdown = $generator->generate();

        TraceContext::setResponseHeaders();
        header('Content-Type: text/markdown');
        echo $markdown;
    }

    /**
     * Get Postman collection
     * 
     * GET /docs/postman
     * 
     * @return void
     */
    #[Route('/postman', method: 'GET', description: 'Postman collection')]
    public function postman(): void
    {
        // Generate OpenAPI spec
        $generator = new OpenApiGenerator($this->config);
        $openApiSpec = $generator->generate();
        
        // Export to Postman
        $exporter = new PostmanExporter($this->config);
        $collection = $exporter->export($openApiSpec);

        TraceContext::setResponseHeaders();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="postman_collection.json"');
        echo json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Health check for documentation
     * 
     * GET /docs/health
     * 
     * @return void
     */
    #[Route('/health', method: 'GET', description: 'Docs health')]
    public function health(): void
    {
        $status = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'endpoints' => [
                'swagger_ui' => '/docs',
                'openapi_spec' => '/docs/openapi.json',
                'error_catalog' => '/docs/errors',
                'postman_collection' => '/docs/postman',
            ],
        ];

        TraceContext::setResponseHeaders();
        header('Content-Type: application/json');
        echo json_encode($status, JSON_PRETTY_PRINT);
    }

    /**
     * Get Swagger UI HTML
     * 
     * @return string
     */
    private function getSwaggerHtml(): string
    {
        $title = $this->config['title'] ?? 'API Documentation';
        $swaggerUiVersion = '5.10.5';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@{$swaggerUiVersion}/swagger-ui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        .swagger-ui .topbar {
            background-color: #2c3e50;
        }
        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="https://unpkg.com/swagger-ui-dist@{$swaggerUiVersion}/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@{$swaggerUiVersion}/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '/docs/openapi.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 1,
                docExpansion: 'list',
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                persistAuthorization: true,
            });
            
            window.ui = ui;
        };
    </script>
</body>
</html>
HTML;
    }
}
