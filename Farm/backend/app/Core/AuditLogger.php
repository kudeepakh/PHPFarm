<?php

namespace PHPFrarm\Core;

use MongoDB\Client;
use MongoDB\Database as MongoDatabase;
use MongoDB\Collection;

/**
 * AuditLogger - Comprehensive audit trail implementation
 * 
 * MANDATORY for compliance: Tracks all user actions, data changes, and security events
 * Separate from application logging for compliance and security audit requirements
 * 
 * Features:
 * - User action tracking with before/after values
 * - PII masking for sensitive fields
 * - Immutable audit records
 * - Retention policy support
 * - Compliance-ready format
 */
class AuditLogger
{
    private static ?Collection $collection = null;
    private static bool $enabled = true;
    private static array $piiFields = ['password', 'ssn', 'credit_card', 'api_key', 'secret', 'token'];
    
    /**
     * Initialize MongoDB audit collection
     */
    private static function getCollection(): ?Collection
    {
        if (!self::$enabled) {
            return null;
        }
        
        if (self::$collection === null) {
            try {
                $host = $_ENV['MONGO_HOST'] ?? 'mongodb';
                $port = $_ENV['MONGO_PORT'] ?? '27017';
                $username = $_ENV['MONGO_ROOT_USER'] ?? 'admin';
                $password = $_ENV['MONGO_ROOT_PASSWORD'] ?? '';
                $database = $_ENV['MONGO_DATABASE'] ?? 'phpfrarm_logs';
                
                if (!empty($username) && !empty($password)) {
                    $uri = "mongodb://{$username}:{$password}@{$host}:{$port}/?authSource=admin";
                } else {
                    $uri = "mongodb://{$host}:{$port}";
                }
                
                $client = new Client($uri);
                $db = $client->selectDatabase($database);
                self::$collection = $db->selectCollection('audit_logs');
                
                // Create indexes for audit logs
                self::createIndexes();
                
            } catch (\Exception $e) {
                error_log('[AuditLogger] MongoDB connection failed: ' . $e->getMessage());
                self::$enabled = false;
                return null;
            }
        }
        
        return self::$collection;
    }
    
