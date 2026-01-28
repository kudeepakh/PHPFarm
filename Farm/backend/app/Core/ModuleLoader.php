<?php

namespace PHPFrarm\Core;

use MongoDB\Client as MongoClient;
use PHPFrarm\Core\Database;
use PHPFrarm\Core\FileCache;

/**
 * ModuleLoader - Auto-discovers and loads modules
 */
class ModuleLoader
{
    private static array $loadedModules = [];
    private static string $modulesPath;

    /**
     * Initialize module loader
     */
    public static function init(string $modulesPath): void
    {
        self::$modulesPath = $modulesPath;
    }

    /**
     * Auto-discover and load all modules
     */
    public static function loadAll(): void
    {
        if (!is_dir(self::$modulesPath)) {
            Logger::warning('Modules directory not found', ['path' => self::$modulesPath]);
            return;
        }

        // Try to load from file cache first
        $cacheData = FileCache::get('modules_loaded');
        if ($cacheData !== null && self::isModuleCacheValid($cacheData)) {
            // We have cached module metadata, but still need to load module.php files
            // to execute bootstrap and register controllers (closures can't be cached)
            $modules = $cacheData['modules'];
            $loadTimes = [];
            
            foreach ($modules as $moduleName => $moduleData) {
                $moduleStart = microtime(true);
                $modulePath = $moduleData['path'] ?? null;
                
                if ($modulePath && is_dir($modulePath)) {
                    self::loadModule($moduleName, $modulePath);
                }
                
                $loadTimes[$moduleName] = round((microtime(true) - $moduleStart) * 1000, 2);
            }
            
            Logger::info('Modules loaded from cache (quick load)', [
                'count' => count(self::$loadedModules),
                'modules' => array_keys(self::$loadedModules),
                'load_times_ms' => $loadTimes,
                'total_ms' => round(array_sum($loadTimes), 2)
            ]);
            return;
        }

        // Cache miss or invalid - do full discovery
        $discoverStart = microtime(true);
        $modules = self::discoverModules();
        $discoverTime = (microtime(true) - $discoverStart) * 1000;
        
        $loadTimes = [];
        foreach ($modules as $moduleName => $modulePath) {
            $moduleStart = microtime(true);
            self::loadModule($moduleName, $modulePath);
            $loadTimes[$moduleName] = round((microtime(true) - $moduleStart) * 1000, 2);
        }

        // Cache the loaded modules
        self::cacheModules();

        Logger::info('Modules loaded', [
            'count' => count(self::$loadedModules),
            'modules' => array_keys(self::$loadedModules),
            'discover_ms' => round($discoverTime, 2),
            'load_times_ms' => $loadTimes,
            'total_ms' => round(array_sum($loadTimes) + $discoverTime, 2)
        ]);
    }

    /**
     * Cache the loaded modules to file cache
     */
    private static function cacheModules(): void
    {
        // Remove closures and non-serializable data before caching
        $cacheableModules = [];
        foreach (self::$loadedModules as $moduleName => $moduleData) {
            $cacheableModules[$moduleName] = [
                'path' => $moduleData['path'] ?? null,
                'controllers' => $moduleData['controllers'] ?? [],
                // Exclude 'config' as it may contain closures (bootstrap functions)
            ];
        }

        $cacheData = [
            'modules' => $cacheableModules,
            'cached_at' => time(),
            'env' => $_ENV['APP_ENV'] ?? 'development'
        ];

        FileCache::put('modules_loaded', $cacheData);
    }

