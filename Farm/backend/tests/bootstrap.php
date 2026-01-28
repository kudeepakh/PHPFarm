<?php

/**
 * PHPUnit Test Bootstrap
 * 
 * Initializes the test environment:
 * - Loads environment variables
 * - Configures autoloading
 * - Initializes database
 * - Registers factories
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

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

// Load environment variables
$envFile = __DIR__ . '/../.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Set testing flag
$_ENV['TESTING'] = 'true';
putenv('TESTING=true');

// Disable external logging during tests
$_ENV['LOG_TO_MONGO'] = 'false';
putenv('LOG_TO_MONGO=false');

// Initialize database connection
use PHPFrarm\Core\Database;

// Resolve DB name (prefer MYSQL_DATABASE, fallback to DB_DATABASE)
$dbName = $_ENV['MYSQL_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? 'phpfrarm_test';
$_ENV['MYSQL_DATABASE'] = $dbName;
$_ENV['DB_DATABASE'] = $dbName;
putenv('MYSQL_DATABASE=' . $dbName);
putenv('DB_DATABASE=' . $dbName);

// Create test database if not exists
$host = $_ENV['MYSQL_HOST'] ?? 'mysql';
$port = $_ENV['MYSQL_PORT'] ?? '3306';
$username = $_ENV['MYSQL_USER'] ?? 'root';
$password = $_ENV['MYSQL_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
} catch (\Exception $e) {
    // Database already exists or cannot create
}

// Run schema bootstrap (tables + stored procedures)
Database::enableRawQueries();

function safeExecute(string $statement): void
{
    $statementTrim = '';
    try {
        $statementTrim = ltrim($statement, "\xEF\xBB\xBF \t\n\r\0\x0B");
        $statementTrim = preg_replace('/^CREATE\s+PROCEDURE\s+IF\s+NOT\s+EXISTS/i', 'CREATE PROCEDURE', $statementTrim, 1);
        if (stripos($statementTrim, 'CREATE PROCEDURE') === 0) {
            Database::getConnection()->exec($statementTrim);
            return;
        }

        Database::execute($statement);
    } catch (\PDOException $e) {
        $errorInfo = $e->errorInfo ?? null;
        $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;

        if (in_array($driverCode, [1050, 1060, 1061, 1304], true)) {
            return;
        }

        if ($driverCode === 1227 && stripos($statementTrim, 'DROP PROCEDURE') === 0) {
            return;
        }

        throw $e;
    }
}

function executeSqlFile(string $path): void
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        return;
    }

    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $delimiter = ';';
    $buffer = '';

    foreach (explode("\n", $contents) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . "\n";

        $lineTrimmed = rtrim($line);
        if ($delimiter !== '' && str_ends_with($lineTrimmed, $delimiter)) {
            $statement = substr($buffer, 0, strrpos($buffer, $delimiter));
            $buffer = '';

            if (trim($statement) !== '') {
                safeExecute($statement);
            }
        }
    }

    if (trim($buffer) !== '') {
        safeExecute($buffer);
    }
}

function executeSqlDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'sql') {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_STRING);

    foreach ($files as $filePath) {
        executeSqlFile($filePath);
    }
}

$tablesDir = __DIR__ . '/../database/mysql/tables';
$proceduresDir = __DIR__ . '/../database/mysql/stored_procedures';
executeSqlDirectory($tablesDir);
executeSqlDirectory($proceduresDir);

function ensureColumnExists(string $dbName, string $table, string $column, string $definition): void
{
    $result = Database::execute(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$dbName, $table, $column]
    );

    if (empty($result)) {
        Database::execute("ALTER TABLE `{$table}` ADD COLUMN {$column} {$definition}");
    }
}

ensureColumnExists($dbName, 'users', 'token_version', 'INT DEFAULT 0');
ensureColumnExists($dbName, 'users', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
ensureColumnExists($dbName, 'user_sessions', 'refresh_token_hash', 'VARCHAR(255) NULL');
ensureColumnExists($dbName, 'user_sessions', 'refresh_expires_at', 'DATETIME NULL');
ensureColumnExists($dbName, 'user_sessions', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
ensureColumnExists($dbName, 'user_sessions', 'created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

Database::disableRawQueries();

// Register factories
use Farm\Backend\Tests\Factories\FactoryRegistry;
$registry = FactoryRegistry::getInstance();

// Register all factories
$factoryPath = __DIR__ . '/Factories';
if (is_dir($factoryPath)) {
    $files = glob($factoryPath . '/*Factory.php');
    foreach ($files as $file) {
        $className = 'Farm\\Backend\\Tests\\Factories\\' . basename($file, '.php');
        if (class_exists($className)) {
            $registry->register($className);
        }
    }
}

// Clear cache
use Farm\Backend\App\Core\Testing\TestHelper;
TestHelper::clearRedis();

echo "✅ Test environment initialized\n";
echo "📁 Database: $dbName\n";
echo "🏭 Factories registered: " . count($registry->all()) . "\n";
