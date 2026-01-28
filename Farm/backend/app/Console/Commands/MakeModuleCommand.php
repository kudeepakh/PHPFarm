<?php

namespace Farm\Backend\App\Console\Commands;

/**
 * Make Module Command
 * 
 * CLI command to scaffold a new module with all required components:
 * - Controllers (with route attributes)
 * - Services (business logic layer)
 * - DAOs (data access with stored procedures)
 * - DTOs (request/response objects)
 * - MySQL tables and stored procedures
 * - MongoDB indexes (if needed)
 * - Module configuration
 * - Route definitions
 * - Tests
 * 
 * Usage:
 * ```bash
 * php artisan make:module Blog
 * php artisan make:module Blog --with-api
 * php artisan make:module Blog --with-crud
 * php artisan make:module Blog --with-mongo
 * php artisan make:module Blog --full
 * ```
 */
class MakeModuleCommand
{
    private string $modulesPath;
    private string $databasePath;
    private string $testsPath;
    private array $config;
    
    public function __construct()
    {
        $this->modulesPath = dirname(__DIR__, 3) . '/modules';
        $this->databasePath = dirname(__DIR__, 3) . '/database';
        $this->testsPath = dirname(__DIR__, 3) . '/tests';
        $this->config = $this->loadConfig();
    }
    
