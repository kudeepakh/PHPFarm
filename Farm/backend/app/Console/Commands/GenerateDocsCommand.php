<?php

namespace Farm\Backend\App\Console\Commands;

use Farm\Backend\App\Core\Documentation\OpenApiGenerator;
use Farm\Backend\App\Core\Documentation\ErrorCatalogGenerator;
use Farm\Backend\App\Core\Documentation\PostmanExporter;

/**
 * Generate Documentation Command
 * 
 * CLI command to generate API documentation files:
 * - OpenAPI 3.0 specification (openapi.json)
 * - Error catalog (ERROR_CATALOG.md)
 * - Postman collection (postman_collection.json)
 * 
 * Usage:
 * ```bash
 * php artisan docs:generate
 * php artisan docs:generate --format=openapi
 * php artisan docs:generate --format=errors
 * php artisan docs:generate --format=postman
 * php artisan docs:generate --output=/custom/path
 * ```
 */
class GenerateDocsCommand
{
    private array $config;
    private string $outputDir;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../../config/documentation.php';
        $this->outputDir = $this->config['output_dir'] ?? __DIR__ . '/../../../storage/docs';
    }

    /**
     * Execute command
     * 
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args = []): int
    {
        echo "🔨 Generating API Documentation...\n\n";
        
        $format = $this->getArgument($args, 'format', 'all');
        $outputDir = $this->getArgument($args, 'output', $this->outputDir);
        
        // Create output directory
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
            echo "✅ Created output directory: {$outputDir}\n";
        }
        
        $generated = [];
        
        try {
            // Generate OpenAPI spec
            if ($format === 'all' || $format === 'openapi') {
                $openApiPath = $this->generateOpenApi($outputDir);
                $generated[] = $openApiPath;
                echo "✅ Generated OpenAPI spec: {$openApiPath}\n";
            }
            
            // Generate error catalog
            if ($format === 'all' || $format === 'errors') {
                $errorsPath = $this->generateErrors($outputDir);
                $generated[] = $errorsPath;
                echo "✅ Generated error catalog: {$errorsPath}\n";
            }
            
            // Generate Postman collection
            if ($format === 'all' || $format === 'postman') {
                $postmanPath = $this->generatePostman($outputDir);
                $generated[] = $postmanPath;
                echo "✅ Generated Postman collection: {$postmanPath}\n";
            }
            
            echo "\n✨ Documentation generated successfully!\n";
            echo "📁 Output directory: {$outputDir}\n";
            echo "📄 Files generated: " . count($generated) . "\n";
            
            return 0;
        } catch (\Exception $e) {
            echo "\n❌ Error generating documentation: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
            return 1;
        }
    }

    /**
     * Generate OpenAPI specification
     * 
     * @param string $outputDir
     * @return string Output file path
     */
    private function generateOpenApi(string $outputDir): string
    {
        echo "  → Scanning controllers...\n";
        
        $generator = new OpenApiGenerator($this->config);
        $spec = $generator->generate();
        
        $outputPath = $outputDir . '/openapi.json';
        file_put_contents($outputPath, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Also generate YAML version
        $yamlPath = $outputDir . '/openapi.yaml';
        $yaml = $this->jsonToYaml($spec);
        file_put_contents($yamlPath, $yaml);
        
        return $outputPath;
    }

    /**
     * Generate error catalog
     * 
     * @param string $outputDir
     * @return string Output file path
     */
    private function generateErrors(string $outputDir): string
    {
        echo "  → Scanning exception classes...\n";
        
        $generator = new ErrorCatalogGenerator($this->config);
        $markdown = $generator->generate();
        
        $outputPath = $outputDir . '/ERROR_CATALOG.md';
        file_put_contents($outputPath, $markdown);
        
        return $outputPath;
    }

    /**
     * Generate Postman collection
     * 
     * @param string $outputDir
     * @return string Output file path
     */
    private function generatePostman(string $outputDir): string
    {
        echo "  → Generating OpenAPI spec...\n";
        
        // Generate OpenAPI spec first
        $generator = new OpenApiGenerator($this->config);
        $openApiSpec = $generator->generate();
        
        echo "  → Converting to Postman format...\n";
        
        // Export to Postman
        $exporter = new PostmanExporter($this->config);
        $collection = $exporter->export($openApiSpec);
        
        $outputPath = $outputDir . '/postman_collection.json';
        file_put_contents($outputPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        return $outputPath;
    }

    /**
     * Get command argument
     * 
     * @param array $args
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getArgument(array $args, string $key, mixed $default = null): mixed
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$key}=")) {
                return substr($arg, strlen("--{$key}="));
            }
        }
        
        return $default;
    }

    /**
     * Convert JSON to YAML (simple implementation)
     * 
     * @param array $data
     * @param int $indent
     * @return string
     */
    private function jsonToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isSequentialArray($value)) {
                    // Sequential array (list)
                    $yaml .= "{$indentStr}{$key}:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "{$indentStr}- \n";
                            $yaml .= $this->jsonToYaml($item, $indent + 1);
                        } else {
                            $yaml .= "{$indentStr}- " . $this->yamlValue($item) . "\n";
                        }
                    }
                } else {
                    // Associative array (object)
                    $yaml .= "{$indentStr}{$key}:\n";
                    $yaml .= $this->jsonToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= "{$indentStr}{$key}: " . $this->yamlValue($value) . "\n";
            }
        }
        
        return $yaml;
    }

    /**
     * Check if array is sequential
     * 
     * @param array $arr
     * @return bool
     */
    private function isSequentialArray(array $arr): bool
    {
        if (empty($arr)) {
            return true;
        }
        
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * Format value for YAML
     * 
     * @param mixed $value
     * @return string
     */
    private function yamlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        if (is_string($value)) {
            // Quote if contains special characters
            if (preg_match('/[:\{\}\[\],&*#?|\-<>=!%@`]/', $value)) {
                return '"' . str_replace('"', '\\"', $value) . '"';
            }
            return $value;
        }
        
        return (string)$value;
    }

    /**
     * Display help message
     * 
     * @return void
     */
    public function help(): void
    {
        echo <<<HELP
Generate API Documentation

Usage:
  php artisan docs:generate [options]

Options:
  --format=<format>   Generate specific format (openapi, errors, postman, all)
                      Default: all
  --output=<path>     Output directory path
                      Default: storage/docs

Examples:
  php artisan docs:generate
  php artisan docs:generate --format=openapi
  php artisan docs:generate --format=errors --output=/tmp/docs
  php artisan docs:generate --format=postman

HELP;
    }
}
