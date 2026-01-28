<?php

namespace Farm\Backend\App\Console\Commands;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * Migrate Database Command
 * 
 * CLI command to run database migrations:
 * - Run pending migrations
 * - Rollback migrations
 * - Check migration status
 * - Refresh database (rollback all + migrate)
 * 
 * Usage:
 * ```bash
 * php artisan migrate
 * php artisan migrate --status
 * php artisan migrate --rollback
 * php artisan migrate --rollback=3
 * php artisan migrate --refresh
 * php artisan migrate --seed
 * ```
 */
class MigrateCommand
{
    private string $migrationsPath;
    private string $tablesPath;
    private string $storedProceduresPath;
    private Database $db;
    
    public function __construct()
    {
        $this->migrationsPath = dirname(__DIR__, 3) . '/database/mysql/migrations';
        $this->tablesPath = dirname(__DIR__, 3) . '/database/mysql/tables';
        $this->storedProceduresPath = dirname(__DIR__, 3) . '/database/mysql/stored_procedures';
        $this->db = Database::getInstance();
    }
    
    /**
     * Execute command
     * 
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args = []): int
    {
        echo "🗄️  Database Migration Tool\n\n";
        
        Database::enableRawQueries();
        try {
            // Ensure migrations table exists
            $this->ensureMigrationsTable();
            
            // Parse arguments
            if ($this->hasOption($args, 'status')) {
                return $this->showStatus();
            }
            
            if ($this->hasOption($args, 'rollback')) {
                $steps = $this->getOptionValue($args, 'rollback') ?: 1;
                return $this->rollback((int) $steps);
            }
            
            if ($this->hasOption($args, 'refresh')) {
                return $this->refresh($args);
            }
            
            if ($this->hasOption($args, 'fresh')) {
                return $this->fresh($args);
            }
            
            // Default: run pending migrations
            return $this->migrate($args);
            
        } catch (\Exception $e) {
            echo "❌ Migration failed: {$e->getMessage()}\n";
            Logger::error('Migration failed', ['error' => $e->getMessage()]);
            return 1;
        } finally {
            Database::disableRawQueries();
        }
    }
    
    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT UNSIGNED NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        
        $this->db->execute($sql);
    }
    
    /**
     * Run pending migrations
     */
    private function migrate(array $args): int
    {
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            echo "✅ Nothing to migrate. Database is up to date.\n";
            return 0;
        }
        
        echo "Found " . count($pending) . " pending migration(s).\n\n";
        
        $batch = $this->getNextBatch();
        $migrated = 0;
        
        foreach ($pending as $migration) {
            echo "  → Migrating: {$migration}\n";
            
            try {
                $this->runMigration($migration, $batch);
                echo "    ✓ Migrated successfully\n";
                $migrated++;
            } catch (\Exception $e) {
                echo "    ✗ Failed: {$e->getMessage()}\n";
                Logger::error('Migration failed', [
                    'migration' => $migration,
                    'error' => $e->getMessage()
                ]);
                return 1;
            }
        }
        
        echo "\n✅ Migrated {$migrated} migration(s).\n";
        
        // Run seeders if requested
        if ($this->hasOption($args, 'seed')) {
            $this->seed();
        }
        
