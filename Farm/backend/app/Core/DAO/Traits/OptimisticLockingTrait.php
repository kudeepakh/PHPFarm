<?php

namespace PHPFrarm\Core\DAO\Traits;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\Exceptions\HttpExceptions\ConflictHttpException;

/**
 * Optimistic Locking Trait
 * 
 * Provides optimistic locking functionality to prevent lost updates in concurrent scenarios.
 * Uses version numbers to detect conflicts and returns 409 Conflict when version mismatches occur.
 * 
 * Usage:
 * 1. Use this trait in your DAO class
 * 2. Ensure your table has a 'version' column (INT, DEFAULT 1)
 * 3. Call updateWithOptimisticLocking() for any update operations
 * 4. Handle ConflictHttpException for version conflicts
 */
trait OptimisticLockingTrait
{
    /**
     * Update record with optimistic locking
     * 
     * @param string $id Record identifier
     * @param int $expectedVersion Expected version number
     * @param array $data Data to update
     * @param string|null $tableName Optional table name override
     * @return array Update result with new version
     * @throws ConflictHttpException When version conflict occurs
     */
    public function updateWithOptimisticLocking(
        string $id, 
        int $expectedVersion, 
        array $data, 
        ?string $tableName = null
    ): array {
        $correlationId = TraceContext::getCorrelationId();
        $actualTableName = $tableName ?? $this->tableName ?? $this->getTableName();
        
        try {
            Logger::info('Optimistic locking update initiated', [
                'table' => $actualTableName,
                'id' => $id,
                'expected_version' => $expectedVersion,
                'correlation_id' => $correlationId
            ]);
            
            // Determine the appropriate stored procedure based on table
            $procedureName = $this->getOptimisticLockingProcedure($actualTableName);
            
            // Prepare parameters based on table type
            $params = $this->prepareOptimisticLockingParams($id, $expectedVersion, $data, $correlationId);
            
            // Call the stored procedure
            $result = Database::callProcedure($procedureName, $params);
            
            if (empty($result) || !isset($result[0]['success'])) {
                throw new \Exception('Invalid response from optimistic locking procedure');
            }
            
            $response = $result[0];
            
            // Handle version conflict
            if (!$response['success']) {
                $message = $response['message'] ?? 'Update failed';
                
                if (strpos($message, 'Version conflict') !== false) {
                    Logger::warning('Optimistic locking conflict detected', [
                        'table' => $actualTableName,
                        'id' => $id,
                        'expected_version' => $expectedVersion,
                        'current_version' => $response['current_version'] ?? 'unknown',
                        'correlation_id' => $correlationId
                    ]);
                    
                    throw new ConflictHttpException(
                        $message . '. Please refresh and try again.',
                        [
                            'expected_version' => $expectedVersion,
                            'current_version' => $response['current_version'] ?? null,
                            'conflict_type' => 'version_mismatch'
                        ]
                    );
                }
                
                // Handle other types of failures
                throw new \Exception($message);
            }
            
            Logger::info('Optimistic locking update successful', [
                'table' => $actualTableName,
                'id' => $id,
                'previous_version' => $expectedVersion,
                'new_version' => $response['new_version'],
                'correlation_id' => $correlationId
            ]);
            
            return [
                'success' => true,
                'message' => $response['message'],
                'previous_version' => $expectedVersion,
                'new_version' => $response['new_version'],
                'affected_rows' => $response['affected_rows'] ?? 1
            ];
            
        } catch (ConflictHttpException $e) {
            // Re-throw conflict exceptions
            throw $e;
        } catch (\Exception $e) {
            Logger::error('Optimistic locking update failed', [
                'table' => $actualTableName,
                'id' => $id,
                'expected_version' => $expectedVersion,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            throw new \Exception('Update with optimistic locking failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get current version of a record
     * 
     * @param string $id Record identifier
     * @param string|null $tableName Optional table name override
     * @return array Version information
     */
    public function getCurrentVersion(string $id, ?string $tableName = null): array
    {
        $correlationId = TraceContext::getCorrelationId();
        $actualTableName = $tableName ?? $this->tableName ?? $this->getTableName();
        
        try {
            $result = Database::callProcedure('sp_get_record_version', [
                $actualTableName,
                $id,
                $correlationId
            ]);
            
            if (empty($result)) {
                return ['found' => false, 'version' => 0];
            }
            
            return [
                'found' => (bool)$result[0]['found'],
                'version' => (int)$result[0]['version'],
                'table_name' => $result[0]['table_name'],
                'record_id' => $result[0]['record_id']
            ];
            
        } catch (\Exception $e) {
            Logger::error('Get current version failed', [
                'table' => $actualTableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId
            ]);
            
            return ['found' => false, 'version' => 0];
        }
    }
    
    /**
     * Validate version before update (optional pre-check)
     * 
     * @param string $id Record identifier
     * @param int $expectedVersion Expected version
     * @param string|null $tableName Optional table name override
     * @return bool True if version matches
     * @throws ConflictHttpException When version mismatch occurs
     */
    public function validateVersion(string $id, int $expectedVersion, ?string $tableName = null): bool
    {
        $versionInfo = $this->getCurrentVersion($id, $tableName);
        
        if (!$versionInfo['found']) {
            throw new \Exception('Record not found');
        }
        
        if ($versionInfo['version'] !== $expectedVersion) {
            throw new ConflictHttpException(
                'Record has been modified by another process. Please refresh and try again.',
                [
                    'expected_version' => $expectedVersion,
                    'current_version' => $versionInfo['version'],
                    'conflict_type' => 'version_mismatch'
                ]
            );
        }
        
        return true;
    }
    
    /**
     * Get the appropriate stored procedure name for optimistic locking
     * 
     * @param string $tableName Table name
     * @return string Stored procedure name
     */
    protected function getOptimisticLockingProcedure(string $tableName): string
    {
        switch ($tableName) {
            case 'users':
                return 'sp_update_user_with_locking';
            case 'roles':
                return 'sp_update_role_with_locking';
            case 'permissions':
                return 'sp_update_permission_with_locking';
            default:
                throw new \Exception("Optimistic locking not supported for table: {$tableName}");
        }
    }
    
    /**
     * Prepare parameters for optimistic locking stored procedures
     * 
     * @param string $id Record ID
     * @param int $expectedVersion Expected version
     * @param array $data Update data
     * @param string $correlationId Correlation ID
     * @return array Prepared parameters
     */
    protected function prepareOptimisticLockingParams(
        string $id, 
        int $expectedVersion, 
        array $data, 
        string $correlationId
    ): array {
        $tableName = $this->tableName ?? $this->getTableName();
        
        switch ($tableName) {
            case 'users':
                return [
                    $id,
                    $expectedVersion,
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
                    $data['phone'] ?? null,
                    $data['status'] ?? null,
                    $correlationId
                ];
            case 'roles':
                return [
                    $id,
                    $expectedVersion,
                    $data['name'] ?? null,
                    $data['description'] ?? null,
                    $data['priority'] ?? null,
                    $correlationId
                ];
            case 'permissions':
                return [
                    $id,
                    $expectedVersion,
                    $data['description'] ?? null,
                    $correlationId
                ];
            default:
                throw new \Exception("Parameter preparation not implemented for table: {$tableName}");
        }
    }
    
    /**
     * Get table name - abstract method to be implemented by classes using this trait
     * 
     * @return string Table name
     */
    abstract protected function getTableName(): string;
}