    /**
     * Create required indexes for audit logs
     */
    private static function createIndexes(): void
    {
        try {
            $collection = self::$collection;
            
            // Index on user_id for user activity queries
            $collection->createIndex(['user_id' => 1]);
            
            // Index on action for action-type queries
            $collection->createIndex(['action' => 1]);
            
            // Index on resource_type and resource_id for resource history
            $collection->createIndex(['resource_type' => 1, 'resource_id' => 1]);
            
            // Index on timestamp for time-based queries and retention
            $collection->createIndex(['timestamp' => -1]);
            
            // Compound index on correlation_id for tracing
            $collection->createIndex(['correlation_id' => 1]);
            
            // Index for security event queries
            $collection->createIndex(['is_security_event' => 1, 'timestamp' => -1]);
            
            // TTL index for automatic retention (configurable)
            $retentionDays = (int)($_ENV['AUDIT_RETENTION_DAYS'] ?? 2555); // 7 years default
            $collection->createIndex(
                ['timestamp' => 1],
                ['expireAfterSeconds' => $retentionDays * 86400]
            );
            
        } catch (\Exception $e) {
            error_log('[AuditLogger] Index creation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Log user action with data changes
     * 
     * @param string $action Action performed (e.g., 'user.created', 'order.updated')
     * @param string $resourceType Resource type (e.g., 'user', 'order', 'payment')
     * @param string $resourceId Resource identifier
     * @param array|null $before Data before change (for updates/deletes)
     * @param array|null $after Data after change (for creates/updates)
     * @param array $metadata Additional metadata
     */
    public static function log(
        string $action,
        string $resourceType,
        string $resourceId,
        ?array $before = null,
        ?array $after = null,
        array $metadata = []
    ): void {
        $collection = self::getCollection();
        if ($collection === null) {
            // Fallback to file logging
            self::logToFile($action, $resourceType, $resourceId, $before, $after, $metadata);
            return;
        }
        
        try {
            $auditRecord = [
                // Identity
                'user_id' => self::getCurrentUserId(),
                'session_id' => self::getCurrentSessionId(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                
                // Action
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                
                // Data changes (with PII masking)
                'before' => $before ? self::maskPII($before) : null,
                'after' => $after ? self::maskPII($after) : null,
                'changes' => self::calculateChanges($before, $after),
                
                // Traceability
                'correlation_id' => TraceContext::getCorrelationId(),
                'transaction_id' => TraceContext::getTransactionId(),
                'request_id' => TraceContext::getRequestId(),
                
                // Timing
                'timestamp' => new \MongoDB\BSON\UTCDateTime(),
                
                // Security classification
                'is_security_event' => self::isSecurityEvent($action),
                'risk_level' => self::calculateRiskLevel($action, $resourceType),
                
                // Additional metadata
                'metadata' => $metadata,
                
                // Immutability marker
                'immutable' => true,
                'audit_version' => '1.0'
            ];
            
            $collection->insertOne($auditRecord);
            
        } catch (\Exception $e) {
            error_log('[AuditLogger] Failed to write audit log: ' . $e->getMessage());
            // Fallback to file
            self::logToFile($action, $resourceType, $resourceId, $before, $after, $metadata);
        }
    }
    
    /**
     * Log authentication event
     */
    public static function logAuth(string $event, string $userId, array $metadata = []): void
    {
        self::log(
            "auth.{$event}",
            'user',
            $userId,
            null,
            null,
            array_merge($metadata, ['category' => 'authentication'])
        );
    }
    
    /**
     * Log authorization event
     */
    public static function logAuthz(string $event, string $userId, string $resource, array $metadata = []): void
    {
        self::log(
            "authz.{$event}",
            'authorization',
            $userId,
            null,
            ['resource' => $resource],
            array_merge($metadata, ['category' => 'authorization'])
        );
    }
    
    /**
     * Log data access event
     */
    public static function logDataAccess(string $resourceType, string $resourceId, string $operation, array $metadata = []): void
    {
        self::log(
            "data.{$operation}",
            $resourceType,
            $resourceId,
            null,
            null,
            array_merge($metadata, ['category' => 'data_access'])
        );
    }
    
    /**
     * Log security event
     */
    public static function logSecurity(string $event, array $metadata = []): void
    {
        self::log(
            "security.{$event}",
            'security',
            self::getCurrentUserId() ?? 'anonymous',
            null,
            null,
            array_merge($metadata, ['category' => 'security', 'severity' => 'high'])
        );
    }
    
    /**
     * Mask PII fields in data
     */
    private static function maskPII(array $data): array
    {
        $masked = [];
        
        foreach ($data as $key => $value) {
            // Check if field should be masked
            $lowerKey = strtolower($key);
            $shouldMask = false;
            
            foreach (self::$piiFields as $piiField) {
                if (str_contains($lowerKey, $piiField)) {
                    $shouldMask = true;
                    break;
                }
            }
            
            if ($shouldMask) {
                $masked[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $masked[$key] = self::maskPII($value);
            } else {
                $masked[$key] = $value;
            }
        }
        
        return $masked;
    }
    
    /**
     * Calculate changes between before and after
     */
    private static function calculateChanges(?array $before, ?array $after): array
    {
        if ($before === null || $after === null) {
            return [];
        }
        
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
        
        foreach ($allKeys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;
            
            if ($beforeValue !== $afterValue) {
                $changes[$key] = [
                    'from' => $beforeValue,
                    'to' => $afterValue
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Get current user ID from request context
     */
    private static function getCurrentUserId(): ?string
    {
        // Try to get from CommonMiddleware auth context
        global $request;
        return $request['user']['user_id'] ?? $_SERVER['AUTH_USER_ID'] ?? null;
    }
    
    /**
     * Get current session ID from request context
     */
    private static function getCurrentSessionId(): ?string
    {
        global $request;
        return $request['session_id'] ?? $_SERVER['SESSION_ID'] ?? null;
    }
    
    /**
     * Check if action is a security event
     */
    private static function isSecurityEvent(string $action): bool
    {
        $securityActions = [
            'auth.', 'authz.', 'security.', 'admin.', 
            'permission.', 'role.', 'access_denied'
        ];
        
        foreach ($securityActions as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate risk level for action
     */
    private static function calculateRiskLevel(string $action, string $resourceType): string
    {
        // High risk actions
        $highRisk = ['delete', 'drop', 'admin', 'permission', 'role', 'security'];
        foreach ($highRisk as $term) {
            if (str_contains(strtolower($action), $term)) {
                return 'high';
            }
        }
        
        // Medium risk actions
        $mediumRisk = ['update', 'modify', 'change'];
        foreach ($mediumRisk as $term) {
            if (str_contains(strtolower($action), $term)) {
                return 'medium';
            }
        }
        
        return 'low';
    }
    
    /**
     * Fallback file logging when MongoDB is unavailable
     */
    private static function logToFile(
        string $action,
        string $resourceType,
        string $resourceId,
        ?array $before,
        ?array $after,
        array $metadata
    ): void {
        $logPath = $_ENV['LOG_PATH'] ?? '/var/www/html/logs/';
        $logFile = $logPath . 'audit_' . date('Y-m-d') . '.log';
        
        $record = json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'user_id' => self::getCurrentUserId(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $before ? self::maskPII($before) : null,
            'after' => $after ? self::maskPII($after) : null,
            'correlation_id' => TraceContext::getCorrelationId(),
            'metadata' => $metadata
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        error_log("[AUDIT] {$record}\n", 3, $logFile);
    }
    
    /**
     * Query audit logs (for admin/compliance purposes)
     * 
     * @param array $filters Filters (user_id, action, resource_type, date_from, date_to)
     * @param int $limit Maximum records to return
     * @return array Audit records
     */
    public static function query(array $filters = [], int $limit = 100): array
    {
        $collection = self::getCollection();
        if ($collection === null) {
            return [];
        }
        
        try {
            $query = [];
            
            if (isset($filters['user_id'])) {
                $query['user_id'] = $filters['user_id'];
            }
            
            if (isset($filters['action'])) {
                $query['action'] = ['$regex' => $filters['action'], '$options' => 'i'];
            }
            
            if (isset($filters['resource_type'])) {
                $query['resource_type'] = $filters['resource_type'];
            }
            
            if (isset($filters['date_from']) || isset($filters['date_to'])) {
                $query['timestamp'] = [];
                if (isset($filters['date_from'])) {
                    $query['timestamp']['$gte'] = new \MongoDB\BSON\UTCDateTime(
                        strtotime($filters['date_from']) * 1000
                    );
                }
                if (isset($filters['date_to'])) {
                    $query['timestamp']['$lte'] = new \MongoDB\BSON\UTCDateTime(
                        strtotime($filters['date_to']) * 1000
                    );
                }
            }
            
            $results = $collection->find($query, [
                'limit' => $limit,
                'sort' => ['timestamp' => -1]
            ]);
            
            return iterator_to_array($results);
            
        } catch (\Exception $e) {
            error_log('[AuditLogger] Query failed: ' . $e->getMessage());
            return [];
        }
    }
}