        return 0;
    }
    
    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        $all = $this->getAllMigrationFiles();
        $ran = $this->getRanMigrations();
        
        return array_diff($all, $ran);
    }
    
    /**
     * Get all migration files
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = glob($this->migrationsPath . '/*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file);
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Get already ran migrations
     */
    private function getRanMigrations(): array
    {
        $result = $this->db->execute("SELECT migration FROM migrations ORDER BY batch, migration");
        return array_column($result, 'migration');
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatch(): int
    {
        $result = $this->db->execute("SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations");
        return (int) ($result[0]['next_batch'] ?? 1);
    }
    
    /**
     * Run a single migration
     */
    private function runMigration(string $migration, int $batch): void
    {
        $path = $this->migrationsPath . '/' . $migration;
        
        if (!file_exists($path)) {
            throw new \RuntimeException("Migration file not found: {$migration}");
        }
        
        $content = file_get_contents($path);
        
        // Parse and execute SQL statements
        $statements = $this->parseSqlStatements($content);
        
        foreach ($statements as $sql) {
            if (!empty(trim($sql))) {
                // Handle SOURCE commands
                if (preg_match('/^\s*SOURCE\s+(.+);?\s*$/i', $sql, $matches)) {
                    $this->executeSourceFile($matches[1]);
                } else {
                    $this->db->execute($sql);
                }
            }
        }
        
        // Record migration
        $this->db->execute(
            "INSERT INTO migrations (migration, batch, executed_at) VALUES (?, ?, NOW())",
            [$migration, $batch]
        );
        
        Logger::info('Migration executed', ['migration' => $migration, 'batch' => $batch]);
    }
    
    /**
     * Parse SQL statements from content
     */
    private function parseSqlStatements(string $content): array
    {
        // Remove comments
        $content = preg_replace('/--.*$/m', '', $content);
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        
        // Handle DELIMITER changes for stored procedures
        $statements = [];
        $delimiter = ';';
        $currentStatement = '';
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip empty lines
            if (empty($trimmed)) {
                continue;
            }
            
            // Check for DELIMITER change
            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
                $delimiter = trim($matches[1]);
                continue;
            }
            
            // Check for SOURCE command
            if (preg_match('/^\s*SOURCE\s+/i', $trimmed)) {
                if (!empty($currentStatement)) {
                    $statements[] = $currentStatement;
                    $currentStatement = '';
                }
                $statements[] = $trimmed;
                continue;
            }
            
            $currentStatement .= $line . "\n";
            
            // Check if statement ends with delimiter
            if (str_ends_with($trimmed, $delimiter)) {
                $stmt = rtrim($currentStatement, $delimiter . "\n\r\t ");
                if (!empty(trim($stmt))) {
                    $statements[] = $stmt;
                }
                $currentStatement = '';
            }
        }
        
        // Add any remaining statement
        if (!empty(trim($currentStatement))) {
            $statements[] = $currentStatement;
        }
        
        return $statements;
    }
    
    /**
     * Execute SOURCE file
     */
    private function executeSourceFile(string $relativePath): void
    {
        // Resolve path relative to migrations directory
        $basePath = dirname($this->migrationsPath);
        $fullPath = realpath($basePath . '/' . trim($relativePath));
        
        if (!$fullPath || !file_exists($fullPath)) {
            // Try absolute path
            $fullPath = realpath($relativePath);
        }
        
        if (!$fullPath || !file_exists($fullPath)) {
            throw new \RuntimeException("SOURCE file not found: {$relativePath}");
        }
        
        echo "      Loading: {$relativePath}\n";
        
        $content = file_get_contents($fullPath);
        $statements = $this->parseSqlStatements($content);
        
        foreach ($statements as $sql) {
            if (!empty(trim($sql))) {
                $this->db->execute($sql);
            }
        }
    }
    
    /**
     * Show migration status
     */
    private function showStatus(): int
    {
        $all = $this->getAllMigrationFiles();
        $ran = $this->getRanMigrations();
        
        if (empty($all)) {
            echo "No migrations found.\n";
            return 0;
        }
        
        echo "Migration Status:\n";
        echo str_repeat('-', 70) . "\n";
        echo sprintf("%-50s %s\n", "Migration", "Status");
        echo str_repeat('-', 70) . "\n";
        
        foreach ($all as $migration) {
            $status = in_array($migration, $ran) ? '✓ Ran' : '○ Pending';
            echo sprintf("%-50s %s\n", $migration, $status);
        }
        
        echo str_repeat('-', 70) . "\n";
        echo sprintf("Total: %d | Ran: %d | Pending: %d\n", 
            count($all), 
            count($ran), 
            count($all) - count($ran)
        );
        
        return 0;
    }
    
    /**
     * Rollback migrations
     */
    private function rollback(int $steps = 1): int
    {
        $lastBatch = $this->getLastBatch();
        
        if (!$lastBatch) {
            echo "Nothing to rollback.\n";
            return 0;
        }
        
        $migrations = $this->getMigrationsForRollback($steps);
        
        if (empty($migrations)) {
            echo "Nothing to rollback.\n";
            return 0;
        }
        
        echo "Rolling back " . count($migrations) . " migration(s)...\n\n";
        
        foreach ($migrations as $migration) {
            echo "  → Rolling back: {$migration['migration']}\n";
            
            try {
                // Note: Actual rollback logic would need DOWN migration scripts
                $this->db->execute(
                    "DELETE FROM migrations WHERE migration = ?",
                    [$migration['migration']]
                );
                echo "    ✓ Rolled back\n";
            } catch (\Exception $e) {
                echo "    ✗ Failed: {$e->getMessage()}\n";
                return 1;
            }
        }
        
        echo "\n✅ Rolled back " . count($migrations) . " migration(s).\n";
        echo "⚠️  Note: You may need to manually drop tables/procedures.\n";
        
        return 0;
    }
    
    /**
     * Get last batch number
     */
    private function getLastBatch(): ?int
    {
        $result = $this->db->execute("SELECT MAX(batch) AS last_batch FROM migrations");
        return $result[0]['last_batch'] ?? null;
    }
    
    /**
     * Get migrations for rollback
     */
    private function getMigrationsForRollback(int $steps): array
    {
        return $this->db->execute(
            "SELECT migration, batch FROM migrations ORDER BY batch DESC, migration DESC LIMIT ?",
            [$steps]
        );
    }
    
    /**
     * Refresh database (rollback all + migrate)
     */
    private function refresh(array $args): int
    {
        echo "🔄 Refreshing database...\n\n";
        
        // Get all ran migrations
        $ran = $this->getRanMigrations();
        
        // Rollback all
        foreach (array_reverse($ran) as $migration) {
            echo "  → Rolling back: {$migration}\n";
            $this->db->execute("DELETE FROM migrations WHERE migration = ?", [$migration]);
        }
        
        // Re-run all migrations
        return $this->migrate($args);
    }
    
    /**
     * Fresh database (drop all + migrate)
     */
    private function fresh(array $args): int
    {
        echo "🔄 Fresh database install...\n\n";
        echo "⚠️  This will DROP all tables!\n\n";
        
        // Get all tables
        $tables = $this->db->execute("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            if ($tableName !== 'migrations') {
                echo "  → Dropping: {$tableName}\n";
                $this->db->execute("DROP TABLE IF EXISTS `{$tableName}`");
            }
        }
        
        // Drop procedures
        $procedures = $this->db->execute("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
        foreach ($procedures as $proc) {
            echo "  → Dropping procedure: {$proc['Name']}\n";
            $this->db->execute("DROP PROCEDURE IF EXISTS `{$proc['Name']}`");
        }
        
        // Clear migrations table
        $this->db->execute("TRUNCATE TABLE migrations");
        
        echo "\n";
        
        // Run fresh migrations
        return $this->migrate($args);
    }
    
    /**
     * Run database seeders
     */
    private function seed(): void
    {
        echo "\n🌱 Running seeders...\n";
        
        $seedersPath = dirname(__DIR__, 3) . '/database/seeders';
        
        if (!is_dir($seedersPath)) {
            echo "  No seeders directory found.\n";
            return;
        }
        
        $files = glob($seedersPath . '/*.php');
        
        foreach ($files as $file) {
            $seeder = require $file;
            
            if (is_callable($seeder)) {
                echo "  → Running: " . basename($file) . "\n";
                $seeder($this->db);
            }
        }
        
        echo "✅ Seeding complete.\n";
    }
    
    /**
     * Check if option exists
     */
    private function hasOption(array $args, string $option): bool
    {
        foreach ($args as $arg) {
            if ($arg === "--{$option}" || str_starts_with($arg, "--{$option}=")) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get option value
     */
    private function getOptionValue(array $args, string $option): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$option}=")) {
                return substr($arg, strlen("--{$option}="));
            }
        }
        return null;
    }
}