    /**
     * Check if module cache is still valid
     */
    private static function isModuleCacheValid(array $cacheData): bool
    {
        // Check cache age based on environment
        $cacheAge = time() - ($cacheData['cached_at'] ?? 0);
        $maxAge = ($_ENV['APP_ENV'] ?? 'development') === 'production' ? 3600 : 60;

        if ($cacheAge > $maxAge) {
            return false;
        }

        // Check if any module.php files have been modified since cache creation
        $modules = self::discoverModules();
        foreach ($modules as $moduleName => $modulePath) {
            $moduleFile = $modulePath . '/module.php';
            if (file_exists($moduleFile)) {
                $fileTime = filemtime($moduleFile);
                if ($fileTime > ($cacheData['cached_at'] ?? 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Discover all available modules
     */
    private static function discoverModules(): array
    {
        $modules = [];
        $directories = glob(self::$modulesPath . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $moduleFile = $dir . '/module.php';

            // Check if module has a module.php file
            if (file_exists($moduleFile)) {
                $modules[$moduleName] = $dir;
            }
        }

        return $modules;
    }

    /**
     * Load a specific module
     */
    private static function loadModule(string $moduleName, string $modulePath): void
    {
        try {
            $moduleFile = $modulePath . '/module.php';
            
            // Load module configuration
            $moduleConfig = require $moduleFile;

            // Validate module config
            if (!is_array($moduleConfig)) {
                throw new \Exception("Module $moduleName must return an array");
            }

            // Store module info
            self::$loadedModules[$moduleName] = [
                'path' => $modulePath,
                'config' => $moduleConfig,
                'enabled' => $moduleConfig['enabled'] ?? true,
            ];

            // Skip if module is disabled
            if (!self::$loadedModules[$moduleName]['enabled']) {
                Logger::info("Module skipped (disabled)", ['module' => $moduleName]);
                return;
            }

            // Auto-initialize module resources (DB/Mongo) if configured
            self::initializeModuleResources($moduleName, $modulePath, $moduleConfig);

            // Execute module bootstrap if exists
            if (isset($moduleConfig['bootstrap']) && is_callable($moduleConfig['bootstrap'])) {
                $moduleConfig['bootstrap']();
            }

            // Load routes file if exists
            $routesFile = $modulePath . '/routes.php';
            if (file_exists($routesFile)) {
                require $routesFile;
            }

            Logger::debug("Module loaded successfully", ['module' => $moduleName]);

        } catch (\Exception $e) {
            Logger::error("Failed to load module", [
                'module' => $moduleName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get loaded modules
     */
    public static function getLoadedModules(): array
    {
        return self::$loadedModules;
    }

    /**
     * Check if module is loaded
     */
    public static function isLoaded(string $moduleName): bool
    {
        return isset(self::$loadedModules[$moduleName]);
    }

    /**
     * Get module config
     */
    public static function getModuleConfig(string $moduleName): ?array
    {
        return self::$loadedModules[$moduleName]['config'] ?? null;
    }

    /**
     * Initialize module resources (database + mongo) with safe idempotency.
     */
    private static function initializeModuleResources(string $moduleName, string $modulePath, array $moduleConfig): void
    {
        if (self::shouldAutoInitDatabase()) {
            self::initializeModuleDatabase($moduleName, $modulePath, $moduleConfig);
        }

        if (self::shouldAutoInitMongo()) {
            self::initializeModuleMongo($moduleName, $moduleConfig);
        }
    }

    private static function shouldAutoInitDatabase(): bool
    {
        if (isset($_ENV['MODULE_AUTO_DB'])) {
            return filter_var($_ENV['MODULE_AUTO_DB'], FILTER_VALIDATE_BOOL);
        }

        return ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    }

    private static function shouldAutoInitMongo(): bool
    {
        if (isset($_ENV['MODULE_AUTO_MONGO'])) {
            return filter_var($_ENV['MODULE_AUTO_MONGO'], FILTER_VALIDATE_BOOL);
        }

        return ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    }

    private static function initializeModuleDatabase(string $moduleName, string $modulePath, array $moduleConfig): void
    {
        try {
            Database::enableRawQueries();

            self::ensureModuleRegistryTable();
            if (self::isModuleDbInitialized($moduleName)) {
                return;
            }

            $dbConfig = $moduleConfig['database'] ?? [];
            $dbFiles = self::resolveDatabaseFiles($moduleName, $modulePath, $dbConfig);

            foreach ($dbFiles['tables'] as $tableFile) {
                self::executeSqlFile($tableFile);
            }

            foreach ($dbFiles['stored_procedures'] as $spFile) {
                self::executeSqlFile($spFile);
            }

            self::markModuleDbInitialized($moduleName, $moduleConfig['version'] ?? '1.0.0');
        } catch (\Exception $e) {
            Logger::error('Module DB initialization failed', [
                'module' => $moduleName,
                'error' => $e->getMessage()
            ]);
        } finally {
            Database::disableRawQueries();
        }
    }

    private static function initializeModuleMongo(string $moduleName, array $moduleConfig): void
    {
        try {
            Database::enableRawQueries();
            self::ensureModuleRegistryTable();
            if (self::isModuleMongoInitialized($moduleName)) {
                return;
            }

            $mongoConfig = $moduleConfig['mongo'] ?? [];
            $collections = $mongoConfig['collections'] ?? [];
            $indexes = $mongoConfig['indexes'] ?? [];

            if (empty($collections) && empty($indexes)) {
                return;
            }

            $client = self::getMongoClient();
            if ($client === null) {
                return;
            }

            $database = $_ENV['MONGO_DATABASE'] ?? 'phpfrarm_logs';
            $db = $client->selectDatabase($database);

            foreach ($collections as $collectionName) {
                $collection = $db->selectCollection($collectionName);
                $collection->createIndex(['correlation_id' => 1]);
                $collection->createIndex(['transaction_id' => 1]);
                $collection->createIndex(['timestamp' => -1]);
            }

            foreach ($indexes as $index) {
                if (empty($index['collection']) || empty($index['keys'])) {
                    continue;
                }

                $collection = $db->selectCollection($index['collection']);
                $options = $index['options'] ?? [];
                $collection->createIndex($index['keys'], $options);
            }

            self::markModuleMongoInitialized($moduleName, $moduleConfig['version'] ?? '1.0.0');
        } catch (\Exception $e) {
            Logger::error('Module Mongo initialization failed', [
                'module' => $moduleName,
                'error' => $e->getMessage()
            ]);
        } finally {
            Database::disableRawQueries();
        }
    }

    private static function ensureModuleRegistryTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS module_registry (
    module_name VARCHAR(100) PRIMARY KEY,
    module_version VARCHAR(50) DEFAULT NULL,
    db_initialized_at TIMESTAMP NULL,
    mongo_initialized_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        Database::execute($sql);
    }

    private static function isModuleDbInitialized(string $moduleName): bool
    {
        $result = Database::execute(
            'SELECT db_initialized_at FROM module_registry WHERE module_name = ?',
            [$moduleName]
        );

        if (!is_array($result) || empty($result)) {
            return false;
        }

        return !empty($result[0]['db_initialized_at']);
    }

    private static function isModuleMongoInitialized(string $moduleName): bool
    {
        $result = Database::execute(
            'SELECT mongo_initialized_at FROM module_registry WHERE module_name = ?',
            [$moduleName]
        );

        if (!is_array($result) || empty($result)) {
            return false;
        }

        return !empty($result[0]['mongo_initialized_at']);
    }

    private static function markModuleDbInitialized(string $moduleName, string $version): void
    {
        Database::execute(
            'INSERT INTO module_registry (module_name, module_version, db_initialized_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE module_version = VALUES(module_version), db_initialized_at = NOW()',
            [$moduleName, $version]
        );
    }

    private static function markModuleMongoInitialized(string $moduleName, string $version): void
    {
        Database::execute(
            'INSERT INTO module_registry (module_name, module_version, mongo_initialized_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE module_version = VALUES(module_version), mongo_initialized_at = NOW()',
            [$moduleName, $version]
        );
    }

    private static function resolveDatabaseFiles(string $moduleName, string $modulePath, array $dbConfig): array
    {
        $tables = [];
        $storedProcedures = [];
        $basePath = dirname($modulePath, 2);

        if (!empty($dbConfig['tables']) && is_array($dbConfig['tables'])) {
            $tables = array_values($dbConfig['tables']);
        }

        if (!empty($dbConfig['stored_procedures'])) {
            $storedProcedures = self::resolveSqlFiles($dbConfig['stored_procedures']);
        }

        if (empty($tables) && empty($storedProcedures)) {
            $snake = self::toSnakeCase($moduleName);
            $defaultTable = $basePath . '/database/mysql/tables/' . $snake . '.sql';
            $defaultSpDir = $basePath . '/database/mysql/stored_procedures/' . $snake;

            if (file_exists($defaultTable)) {
                $tables[] = $defaultTable;
            }

            if (is_dir($defaultSpDir)) {
                $storedProcedures = array_merge($storedProcedures, self::resolveSqlFiles($defaultSpDir));
            }
        }

        return [
            'tables' => $tables,
            'stored_procedures' => $storedProcedures,
        ];
    }

    private static function resolveSqlFiles(string|array $pathOrList): array
    {
        if (is_array($pathOrList)) {
            return array_values(array_filter($pathOrList, 'file_exists'));
        }

        if (!is_dir($pathOrList)) {
            return file_exists($pathOrList) ? [$pathOrList] : [];
        }

        $files = glob(rtrim($pathOrList, '/\\') . '/*.sql') ?: [];
        sort($files);
        return $files;
    }

    private static function executeSqlFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $statements = self::parseSqlStatements($content);

        foreach ($statements as $sql) {
            if (!empty(trim($sql))) {
                if (preg_match('/^\s*SOURCE\s+(.+);?\s*$/i', $sql, $matches)) {
                    self::executeSqlFile(self::resolveSourcePath($filePath, $matches[1]));
                } else {
                    Database::execute($sql);
                }
            }
        }
    }

    private static function parseSqlStatements(string $content): array
    {
        $content = preg_replace('/--.*$/m', '', $content);
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);

        $statements = [];
        $delimiter = ';';
        $current = '';

        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
                $delimiter = trim($matches[1]);
                continue;
            }

            if (preg_match('/^\s*SOURCE\s+/i', $trimmed)) {
                if ($current !== '') {
                    $statements[] = $current;
                    $current = '';
                }
                $statements[] = $trimmed;
                continue;
            }

            $current .= $line . "\n";
            if (str_ends_with($trimmed, $delimiter)) {
                $stmt = rtrim($current, $delimiter . "\n\r\t ");
                if (trim($stmt) !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }

    private static function resolveSourcePath(string $baseFile, string $sourcePath): string
    {
        $sourcePath = trim($sourcePath, "'\" ");
        if (str_starts_with($sourcePath, '/') || preg_match('/^[A-Za-z]:\\\\/', $sourcePath)) {
            return $sourcePath;
        }

        return dirname($baseFile) . DIRECTORY_SEPARATOR . $sourcePath;
    }

    private static function toSnakeCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);
        return strtolower($value ?? '');
    }

    private static function getMongoClient(): ?MongoClient
    {
        try {
            $host = $_ENV['MONGO_HOST'] ?? 'mongodb';
            $port = $_ENV['MONGO_PORT'] ?? '27017';
            $username = $_ENV['MONGO_ROOT_USER'] ?? '';
            $password = $_ENV['MONGO_ROOT_PASSWORD'] ?? '';

            if (!empty($username) && !empty($password)) {
                $uri = "mongodb://{$username}:{$password}@{$host}:{$port}/?authSource=admin";
            } else {
                $uri = "mongodb://{$host}:{$port}";
            }

            return new MongoClient($uri, [
                'connectTimeoutMS' => 5000,
                'serverSelectionTimeoutMS' => 5000
            ]);
        } catch (\Exception $e) {
            Logger::error('MongoDB connection failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