    /**
     * Load scaffold configuration
     */
    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 3) . '/config/scaffold.php';
        return file_exists($configPath) ? require $configPath : [
            'namespace' => 'PHPFrarm\\Modules',
            'author' => 'PHPFrarm Team',
            'default_features' => ['api', 'crud'],
        ];
    }
    
    /**
     * Execute command
     * 
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args = []): int
    {
        $moduleName = $args[0] ?? null;
        
        if (!$moduleName) {
            echo "❌ Error: Module name is required\n";
            echo "Usage: php artisan make:module <ModuleName> [options]\n";
            echo "\nOptions:\n";
            echo "  --with-api     Include API controller with CRUD endpoints\n";
            echo "  --with-crud    Include full CRUD operations\n";
            echo "  --with-mongo   Include MongoDB collection indexes\n";
            echo "  --with-tests   Generate test files\n";
            echo "  --full         Include all components\n";
            return 1;
        }
        
        // Parse options
        $options = $this->parseOptions(array_slice($args, 1));
        
        // Normalize module name
        $moduleName = $this->normalizeModuleName($moduleName);
        $moduleDir = $this->modulesPath . '/' . $moduleName;
        
        if (is_dir($moduleDir)) {
            echo "❌ Error: Module '{$moduleName}' already exists\n";
            return 1;
        }
        
        echo "🚀 Creating module: {$moduleName}\n\n";
        
        try {
            // Create directory structure
            $this->createDirectoryStructure($moduleDir);
            
            // Generate files
            $this->generateModuleConfig($moduleName, $moduleDir);
            $this->generateRoutes($moduleName, $moduleDir);
            $this->generateController($moduleName, $moduleDir, $options);
            $this->generateService($moduleName, $moduleDir);
            $this->generateDAO($moduleName, $moduleDir);
            $this->generateDTOs($moduleName, $moduleDir);
            
            // Database files
            $this->generateMySQLTable($moduleName, $options);
            $this->generateStoredProcedures($moduleName, $options);
            $this->generateMigration($moduleName);
            
            // Optional components
            if ($options['with-mongo'] || $options['full']) {
                $this->generateMongoIndexes($moduleName);
            }
            
            if ($options['with-tests'] || $options['full']) {
                $this->generateTests($moduleName);
            }
            
            echo "\n✅ Module '{$moduleName}' created successfully!\n\n";
            echo "📁 Structure:\n";
            echo "   modules/{$moduleName}/\n";
            echo "   ├── Controllers/{$moduleName}Controller.php\n";
            echo "   ├── Services/{$moduleName}Service.php\n";
            echo "   ├── DAO/{$moduleName}DAO.php\n";
            echo "   ├── DTO/\n";
            echo "   │   ├── Create{$moduleName}Request.php\n";
            echo "   │   ├── Update{$moduleName}Request.php\n";
            echo "   │   └── {$moduleName}Response.php\n";
            echo "   ├── module.php\n";
            echo "   └── routes.php\n";
            echo "\n📄 Database files:\n";
            echo "   database/mysql/tables/{$this->toSnakeCase($moduleName)}.sql\n";
            echo "   database/mysql/stored_procedures/{$this->toSnakeCase($moduleName)}/\n";
            echo "   database/mysql/migrations/XXXXXX_create_{$this->toSnakeCase($moduleName)}_table.sql\n";
            
            echo "\n🎯 Next steps:\n";
            echo "   1. Review generated files\n";
            echo "   2. Run database migrations\n";
            echo "   3. Register module in bootstrap/modules.php\n";
            
            return 0;
        } catch (\Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n";
            return 1;
        }
    }
    
    /**
     * Parse command options
     */
    private function parseOptions(array $args): array
    {
        $defaults = [
            'with-api' => false,
            'with-crud' => false,
            'with-mongo' => false,
            'with-tests' => false,
            'full' => false,
        ];
        
        foreach ($args as $arg) {
            $arg = ltrim($arg, '-');
            if (isset($defaults[$arg])) {
                $defaults[$arg] = true;
            }
        }
        
        // If no options specified, use defaults
        if (!array_filter($defaults)) {
            $defaults['with-api'] = true;
            $defaults['with-crud'] = true;
        }
        
        return $defaults;
    }
    
    /**
     * Normalize module name to PascalCase
     */
    private function normalizeModuleName(string $name): string
    {
        // Convert to PascalCase
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }
    
    /**
     * Convert to snake_case
     */
    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
    
    /**
     * Convert to kebab-case
     */
    private function toKebabCase(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }
    
    /**
     * Create directory structure
     */
    private function createDirectoryStructure(string $moduleDir): void
    {
        $dirs = [
            $moduleDir,
            $moduleDir . '/Controllers',
            $moduleDir . '/Services',
            $moduleDir . '/DAO',
            $moduleDir . '/DTO',
        ];
        
        foreach ($dirs as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create directory: {$dir}");
            }
        }
        
        echo "  ✓ Created directory structure\n";
    }
    
    /**
     * Generate module.php config
     */
    private function generateModuleConfig(string $moduleName, string $moduleDir): void
    {
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        $snakeName = $this->toSnakeCase($moduleName);
        
        $content = <<<PHP
<?php

/**
 * {$moduleName} Module Configuration
 * 
 * Auto-generated by make:module command
 * Generated: %DATE%
 */

use PHPFrarm\Core\ControllerRegistry;
use PHPFrarm\Core\Logger;

return [
    'name' => '{$moduleName}',
    'version' => '1.0.0',
    'description' => '{$moduleName} module',
    'enabled' => true,
    'dependencies' => [],

    /**
     * Database configuration
     * Tables and stored procedures are auto-registered on module load
     */
    'database' => [
        'tables' => [
            '{$snakeName}' => __DIR__ . '/../../database/mysql/tables/{$snakeName}.sql',
        ],
        'stored_procedures' => __DIR__ . '/../../database/mysql/stored_procedures/{$snakeName}/',
        'migrations' => __DIR__ . '/../../database/mysql/migrations/',
    ],

    /**
     * MongoDB collections (if applicable)
     */
    'mongo' => [
        'collections' => [],
        'indexes' => [],
    ],

    /**
     * Module bootstrap
     * Called when module is loaded
     */
    'bootstrap' => function() {
        // Register controllers for automatic route discovery
        ControllerRegistry::register({$namespace}\\{$moduleName}\\Controllers\\{$moduleName}Controller::class);
        
        Logger::info('{$moduleName} module initialized');
    },

    /**
     * Module configuration options
     */
    'config' => [
        'pagination' => [
            'default_limit' => 20,
            'max_limit' => 100,
        ],
        'cache' => [
            'enabled' => true,
            'ttl' => 3600,
        ],
    ],
];

PHP;
        $content = str_replace('%DATE%', date('Y-m-d H:i:s'), $content);
        
        file_put_contents($moduleDir . '/module.php', $content);
        echo "  ✓ Generated module.php\n";
    }
    
    /**
     * Generate routes.php
     */
    private function generateRoutes(string $moduleName, string $moduleDir): void
    {
        $kebabName = $this->toKebabCase($moduleName);
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        
        $content = <<<PHP
<?php

/**
 * {$moduleName} Module Routes
 * 
 * Routes are auto-discovered via PHP 8 attributes on controllers.
 * This file can be used for additional route configuration if needed.
 * 
 * @see {$namespace}\\{$moduleName}\\Controllers\\{$moduleName}Controller
 */

// Routes are defined via #[Route] attributes on the controller
// Example: #[Route('/api/{$kebabName}', methods: ['GET'])]

// Manual route registration (if needed):
// \$router->get('/api/{$kebabName}/custom', [{$moduleName}Controller::class, 'customAction']);

PHP;
        
        file_put_contents($moduleDir . '/routes.php', $content);
        echo "  ✓ Generated routes.php\n";
    }
    
    /**
     * Generate controller
     */
    private function generateController(string $moduleName, string $moduleDir, array $options): void
    {
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        $kebabName = $this->toKebabCase($moduleName);
        $snakeName = $this->toSnakeCase($moduleName);
        $camelName = lcfirst($moduleName);
        
        $content = <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RateLimit;
use PHPFrarm\Core\Documentation\Attributes\ApiDoc;
use PHPFrarm\Core\Documentation\Attributes\ApiParam;
use PHPFrarm\Core\Documentation\Attributes\ApiResponse;
use {$namespace}\\{$moduleName}\\Services\\{$moduleName}Service;
use {$namespace}\\{$moduleName}\\DTO\\Create{$moduleName}Request;
use {$namespace}\\{$moduleName}\\DTO\\Update{$moduleName}Request;

/**
 * {$moduleName} Controller
 * 
 * Handles all {$moduleName} related API endpoints.
 * Auto-generated by make:module command.
 */
class {$moduleName}Controller
{
    private {$moduleName}Service \$service;

    public function __construct()
    {
        \$this->service = new {$moduleName}Service();
    }

    /**
     * List all {$moduleName} records
     */
    #[Route('/api/{$kebabName}', methods: ['GET'])]
    #[RateLimit(requests: 100, window: 60)]
    #[ApiDoc(
        summary: 'List all {$moduleName} records',
        description: 'Returns a paginated list of {$moduleName} records',
        tags: ['{$moduleName}']
    )]
    #[ApiParam(name: 'page', in: 'query', type: 'integer', description: 'Page number')]
    #[ApiParam(name: 'limit', in: 'query', type: 'integer', description: 'Items per page')]
    #[ApiResponse(status: 200, description: 'List of records')]
    public function index(): void
    {
        try {
            \$page = (int) (\$_GET['page'] ?? 1);
            \$limit = (int) (\$_GET['limit'] ?? 20);
            
            \$result = \$this->service->list(\$page, \$limit);
            
            Response::paginated(
                \$result['data'],
                \$result['total'],
                \$page,
                \$limit
            );
        } catch (\Exception \$e) {
            Logger::error('{$moduleName} list failed', ['error' => \$e->getMessage()]);
            Response::error(\$e->getMessage(), 500, '{$snakeName}_LIST_FAILED');
        }
    }

    /**
     * Get single {$moduleName} record
     */
    #[Route('/api/{$kebabName}/{id}', methods: ['GET'])]
    #[RateLimit(requests: 100, window: 60)]
    #[ApiDoc(
        summary: 'Get {$moduleName} by ID',
        description: 'Returns a single {$moduleName} record',
        tags: ['{$moduleName}']
    )]
    #[ApiParam(name: 'id', in: 'path', type: 'string', required: true, description: 'Record ID')]
    #[ApiResponse(status: 200, description: 'Record found')]
    #[ApiResponse(status: 404, description: 'Record not found')]
    public function show(string \$id): void
    {
        try {
            \$record = \$this->service->findById(\$id);
            
            if (!\$record) {
                Response::notFound('module.not_found');
                return;
            }
            
            Response::success(\$record);
        } catch (\Exception \$e) {
            Logger::error('{$moduleName} show failed', ['id' => \$id, 'error' => \$e->getMessage()]);
            Response::error(\$e->getMessage(), 500, '{$snakeName}_SHOW_FAILED');
        }
    }

    /**
     * Create new {$moduleName} record
     */
    #[Route('/api/{$kebabName}', methods: ['POST'])]
    #[RateLimit(requests: 50, window: 60)]
    #[ApiDoc(
        summary: 'Create {$moduleName}',
        description: 'Creates a new {$moduleName} record',
        tags: ['{$moduleName}']
    )]
    #[ApiResponse(status: 201, description: 'Record created')]
    #[ApiResponse(status: 400, description: 'Validation failed')]
    public function store(): void
    {
        try {
            \$data = json_decode(file_get_contents('php://input'), true) ?? [];
            \$request = Create{$moduleName}Request::fromArray(\$data);
            
            // Validate request
            \$errors = \$request->validate();
            if (!empty(\$errors)) {
                Response::validationError(\$errors);
                return;
            }
            
            \$record = \$this->service->create(\$request);
            
            Logger::audit('{$moduleName} created', ['id' => \$record['id']]);
            Response::created(\$record, 'module.created_success');
        } catch (\Exception \$e) {
            Logger::error('{$moduleName} create failed', ['error' => \$e->getMessage()]);
            Response::error(\$e->getMessage(), 500, '{$snakeName}_CREATE_FAILED');
        }
    }

    /**
     * Update {$moduleName} record
     */
    #[Route('/api/{$kebabName}/{id}', methods: ['PUT', 'PATCH'])]
    #[RateLimit(requests: 50, window: 60)]
    #[ApiDoc(
        summary: 'Update {$moduleName}',
        description: 'Updates an existing {$moduleName} record',
        tags: ['{$moduleName}']
    )]
    #[ApiParam(name: 'id', in: 'path', type: 'string', required: true, description: 'Record ID')]
    #[ApiResponse(status: 200, description: 'Record updated')]
    #[ApiResponse(status: 404, description: 'Record not found')]
    public function update(string \$id): void
    {
        try {
            \$data = json_decode(file_get_contents('php://input'), true) ?? [];
            \$request = Update{$moduleName}Request::fromArray(\$data);
            
            // Validate request
            \$errors = \$request->validate();
            if (!empty(\$errors)) {
                Response::validationError(\$errors);
                return;
            }
            
            \$record = \$this->service->update(\$id, \$request);
            
            if (!\$record) {
                Response::notFound('module.not_found');
                return;
            }
            
            Logger::audit('{$moduleName} updated', ['id' => \$id]);
            Response::success(\$record, 'module.updated_success');
        } catch (\Exception \$e) {
            Logger::error('{$moduleName} update failed', ['id' => \$id, 'error' => \$e->getMessage()]);
            Response::error(\$e->getMessage(), 500, '{$snakeName}_UPDATE_FAILED');
        }
    }

    /**
     * Delete {$moduleName} record
     */
    #[Route('/api/{$kebabName}/{id}', methods: ['DELETE'])]
    #[RateLimit(requests: 30, window: 60)]
    #[ApiDoc(
        summary: 'Delete {$moduleName}',
        description: 'Soft deletes a {$moduleName} record',
        tags: ['{$moduleName}']
    )]
    #[ApiParam(name: 'id', in: 'path', type: 'string', required: true, description: 'Record ID')]
    #[ApiResponse(status: 200, description: 'Record deleted')]
    #[ApiResponse(status: 404, description: 'Record not found')]
    public function destroy(string \$id): void
    {
        try {
            \$deleted = \$this->service->delete(\$id);
            
            if (!\$deleted) {
                Response::notFound('module.not_found');
                return;
            }
            
            Logger::audit('{$moduleName} deleted', ['id' => \$id]);
            Response::success(null, 'module.deleted_success');
        } catch (\Exception \$e) {
            Logger::error('{$moduleName} delete failed', ['id' => \$id, 'error' => \$e->getMessage()]);
            Response::error(\$e->getMessage(), 500, '{$snakeName}_DELETE_FAILED');
        }
    }
}

