<?php

namespace PHPFrarm\Core\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;

/**
 * Base DAO class with universal soft delete support
 * 
 * This class provides common functionality for all DAOs including:
 * - Soft delete operations
 * - Restore operations
 * - Force delete operations
 * - Correlation ID tracking for all operations
 * 
 * Usage:
 * 1. Extend this class in your specific DAO
 * 2. Set the $tableName property
 * 3. Optionally override methods for custom behavior
 */
abstract class BaseDAO
{
    protected string $tableName;
    protected string $primaryKeyColumn = 'id';
    protected bool $supportsSoftDelete = true;
    protected string $deletedAtColumn = 'deleted_at';
    
    /**
     * Constructor - requires table name
     */
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }
    
    /**
     * Soft delete a record by ID
     * Updates the deleted_at column instead of physically deleting
     */
    public function softDelete(string $id): bool
    {
        if (!$this->supportsSoftDelete) {
            throw new \Exception("Soft delete not supported for table: {$this->tableName}");
        }
        
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            Logger::info('Soft delete initiated', [
                'table' => $this->tableName,
                'id' => $id,
                'correlation_id' => $correlationId
            ]);
            
            // Call stored procedure for soft delete
            $procedureName = "sp_soft_delete_{$this->tableName}";
            $result = Database::callProcedure($procedureName, [
                $id,
                $correlationId
            ]);
            
            if (!empty($result) && isset($result[0]['affected_rows']) && $result[0]['affected_rows'] > 0) {
                Logger::info('Soft delete completed', [
                    'table' => $this->tableName,
                    'id' => $id,
                    'affected_rows' => $result[0]['affected_rows'],
                    'correlation_id' => $correlationId
                ]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Logger::error('Soft delete failed', [
                'table' => $this->tableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return false;
        }
    }
    
    /**
     * Restore a soft-deleted record by ID
     * Sets deleted_at column back to NULL
     */
    public function restore(string $id): bool
    {
        if (!$this->supportsSoftDelete) {
            throw new \Exception("Restore not supported for table: {$this->tableName}");
        }
        
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            Logger::info('Restore initiated', [
                'table' => $this->tableName,
                'id' => $id,
                'correlation_id' => $correlationId
            ]);
            
            // Call stored procedure for restore
            $procedureName = "sp_restore_{$this->tableName}";
            $result = Database::callProcedure($procedureName, [
                $id,
                $correlationId
            ]);
            
            if (!empty($result) && isset($result[0]['affected_rows']) && $result[0]['affected_rows'] > 0) {
                Logger::info('Restore completed', [
                    'table' => $this->tableName,
                    'id' => $id,
                    'affected_rows' => $result[0]['affected_rows'],
                    'correlation_id' => $correlationId
                ]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Logger::error('Restore failed', [
                'table' => $this->tableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return false;
        }
    }
    
    /**
     * Force delete a record by ID (permanent deletion)
     * This physically removes the record from the database
     * Use with extreme caution!
     */
    public function forceDelete(string $id): bool
    {
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            Logger::warning('Force delete initiated', [
                'table' => $this->tableName,
                'id' => $id,
                'correlation_id' => $correlationId
            ]);
            
            // Call stored procedure for force delete
            $procedureName = "sp_force_delete_{$this->tableName}";
            $result = Database::callProcedure($procedureName, [
                $id,
                $correlationId
            ]);
            
            if (!empty($result) && isset($result[0]['affected_rows']) && $result[0]['affected_rows'] > 0) {
                Logger::warning('Force delete completed', [
                    'table' => $this->tableName,
                    'id' => $id,
                    'affected_rows' => $result[0]['affected_rows'],
                    'correlation_id' => $correlationId
                ]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Logger::error('Force delete failed', [
                'table' => $this->tableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return false;
        }
    }
    
    /**
     * Get all records including soft-deleted ones
     * Useful for admin interfaces
     */
    public function getAllWithDeleted(int $limit = 100, int $offset = 0): array
    {
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            // Call stored procedure to get all records including deleted
            $procedureName = "sp_get_all_{$this->tableName}_with_deleted";
            return Database::callProcedure($procedureName, [
                $limit,
                $offset,
                $correlationId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Get all with deleted failed', [
                'table' => $this->tableName,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return [];
        }
    }
    
    /**
     * Get only soft-deleted records
     * Useful for restore interfaces
     */
    public function getDeleted(int $limit = 100, int $offset = 0): array
    {
        if (!$this->supportsSoftDelete) {
            return [];
        }
        
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            // Call stored procedure to get only deleted records
            $procedureName = "sp_get_deleted_{$this->tableName}";
            return Database::callProcedure($procedureName, [
                $limit,
                $offset,
                $correlationId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Get deleted records failed', [
                'table' => $this->tableName,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return [];
        }
    }
    
    /**
     * Check if a record is soft-deleted
     */
    public function isDeleted(string $id): bool
    {
        if (!$this->supportsSoftDelete) {
            return false;
        }
        
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            // Call stored procedure to check if record is deleted
            $procedureName = "sp_is_deleted_{$this->tableName}";
            $result = Database::callProcedure($procedureName, [
                $id,
                $correlationId
            ]);
            
            return !empty($result) && ($result[0]['is_deleted'] ?? false) === true;
            
        } catch (\Exception $e) {
            Logger::error('Is deleted check failed', [
                'table' => $this->tableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return false;
        }
    }
    
    /**
     * Bulk soft delete multiple records
     */
    public function bulkSoftDelete(array $ids): int
    {
        if (!$this->supportsSoftDelete || empty($ids)) {
            return 0;
        }
        
        $correlationId = TraceContext::getCorrelationId();
        
        try {
            Logger::info('Bulk soft delete initiated', [
                'table' => $this->tableName,
                'count' => count($ids),
                'correlation_id' => $correlationId
            ]);
            
            // Convert IDs to comma-separated string for stored procedure
            $idsString = implode(',', array_map(function($id) {
                return "'" . addslashes($id) . "'";
            }, $ids));
            
            // Call stored procedure for bulk soft delete
            $procedureName = "sp_bulk_soft_delete_{$this->tableName}";
            $result = Database::callProcedure($procedureName, [
                $idsString,
                $correlationId
            ]);
            
            $affectedRows = $result[0]['affected_rows'] ?? 0;
            
            Logger::info('Bulk soft delete completed', [
                'table' => $this->tableName,
                'requested_count' => count($ids),
                'affected_rows' => $affectedRows,
                'correlation_id' => $correlationId
            ]);
            
            return (int)$affectedRows;
            
        } catch (\Exception $e) {
            Logger::error('Bulk soft delete failed', [
                'table' => $this->tableName,
                'count' => count($ids),
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return 0;
        }
    }
    
    /**
     * Configuration methods for child classes
     */
    protected function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }
    
    protected function setPrimaryKeyColumn(string $column): void
    {
        $this->primaryKeyColumn = $column;
    }
    
    protected function setSoftDeleteSupport(bool $enabled): void
    {
        $this->supportsSoftDelete = $enabled;
    }
    
    protected function setDeletedAtColumn(string $column): void
    {
        $this->deletedAtColumn = $column;
    }
}