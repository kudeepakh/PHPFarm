<?php

declare(strict_types=1);

namespace App\Core\Data;

use PHPFrarm\Core\Database\MySQLConnection;
use PHPFrarm\Core\Logger;

/**
 * Schema Version Manager
 * 
 * Manages database schema versions for the Data Standards module.
 * Provides schema versioning, evolution tracking, and compatibility checks.
 * 
 * @package PHPFrarm
 * @module Data Standards (Module 13)
 */
class SchemaVersionManager
{
    private MySQLConnection $db;
    private Logger $logger;
    private string $versionTable = 'schema_versions';
    
    /**
     * Schema version history
     */
    private array $versions = [];
    
    /**
     * Current schema version
     */
    private ?string $currentVersion = null;

    public function __construct(MySQLConnection $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->ensureVersionTable();
    }

    /**
     * Ensure the schema versions tracking table exists
     */
    private function ensureVersionTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->versionTable} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(50) NOT NULL,
                description TEXT,
                module VARCHAR(100) NOT NULL,
                migration_file VARCHAR(255),
                checksum VARCHAR(64),
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                applied_by VARCHAR(100),
                execution_time_ms INT UNSIGNED,
                is_rolled_back BOOLEAN DEFAULT FALSE,
                rolled_back_at TIMESTAMP NULL,
                INDEX idx_version (version),
                INDEX idx_module (module),
                INDEX idx_applied_at (applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->db->execute($sql);
    }

    /**
     * Get current schema version for a module
     */
    public function getCurrentVersion(string $module = 'core'): ?string
    {
        $result = $this->db->query(
            "SELECT version FROM {$this->versionTable} 
             WHERE module = :module AND is_rolled_back = FALSE 
             ORDER BY applied_at DESC LIMIT 1",
            ['module' => $module]
        );
        
        return $result[0]['version'] ?? null;
    }

    /**
     * Get all applied versions for a module
     */
    public function getAppliedVersions(string $module = 'core'): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->versionTable} 
             WHERE module = :module AND is_rolled_back = FALSE 
             ORDER BY applied_at ASC",
            ['module' => $module]
        );
    }

    /**
     * Get pending migrations that haven't been applied
     */
    public function getPendingMigrations(string $module, array $availableMigrations): array
    {
        $applied = array_column($this->getAppliedVersions($module), 'version');
        
        return array_filter($availableMigrations, function ($migration) use ($applied) {
            return !in_array($migration['version'], $applied);
        });
    }

    /**
     * Record a schema version
     */
    public function recordVersion(
        string $version,
        string $module,
        string $description = '',
        ?string $migrationFile = null,
        ?int $executionTimeMs = null
    ): bool {
        $checksum = $migrationFile ? $this->calculateChecksum($migrationFile) : null;
        
        $result = $this->db->execute(
            "INSERT INTO {$this->versionTable} 
             (version, module, description, migration_file, checksum, applied_by, execution_time_ms)
             VALUES (:version, :module, :description, :file, :checksum, :user, :time)",
            [
                'version' => $version,
                'module' => $module,
                'description' => $description,
                'file' => $migrationFile,
                'checksum' => $checksum,
                'user' => $_ENV['USER'] ?? 'system',
                'time' => $executionTimeMs
            ]
        );
        
        $this->logger->info('Schema version recorded', [
            'version' => $version,
            'module' => $module,
            'description' => $description
        ]);
        
        return $result > 0;
    }

    /**
     * Mark a version as rolled back
     */
    public function markRolledBack(string $version, string $module): bool
    {
        $result = $this->db->execute(
            "UPDATE {$this->versionTable} 
             SET is_rolled_back = TRUE, rolled_back_at = NOW()
             WHERE version = :version AND module = :module",
            ['version' => $version, 'module' => $module]
        );
        
        $this->logger->warning('Schema version rolled back', [
            'version' => $version,
            'module' => $module
        ]);
        
        return $result > 0;
    }

    /**
     * Check if a version has been applied
     */
    public function isVersionApplied(string $version, string $module): bool
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM {$this->versionTable} 
             WHERE version = :version AND module = :module AND is_rolled_back = FALSE",
            ['version' => $version, 'module' => $module]
        );
        
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Calculate file checksum for integrity verification
     */
    public function calculateChecksum(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }
        
        return hash('sha256', file_get_contents($filePath));
    }

    /**
     * Verify migration integrity (checksums match)
     */
    public function verifyIntegrity(string $module): array
    {
        $issues = [];
        $versions = $this->getAppliedVersions($module);
        
        foreach ($versions as $version) {
            if (!$version['migration_file'] || !$version['checksum']) {
                continue;
            }
            
            if (!file_exists($version['migration_file'])) {
                $issues[] = [
                    'version' => $version['version'],
                    'issue' => 'Migration file missing',
                    'file' => $version['migration_file']
                ];
                continue;
            }
            
            $currentChecksum = $this->calculateChecksum($version['migration_file']);
            if ($currentChecksum !== $version['checksum']) {
                $issues[] = [
                    'version' => $version['version'],
                    'issue' => 'Checksum mismatch - migration file modified after application',
                    'file' => $version['migration_file'],
                    'expected' => $version['checksum'],
                    'actual' => $currentChecksum
                ];
            }
        }
        
        return $issues;
    }

    /**
     * Get schema version history with full details
     */
    public function getVersionHistory(string $module, int $limit = 50): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->versionTable} 
             WHERE module = :module 
             ORDER BY applied_at DESC 
             LIMIT :limit",
            ['module' => $module, 'limit' => $limit]
        );
    }

    /**
     * Generate version string from timestamp
     */
    public function generateVersionString(): string
    {
        return date('Y_m_d_His');
    }

    /**
     * Parse version string to timestamp
     */
    public function parseVersionString(string $version): ?\DateTimeImmutable
    {
        try {
            return \DateTimeImmutable::createFromFormat('Y_m_d_His', $version);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Compare two versions
     * Returns: -1 if v1 < v2, 0 if equal, 1 if v1 > v2
     */
    public function compareVersions(string $v1, string $v2): int
    {
        return strcmp($v1, $v2);
    }

    /**
     * Get database schema info
     */
    public function getDatabaseSchemaInfo(): array
    {
        $tables = $this->db->query("SHOW TABLES");
        $info = [
            'tables' => [],
            'total_tables' => 0,
            'total_rows' => 0
        ];
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $status = $this->db->query("SHOW TABLE STATUS LIKE :table", ['table' => $tableName]);
            
            if (!empty($status)) {
                $info['tables'][$tableName] = [
                    'engine' => $status[0]['Engine'] ?? 'unknown',
                    'rows' => (int) ($status[0]['Rows'] ?? 0),
                    'data_length' => (int) ($status[0]['Data_length'] ?? 0),
                    'index_length' => (int) ($status[0]['Index_length'] ?? 0),
                    'created' => $status[0]['Create_time'] ?? null,
                    'updated' => $status[0]['Update_time'] ?? null
                ];
                $info['total_rows'] += (int) ($status[0]['Rows'] ?? 0);
            }
        }
        
        $info['total_tables'] = count($info['tables']);
        
        return $info;
    }

    /**
     * Export schema to SQL
     */
    public function exportSchema(string $outputPath): bool
    {
        $tables = $this->db->query("SHOW TABLES");
        $schema = "-- PHPFrarm Schema Export\n";
        $schema .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $createTable = $this->db->query("SHOW CREATE TABLE `{$tableName}`");
            
            if (!empty($createTable)) {
                $schema .= "-- Table: {$tableName}\n";
                $schema .= $createTable[0]['Create Table'] ?? '';
                $schema .= ";\n\n";
            }
        }
        
        // Export stored procedures
        $procedures = $this->db->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
        foreach ($procedures as $proc) {
            $procName = $proc['Name'];
            $createProc = $this->db->query("SHOW CREATE PROCEDURE `{$procName}`");
            
            if (!empty($createProc)) {
                $schema .= "-- Procedure: {$procName}\n";
                $schema .= "DELIMITER //\n";
                $schema .= ($createProc[0]['Create Procedure'] ?? '') . "//\n";
                $schema .= "DELIMITER ;\n\n";
            }
        }
        
        return file_put_contents($outputPath, $schema) !== false;
    }

    /**
     * Create a schema snapshot
     */
    public function createSnapshot(string $snapshotName): array
    {
        $snapshot = [
            'name' => $snapshotName,
            'created_at' => date('Y-m-d H:i:s'),
            'schema_info' => $this->getDatabaseSchemaInfo(),
            'versions' => []
        ];
        
        // Get versions for all modules
        $modules = $this->db->query(
            "SELECT DISTINCT module FROM {$this->versionTable} WHERE is_rolled_back = FALSE"
        );
        
        foreach ($modules as $module) {
            $moduleName = $module['module'];
            $snapshot['versions'][$moduleName] = $this->getCurrentVersion($moduleName);
        }
        
        $snapshotPath = __DIR__ . "/../../../database/snapshots/{$snapshotName}.json";
        $dir = dirname($snapshotPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT));
        
        $this->logger->info('Schema snapshot created', ['name' => $snapshotName]);
        
        return $snapshot;
    }

    /**
     * Restore from snapshot (verification only - shows diff)
     */
    public function compareWithSnapshot(string $snapshotName): array
    {
        $snapshotPath = __DIR__ . "/../../../database/snapshots/{$snapshotName}.json";
        
        if (!file_exists($snapshotPath)) {
            return ['error' => 'Snapshot not found'];
        }
        
        $snapshot = json_decode(file_get_contents($snapshotPath), true);
        $currentInfo = $this->getDatabaseSchemaInfo();
        
        $diff = [
            'snapshot_name' => $snapshotName,
            'snapshot_date' => $snapshot['created_at'],
            'tables_added' => [],
            'tables_removed' => [],
            'tables_modified' => [],
            'version_changes' => []
        ];
        
        $snapshotTables = array_keys($snapshot['schema_info']['tables'] ?? []);
        $currentTables = array_keys($currentInfo['tables']);
        
        $diff['tables_added'] = array_diff($currentTables, $snapshotTables);
        $diff['tables_removed'] = array_diff($snapshotTables, $currentTables);
        
        // Check version changes
        foreach ($snapshot['versions'] as $module => $version) {
            $currentVersion = $this->getCurrentVersion($module);
            if ($currentVersion !== $version) {
                $diff['version_changes'][$module] = [
                    'snapshot' => $version,
                    'current' => $currentVersion
                ];
            }
        }
        
        return $diff;
    }
}
