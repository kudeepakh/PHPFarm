<?php

namespace PHPFrarm\Core\Traits;

/**
 * Soft Delete Trait
 * 
 * Provides soft delete functionality for models
 * Adds deleted_at timestamp instead of actually deleting records
 * 
 * Database Requirements:
 * - deleted_at TIMESTAMP NULL DEFAULT NULL
 * 
 * Usage in DAO:
 * ```php
 * class UserDAO {
 *     use SoftDelete;
 *     
 *     protected string $table = 'users';
 * }
 * ```
 */
trait SoftDelete
{
    /**
     * Soft delete a record
     */
    public function softDelete(string $id): bool
    {
        $deletedAt = gmdate('Y-m-d H:i:s');
        
        $result = $this->db->callProcedure('sp_soft_delete', [
            'p_table' => $this->table,
            'p_id' => $id,
            'p_deleted_at' => $deletedAt
        ]);
        
        Logger::info('Record soft deleted', [
            'table' => $this->table,
            'id' => $id
        ]);
        
        return !empty($result);
    }
    
    /**
     * Restore a soft-deleted record
     */
    public function restore(string $id): bool
    {
        $result = $this->db->callProcedure('sp_restore_deleted', [
            'p_table' => $this->table,
            'p_id' => $id
        ]);
        
        Logger::info('Record restored', [
            'table' => $this->table,
            'id' => $id
        ]);
        
        return !empty($result);
    }
    
    /**
     * Permanently delete a record
     */
    public function forceDelete(string $id): bool
    {
        $result = $this->db->callProcedure('sp_force_delete', [
            'p_table' => $this->table,
            'p_id' => $id
        ]);
        
        Logger::warning('Record permanently deleted', [
            'table' => $this->table,
            'id' => $id
        ]);
        
        return !empty($result);
    }
    
    /**
     * Check if record is soft deleted
     */
    public function isDeleted(string $id): bool
    {
        $result = $this->db->callProcedure('sp_is_deleted', [
            'p_table' => $this->table,
            'p_id' => $id
        ]);
        
        return !empty($result) && $result[0]['is_deleted'] == 1;
    }
    
    /**
     * Get only soft deleted records
     */
    public function onlyTrashed(): array
    {
        return $this->db->callProcedure('sp_get_trashed', [
            'p_table' => $this->table
        ]);
    }
    
    /**
     * Get all records including soft deleted
     */
    public function withTrashed(): array
    {
        return $this->db->callProcedure('sp_get_with_trashed', [
            'p_table' => $this->table
        ]);
    }
}