PHP;
        
        file_put_contents($moduleDir . '/Controllers/' . $moduleName . 'Controller.php', $content);
        echo "  ✓ Generated {$moduleName}Controller.php\n";
    }
    
    /**
     * Generate service class
     */
    private function generateService(string $moduleName, string $moduleDir): void
    {
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        
        $content = <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\Services;

use {$namespace}\\{$moduleName}\\DAO\\{$moduleName}DAO;
use {$namespace}\\{$moduleName}\\DTO\\Create{$moduleName}Request;
use {$namespace}\\{$moduleName}\\DTO\\Update{$moduleName}Request;
use PHPFrarm\Core\Utils\IdGenerator;

/**
 * {$moduleName} Service
 * 
 * Business logic layer for {$moduleName} operations.
 * All database operations go through the DAO layer.
 */
class {$moduleName}Service
{
    private {$moduleName}DAO \$dao;

    public function __construct()
    {
        \$this->dao = new {$moduleName}DAO();
    }

    /**
     * List records with pagination
     */
    public function list(int \$page = 1, int \$limit = 20): array
    {
        \$offset = (\$page - 1) * \$limit;
        
        \$data = \$this->dao->findAll(\$limit, \$offset);
        \$total = \$this->dao->count();
        
        return [
            'data' => \$data,
            'total' => \$total,
            'page' => \$page,
            'limit' => \$limit,
            'pages' => (int) ceil(\$total / \$limit)
        ];
    }

    /**
     * Find record by ID
     */
    public function findById(string \$id): ?array
    {
        return \$this->dao->findById(\$id);
    }

    /**
     * Create new record
     */
    public function create(Create{$moduleName}Request \$request): array
    {
        \$id = IdGenerator::generate();
        
        \$data = [
            'id' => \$id,
            ...\$request->toArray(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        \$this->dao->create(\$data);
        
        return \$this->findById(\$id);
    }

    /**
     * Update record
     */
    public function update(string \$id, Update{$moduleName}Request \$request): ?array
    {
        \$existing = \$this->findById(\$id);
        
        if (!\$existing) {
            return null;
        }
        
        \$data = [
            ...\$request->toArray(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        \$this->dao->update(\$id, \$data);
        
        return \$this->findById(\$id);
    }

    /**
     * Delete record (soft delete)
     */
    public function delete(string \$id): bool
    {
        \$existing = \$this->findById(\$id);
        
        if (!\$existing) {
            return false;
        }
        
        return \$this->dao->delete(\$id);
    }
}

PHP;
        
        file_put_contents($moduleDir . '/Services/' . $moduleName . 'Service.php', $content);
        echo "  ✓ Generated {$moduleName}Service.php\n";
    }
    
    /**
     * Generate DAO class
     */
    private function generateDAO(string $moduleName, string $moduleDir): void
    {
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        $snakeName = $this->toSnakeCase($moduleName);
        
        $content = <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\DAO;

use PHPFrarm\Core\Database;

/**
 * {$moduleName} DAO (Data Access Object)
 * 
 * All database operations MUST use stored procedures.
 * Direct SQL queries are NOT allowed per framework rules.
 */
class {$moduleName}DAO
{
    private Database \$db;
    private string \$table = '{$snakeName}';

    public function __construct()
    {
        \$this->db = Database::getInstance();
    }

    /**
     * Find all records with pagination
     * Uses: sp_{$snakeName}_find_all
     */
    public function findAll(int \$limit = 20, int \$offset = 0): array
    {
        return \$this->db->call('sp_{$snakeName}_find_all', [
            'p_limit' => \$limit,
            'p_offset' => \$offset
        ]);
    }

    /**
     * Find record by ID
     * Uses: sp_{$snakeName}_find_by_id
     */
    public function findById(string \$id): ?array
    {
        \$result = \$this->db->call('sp_{$snakeName}_find_by_id', [
            'p_id' => \$id
        ]);
        
        return \$result[0] ?? null;
    }

    /**
     * Create new record
     * Uses: sp_{$snakeName}_create
     */
    public function create(array \$data): bool
    {
        \$this->db->call('sp_{$snakeName}_create', [
            'p_id' => \$data['id'],
            'p_name' => \$data['name'] ?? '',
            'p_description' => \$data['description'] ?? null,
            'p_status' => \$data['status'] ?? 'active',
            'p_created_at' => \$data['created_at'],
            'p_updated_at' => \$data['updated_at']
        ]);
        
        return true;
    }

    /**
     * Update record
     * Uses: sp_{$snakeName}_update
     */
    public function update(string \$id, array \$data): bool
    {
        \$this->db->call('sp_{$snakeName}_update', [
            'p_id' => \$id,
            'p_name' => \$data['name'] ?? null,
            'p_description' => \$data['description'] ?? null,
            'p_status' => \$data['status'] ?? null,
            'p_updated_at' => \$data['updated_at']
        ]);
        
        return true;
    }

    /**
     * Soft delete record
     * Uses: sp_{$snakeName}_soft_delete
     */
    public function delete(string \$id): bool
    {
        \$this->db->call('sp_{$snakeName}_soft_delete', [
            'p_id' => \$id
        ]);
        
        return true;
    }

    /**
     * Count total records
     * Uses: sp_{$snakeName}_count
     */
    public function count(): int
    {
        \$result = \$this->db->call('sp_{$snakeName}_count', []);
        return (int) (\$result[0]['total'] ?? 0);
    }

    /**
     * Hard delete record (permanent)
     * Uses: sp_{$snakeName}_hard_delete
     */
    public function hardDelete(string \$id): bool
    {
        \$this->db->call('sp_{$snakeName}_hard_delete', [
            'p_id' => \$id
        ]);
        
        return true;
    }

    /**
     * Restore soft-deleted record
     * Uses: sp_{$snakeName}_restore
     */
    public function restore(string \$id): bool
    {
        \$this->db->call('sp_{$snakeName}_restore', [
            'p_id' => \$id
        ]);
        
        return true;
    }
}

PHP;
        
        file_put_contents($moduleDir . '/DAO/' . $moduleName . 'DAO.php', $content);
        echo "  ✓ Generated {$moduleName}DAO.php\n";
    }
    
    /**
     * Generate DTOs
     */
    private function generateDTOs(string $moduleName, string $moduleDir): void
    {
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        
        // Create Request DTO
        $createDto = <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\DTO;

/**
 * Create {$moduleName} Request DTO
 */
class Create{$moduleName}Request
{
    public function __construct(
        public string \$name,
        public ?string \$description = null,
        public string \$status = 'active'
    ) {}

    /**
     * Create from array
     */
    public static function fromArray(array \$data): self
    {
        return new self(
            name: \$data['name'] ?? '',
            description: \$data['description'] ?? null,
            status: \$data['status'] ?? 'active'
        );
    }

    /**
     * Validate request data
     */
    public function validate(): array
    {
        \$errors = [];

        if (empty(\$this->name)) {
            \$errors['name'] = 'Name is required';
        } elseif (strlen(\$this->name) < 2) {
            \$errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen(\$this->name) > 255) {
            \$errors['name'] = 'Name must not exceed 255 characters';
        }

        if (\$this->description !== null && strlen(\$this->description) > 1000) {
            \$errors['description'] = 'Description must not exceed 1000 characters';
        }

        if (!in_array(\$this->status, ['active', 'inactive', 'draft'])) {
            \$errors['status'] = 'Invalid status value';
        }

        return \$errors;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => \$this->name,
            'description' => \$this->description,
            'status' => \$this->status,
        ];
    }
}

PHP;

        // Update Request DTO
        $updateDto = <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\DTO;

/**
 * Update {$moduleName} Request DTO
 */
class Update{$moduleName}Request
{
    public function __construct(
        public ?string \$name = null,
        public ?string \$description = null,
        public ?string \$status = null
    ) {}

    /**
     * Create from array
     */
    public static function fromArray(array \$data): self
    {
        return new self(
            name: \$data['name'] ?? null,
            description: \$data['description'] ?? null,
            status: \$data['status'] ?? null
        );
    }

    /**
     * Validate request data
     */
    public function validate(): array
    {
        \$errors = [];

        if (\$this->name !== null) {
            if (strlen(\$this->name) < 2) {
                \$errors['name'] = 'Name must be at least 2 characters';
            } elseif (strlen(\$this->name) > 255) {
                \$errors['name'] = 'Name must not exceed 255 characters';
            }
        }

        if (\$this->description !== null && strlen(\$this->description) > 1000) {
            \$errors['description'] = 'Description must not exceed 1000 characters';
        }

        if (\$this->status !== null && !in_array(\$this->status, ['active', 'inactive', 'draft'])) {
            \$errors['status'] = 'Invalid status value';
        }

        return \$errors;
    }

    /**
     * Convert to array (only non-null values)
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => \$this->name,
            'description' => \$this->description,
            'status' => \$this->status,
        ], fn(\$v) => \$v !== null);
    }
}

PHP;

        // Response DTO
        $responseDto = <<<PHP
<?php

namespace {$namespace}\\{$moduleName}\\DTO;

/**
 * {$moduleName} Response DTO
 */
class {$moduleName}Response
{
    public function __construct(
        public string \$id,
        public string \$name,
        public ?string \$description,
        public string \$status,
        public string \$created_at,
        public string \$updated_at,
        public ?string \$deleted_at = null
    ) {}

    /**
     * Create from database record
     */
    public static function fromArray(array \$data): self
    {
        return new self(
            id: \$data['id'],
            name: \$data['name'],
            description: \$data['description'] ?? null,
            status: \$data['status'],
            created_at: \$data['created_at'],
            updated_at: \$data['updated_at'],
            deleted_at: \$data['deleted_at'] ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => \$this->id,
            'name' => \$this->name,
            'description' => \$this->description,
            'status' => \$this->status,
            'created_at' => \$this->created_at,
            'updated_at' => \$this->updated_at,
            'deleted_at' => \$this->deleted_at,
        ];
    }
}

PHP;
        
        file_put_contents($moduleDir . '/DTO/Create' . $moduleName . 'Request.php', $createDto);
        file_put_contents($moduleDir . '/DTO/Update' . $moduleName . 'Request.php', $updateDto);
        file_put_contents($moduleDir . '/DTO/' . $moduleName . 'Response.php', $responseDto);
        
        echo "  ✓ Generated DTOs (Create, Update, Response)\n";
    }
    
    /**
     * Generate MySQL table
     */
    private function generateMySQLTable(string $moduleName, array $options): void
    {
        $snakeName = $this->toSnakeCase($moduleName);
        $tableDir = $this->databasePath . '/mysql/tables';
        
        if (!is_dir($tableDir)) {
            mkdir($tableDir, 0755, true);
        }
        
        $content = <<<SQL
-- {$moduleName} Table
-- Auto-generated by make:module command
-- Generated: %DATE%

CREATE TABLE IF NOT EXISTS `{$snakeName}` (
    `id` CHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID primary key',
    `name` VARCHAR(255) NOT NULL COMMENT 'Record name',
    `description` TEXT NULL COMMENT 'Optional description',
    `status` ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'active' COMMENT 'Record status',
    `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Optimistic locking version',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    
    -- Indexes for common queries
    INDEX `idx_{$snakeName}_status` (`status`),
    INDEX `idx_{$snakeName}_created_at` (`created_at`),
    INDEX `idx_{$snakeName}_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='{$moduleName} table - Auto-generated';

SQL;
        $content = str_replace('%DATE%', date('Y-m-d H:i:s'), $content);
        
        file_put_contents($tableDir . '/' . $snakeName . '.sql', $content);
        echo "  ✓ Generated MySQL table: {$snakeName}.sql\n";
    }
    
    /**
     * Generate stored procedures
     */
    private function generateStoredProcedures(string $moduleName, array $options): void
    {
        $snakeName = $this->toSnakeCase($moduleName);
        $spDir = $this->databasePath . '/mysql/stored_procedures/' . $snakeName;
        
        if (!is_dir($spDir)) {
            mkdir($spDir, 0755, true);
        }
        
        // Find all procedure
        $findAll = <<<SQL
-- Find all {$moduleName} records with pagination
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_find_all //

CREATE PROCEDURE sp_{$snakeName}_find_all(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT 
        id,
        name,
        description,
        status,
        version,
        created_at,
        updated_at,
        deleted_at
    FROM {$snakeName}
    WHERE deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //

DELIMITER ;

SQL;

        // Find by ID procedure
        $findById = <<<SQL
-- Find {$moduleName} by ID
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_find_by_id //

CREATE PROCEDURE sp_{$snakeName}_find_by_id(
    IN p_id CHAR(26)
)
BEGIN
    SELECT 
        id,
        name,
        description,
        status,
        version,
        created_at,
        updated_at,
        deleted_at
    FROM {$snakeName}
    WHERE id = p_id AND deleted_at IS NULL;
END //

DELIMITER ;

SQL;

        // Create procedure
        $create = <<<SQL
-- Create new {$moduleName} record
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_create //

CREATE PROCEDURE sp_{$snakeName}_create(
    IN p_id CHAR(26),
    IN p_name VARCHAR(255),
    IN p_description TEXT,
    IN p_status VARCHAR(20),
    IN p_created_at TIMESTAMP,
    IN p_updated_at TIMESTAMP
)
BEGIN
    INSERT INTO {$snakeName} (
        id,
        name,
        description,
        status,
        created_at,
        updated_at
    ) VALUES (
        p_id,
        p_name,
        p_description,
        p_status,
        p_created_at,
        p_updated_at
    );
END //

DELIMITER ;

SQL;

        // Update procedure
        $update = <<<SQL
-- Update {$moduleName} record
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_update //

CREATE PROCEDURE sp_{$snakeName}_update(
    IN p_id CHAR(26),
    IN p_name VARCHAR(255),
    IN p_description TEXT,
    IN p_status VARCHAR(20),
    IN p_updated_at TIMESTAMP
)
BEGIN
    UPDATE {$snakeName}
    SET 
        name = COALESCE(p_name, name),
        description = COALESCE(p_description, description),
        status = COALESCE(p_status, status),
        updated_at = p_updated_at,
        version = version + 1
    WHERE id = p_id AND deleted_at IS NULL;
END //

DELIMITER ;

SQL;

        // Soft delete procedure
        $softDelete = <<<SQL
-- Soft delete {$moduleName} record
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_soft_delete //

CREATE PROCEDURE sp_{$snakeName}_soft_delete(
    IN p_id CHAR(26)
)
BEGIN
    UPDATE {$snakeName}
    SET 
        deleted_at = NOW(),
        updated_at = NOW()
    WHERE id = p_id AND deleted_at IS NULL;
END //

DELIMITER ;

SQL;

        // Hard delete procedure
        $hardDelete = <<<SQL
-- Hard delete {$moduleName} record (permanent)
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_hard_delete //

CREATE PROCEDURE sp_{$snakeName}_hard_delete(
    IN p_id CHAR(26)
)
BEGIN
    DELETE FROM {$snakeName}
    WHERE id = p_id;
END //

DELIMITER ;

SQL;

        // Restore procedure
        $restore = <<<SQL
-- Restore soft-deleted {$moduleName} record
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_restore //

CREATE PROCEDURE sp_{$snakeName}_restore(
    IN p_id CHAR(26)
)
BEGIN
    UPDATE {$snakeName}
    SET 
        deleted_at = NULL,
        updated_at = NOW()
    WHERE id = p_id AND deleted_at IS NOT NULL;
END //

DELIMITER ;

SQL;

        // Count procedure
        $count = <<<SQL
-- Count {$moduleName} records
-- Auto-generated by make:module command

DELIMITER //

DROP PROCEDURE IF EXISTS sp_{$snakeName}_count //

CREATE PROCEDURE sp_{$snakeName}_count()
BEGIN
    SELECT COUNT(*) AS total
    FROM {$snakeName}
    WHERE deleted_at IS NULL;
END //

DELIMITER ;

SQL;
        
        file_put_contents($spDir . '/find_all.sql', $findAll);
        file_put_contents($spDir . '/find_by_id.sql', $findById);
        file_put_contents($spDir . '/create.sql', $create);
        file_put_contents($spDir . '/update.sql', $update);
        file_put_contents($spDir . '/soft_delete.sql', $softDelete);
        file_put_contents($spDir . '/hard_delete.sql', $hardDelete);
        file_put_contents($spDir . '/restore.sql', $restore);
        file_put_contents($spDir . '/count.sql', $count);
        
        echo "  ✓ Generated stored procedures (8 files)\n";
    }
    
    /**
     * Generate migration file
     */
    private function generateMigration(string $moduleName): void
    {
        $snakeName = $this->toSnakeCase($moduleName);
        $migrationDir = $this->databasePath . '/mysql/migrations';
        
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }
        
        $timestamp = date('YmdHis');
        $filename = "{$timestamp}_create_{$snakeName}_table.sql";
        
        $content = <<<SQL
-- Migration: Create {$moduleName} table and stored procedures
-- Auto-generated by make:module command
-- Generated: %DATE%
-- Version: 1.0.0

-- ============================================================
-- UP MIGRATION
-- ============================================================

-- Create table
SOURCE ../tables/{$snakeName}.sql;

-- Create stored procedures
SOURCE ../stored_procedures/{$snakeName}/find_all.sql;
SOURCE ../stored_procedures/{$snakeName}/find_by_id.sql;
SOURCE ../stored_procedures/{$snakeName}/create.sql;
SOURCE ../stored_procedures/{$snakeName}/update.sql;
SOURCE ../stored_procedures/{$snakeName}/soft_delete.sql;
SOURCE ../stored_procedures/{$snakeName}/hard_delete.sql;
SOURCE ../stored_procedures/{$snakeName}/restore.sql;
SOURCE ../stored_procedures/{$snakeName}/count.sql;

-- Record migration
INSERT INTO migrations (migration, batch, executed_at)
VALUES ('{$filename}', (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m), NOW());

-- ============================================================
-- DOWN MIGRATION (for rollback)
-- ============================================================

-- To rollback, execute:
-- DROP TABLE IF EXISTS {$snakeName};
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_find_all;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_find_by_id;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_create;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_update;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_soft_delete;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_hard_delete;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_restore;
-- DROP PROCEDURE IF EXISTS sp_{$snakeName}_count;
-- DELETE FROM migrations WHERE migration = '{$filename}';

SQL;
        $content = str_replace('%DATE%', date('Y-m-d H:i:s'), $content);
        
        file_put_contents($migrationDir . '/' . $filename, $content);
        echo "  ✓ Generated migration: {$filename}\n";
    }
    
    /**
     * Generate MongoDB indexes
     */
    private function generateMongoIndexes(string $moduleName): void
    {
        $snakeName = $this->toSnakeCase($moduleName);
        $indexDir = $this->databasePath . '/mongo/indexes';
        
        if (!is_dir($indexDir)) {
            mkdir($indexDir, 0755, true);
        }
        
        $content = <<<JS
// MongoDB indexes for {$moduleName} collection
// Auto-generated by make:module command
// Execute with: mongo phpfrarm_logs --eval "load('path/to/{$snakeName}_indexes.js')"

db.{$snakeName}_logs.createIndex({ "correlation_id": 1 });
db.{$snakeName}_logs.createIndex({ "transaction_id": 1 });
db.{$snakeName}_logs.createIndex({ "timestamp": -1 });
db.{$snakeName}_logs.createIndex({ "level": 1 });
db.{$snakeName}_logs.createIndex({ "record_id": 1 });

// Compound indexes for common queries
db.{$snakeName}_logs.createIndex({ "timestamp": -1, "level": 1 });
db.{$snakeName}_logs.createIndex({ "record_id": 1, "timestamp": -1 });

// TTL index to auto-delete old logs (90 days)
db.{$snakeName}_logs.createIndex({ "timestamp": 1 }, { expireAfterSeconds: 7776000 });

print("Indexes created for {$snakeName}_logs collection");

JS;
        
        file_put_contents($indexDir . '/' . $snakeName . '_indexes.js', $content);
        echo "  ✓ Generated MongoDB indexes: {$snakeName}_indexes.js\n";
    }
    
    /**
     * Generate test files
     */
    private function generateTests(string $moduleName): void
    {
        $snakeName = $this->toSnakeCase($moduleName);
        $testDir = $this->testsPath . '/Unit/' . $moduleName;
        
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        $namespace = $this->config['namespace'] ?? 'PHPFrarm\\Modules';
        
        $content = <<<PHP
<?php

namespace Tests\\Unit\\{$moduleName};

use PHPUnit\Framework\TestCase;
use {$namespace}\\{$moduleName}\\Services\\{$moduleName}Service;
use {$namespace}\\{$moduleName}\\DTO\\Create{$moduleName}Request;
use {$namespace}\\{$moduleName}\\DTO\\Update{$moduleName}Request;

/**
 * {$moduleName} Service Test
 * Auto-generated by make:module command
 */
class {$moduleName}ServiceTest extends TestCase
{
    private {$moduleName}Service \$service;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->service = new {$moduleName}Service();
    }

    public function test_can_create_record(): void
    {
        \$request = Create{$moduleName}Request::fromArray([
            'name' => 'Test {$moduleName}',
            'description' => 'Test description',
            'status' => 'active'
        ]);

        \$result = \$this->service->create(\$request);

        \$this->assertNotNull(\$result);
        \$this->assertEquals('Test {$moduleName}', \$result['name']);
    }

    public function test_can_find_record_by_id(): void
    {
        // Create a record first
        \$request = Create{$moduleName}Request::fromArray([
            'name' => 'Find Test',
        ]);
        \$created = \$this->service->create(\$request);

        // Find it
        \$found = \$this->service->findById(\$created['id']);

        \$this->assertNotNull(\$found);
        \$this->assertEquals(\$created['id'], \$found['id']);
    }

    public function test_can_update_record(): void
    {
        // Create a record
        \$createRequest = Create{$moduleName}Request::fromArray([
            'name' => 'Update Test',
        ]);
        \$created = \$this->service->create(\$createRequest);

        // Update it
        \$updateRequest = Update{$moduleName}Request::fromArray([
            'name' => 'Updated Name',
        ]);
        \$updated = \$this->service->update(\$created['id'], \$updateRequest);

        \$this->assertNotNull(\$updated);
        \$this->assertEquals('Updated Name', \$updated['name']);
    }

    public function test_can_delete_record(): void
    {
        // Create a record
        \$request = Create{$moduleName}Request::fromArray([
            'name' => 'Delete Test',
        ]);
        \$created = \$this->service->create(\$request);

        // Delete it
        \$deleted = \$this->service->delete(\$created['id']);

        \$this->assertTrue(\$deleted);
        \$this->assertNull(\$this->service->findById(\$created['id']));
    }

    public function test_list_returns_paginated_results(): void
    {
        \$result = \$this->service->list(1, 10);

        \$this->assertArrayHasKey('data', \$result);
        \$this->assertArrayHasKey('total', \$result);
        \$this->assertArrayHasKey('page', \$result);
        \$this->assertArrayHasKey('limit', \$result);
        \$this->assertArrayHasKey('pages', \$result);
    }
}

PHP;
        
        file_put_contents($testDir . '/' . $moduleName . 'ServiceTest.php', $content);
        echo "  ✓ Generated test: {$moduleName}ServiceTest.php\n";
    }
    
    /**
     * Get argument value
     */
    private function getArgument(array $args, string $name, mixed $default = null): mixed
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }
        }
        return $default;
    }
}
