<?php

namespace Farm\Backend\App\Core\Documentation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Error Catalog Generator
 * 
 * Scans exception classes and generates a comprehensive error catalog
 * in markdown format with error codes, descriptions, and HTTP status codes.
 * 
 * Usage:
 * ```php
 * $generator = new ErrorCatalogGenerator($config);
 * $markdown = $generator->generate();
 * file_put_contents('ERROR_CATALOG.md', $markdown);
 * ```
 */
class ErrorCatalogGenerator
{
    private array $config;
    private array $errors = [];

    /**
     * @param array $config Documentation configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Generate error catalog markdown
     * 
     * @return string Markdown content
     */
    public function generate(): string
    {
        // Scan exception directories
        $this->scanExceptions();
        
        // Build markdown
        $markdown = $this->buildMarkdown();
        
        return $markdown;
    }

    /**
     * Scan exception directories
     * 
     * @return void
     */
    private function scanExceptions(): void
    {
        $exceptionPaths = $this->config['exception_paths'] ?? [];
        
        foreach ($exceptionPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $this->scanDirectory($path);
        }
        
        // Sort errors by code
        usort($this->errors, fn($a, $b) => strcmp($a['code'], $b['code']));
    }

    /**
     * Recursively scan directory for exceptions
     * 
     * @param string $directory
     * @return void
     */
    private function scanDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Exception.php')) {
                $this->scanExceptionFile($file->getPathname());
            }
        }
    }

    /**
     * Scan single exception file
     * 
     * @param string $filePath
     * @return void
     */
    private function scanExceptionFile(string $filePath): void
    {
        // Extract namespace and class name
        $content = file_get_contents($filePath);
        
        if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            return;
        }
        
        if (!preg_match('/class\s+(\w+Exception)/', $content, $classMatch)) {
            return;
        }
        
        $namespace = $nsMatch[1];
        $className = $classMatch[1];
        $fullClassName = $namespace . '\\' . $className;
        
        // Check if class exists
        if (!class_exists($fullClassName)) {
            return;
        }
        
        $this->extractErrorInfo($fullClassName);
    }

    /**
     * Extract error information from exception class
     * 
     * @param string $className
     * @return void
     */
    private function extractErrorInfo(string $className): void
    {
        $reflection = new ReflectionClass($className);
        
        // Skip abstract classes
        if ($reflection->isAbstract()) {
            return;
        }
        
        // Get error code (from constant or property)
        $errorCode = $this->extractErrorCode($reflection);
        
        if ($errorCode === null) {
            $errorCode = 'UNKNOWN_ERROR';
        }
        
        // Get HTTP status code
        $httpStatus = $this->extractHttpStatus($reflection);
        
        // Get description from docblock
        $description = $this->extractDescription($reflection->getDocComment());
        
        // Get category
        $category = $this->extractCategory($reflection);
        
        $this->errors[] = [
            'code' => $errorCode,
            'http_status' => $httpStatus,
            'description' => $description ?? 'No description available',
            'class' => $className,
            'category' => $category,
        ];
    }

    /**
     * Extract error code from exception class
     * 
     * @param ReflectionClass $reflection
     * @return string|null
     */
    private function extractErrorCode(ReflectionClass $reflection): ?string
    {
        // Check for ERROR_CODE constant
        if ($reflection->hasConstant('ERROR_CODE')) {
            return $reflection->getConstant('ERROR_CODE');
        }
        
        // Check for CODE constant
        if ($reflection->hasConstant('CODE')) {
            return $reflection->getConstant('CODE');
        }
        
        // Check for code property default value
        $codeProperty = $reflection->getProperty('code');
        if ($codeProperty->hasDefaultValue()) {
            return $codeProperty->getDefaultValue();
        }
        
        return null;
    }

    /**
     * Extract HTTP status code from exception class
     * 
     * @param ReflectionClass $reflection
     * @return int
     */
    private function extractHttpStatus(ReflectionClass $reflection): int
    {
        // Check for HTTP_STATUS constant
        if ($reflection->hasConstant('HTTP_STATUS')) {
            return $reflection->getConstant('HTTP_STATUS');
        }
        
        // Check for STATUS constant
        if ($reflection->hasConstant('STATUS')) {
            return $reflection->getConstant('STATUS');
        }
        
        // Infer from class name
        $className = $reflection->getShortName();
        
        return match (true) {
            str_contains($className, 'NotFound') => 404,
            str_contains($className, 'Unauthorized') => 401,
            str_contains($className, 'Forbidden') => 403,
            str_contains($className, 'BadRequest') => 400,
            str_contains($className, 'Validation') => 400,
            str_contains($className, 'Conflict') => 409,
            str_contains($className, 'TooManyRequests') => 429,
            default => 500,
        };
    }

    /**
     * Extract description from docblock
     * 
     * @param string|false $docComment
     * @return string|null
     */
    private function extractDescription($docComment): ?string
    {
        if ($docComment === false) {
            return null;
        }
        
        // Remove /** and */
        $docComment = trim($docComment, "/* \t\n\r");
        
        // Split into lines
        $lines = explode("\n", $docComment);
        $description = [];
        
        foreach ($lines as $line) {
            $line = trim($line, "* \t");
            
            // Stop at first @tag
            if (str_starts_with($line, '@')) {
                break;
            }
            
            if (!empty($line)) {
                $description[] = $line;
            }
        }
        
        return !empty($description) ? implode(' ', $description) : null;
    }

    /**
     * Extract category from exception class namespace
     * 
     * @param ReflectionClass $reflection
     * @return string
     */
    private function extractCategory(ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();
        
        // Extract category from namespace
        if (preg_match('/Exceptions\\\\(\w+)/', $namespace, $matches)) {
            return $matches[1];
        }
        
        return 'General';
    }

    /**
     * Build markdown content
     * 
     * @return string
     */
    private function buildMarkdown(): string
    {
        $markdown = "# Error Catalog\n\n";
        $markdown .= "> Auto-generated error documentation\n\n";
        $markdown .= "**Generated:** " . date('Y-m-d H:i:s T') . "\n\n";
        $markdown .= "---\n\n";
        
        // Group by category
        $byCategory = [];
        foreach ($this->errors as $error) {
            $category = $error['category'];
            if (!isset($byCategory[$category])) {
                $byCategory[$category] = [];
            }
            $byCategory[$category][] = $error;
        }
        
        // Table of contents
        $markdown .= "## Table of Contents\n\n";
        foreach (array_keys($byCategory) as $category) {
            $anchor = strtolower(str_replace(' ', '-', $category));
            $markdown .= "- [{$category}](#{$anchor})\n";
        }
        $markdown .= "\n---\n\n";
        
        // Error details by category
        foreach ($byCategory as $category => $errors) {
            $markdown .= "## {$category}\n\n";
            
            foreach ($errors as $error) {
                $markdown .= "### `{$error['code']}`\n\n";
                $markdown .= "**HTTP Status:** {$error['http_status']}\n\n";
                $markdown .= "**Description:** {$error['description']}\n\n";
                $markdown .= "**Exception Class:** `{$error['class']}`\n\n";
                
                // Example response
                $markdown .= "**Example Response:**\n";
                $markdown .= "```json\n";
                $markdown .= json_encode([
                    'success' => false,
                    'error' => [
                        'code' => $error['code'],
                        'message' => $error['description'],
                        'http_status' => $error['http_status'],
                    ],
                    'meta' => [
                        'correlation_id' => '01HQZK1234567890ABCDEF',
                        'timestamp' => date('c'),
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $markdown .= "\n```\n\n";
                
                $markdown .= "---\n\n";
            }
        }
        
        // Summary
        $markdown .= "## Summary\n\n";
        $markdown .= "**Total Errors:** " . count($this->errors) . "\n\n";
        
        $markdown .= "**By HTTP Status:**\n\n";
        $statusCounts = [];
        foreach ($this->errors as $error) {
            $status = $error['http_status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        ksort($statusCounts);
        
        foreach ($statusCounts as $status => $count) {
            $markdown .= "- **{$status}:** {$count} errors\n";
        }
        
        return $markdown;
    }
}
