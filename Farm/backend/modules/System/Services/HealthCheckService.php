<?php

namespace PHPFrarm\Modules\System\Services;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;
use MongoDB\Client as MongoClient;
use Redis;

/**
 * Health Check Service
 * 
 * Business logic for system health checks and readiness probes.
 * Extracted from HealthController to follow service layer pattern.
 * 
 * @package PHPFrarm\Modules\System\Services
 */
class HealthCheckService
{
    /**
     * Check if MySQL is healthy
     * 
     * @return bool True if MySQL is accessible
     */
    public function checkMySQL(): bool
    {
        try {
            $db = Database::getInstance();
            $result = $db->query('SELECT 1 as health_check');
            return !empty($result);
        } catch (\Exception $e) {
            Logger::warning('MySQL health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get detailed MySQL health information
     * 
     * @return array MySQL health details
     */
    public function checkMySQLDetailed(): array
    {
        try {
            $db = Database::getInstance();
            $result = $db->query('SELECT 1 as health_check');
            
            if (empty($result)) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Query returned empty result'
                ];
            }
            
            // Get additional stats
            $stats = $db->query("SHOW STATUS LIKE 'Threads_connected'");
            $uptime = $db->query("SHOW STATUS LIKE 'Uptime'");
            
            return [
                'status' => 'healthy',
                'connections' => $stats[0]['Value'] ?? 'unknown',
                'uptime_seconds' => $uptime[0]['Value'] ?? 'unknown'
            ];
        } catch (\Exception $e) {
            Logger::warning('MySQL detailed health check failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'unhealthy',
                'error' => 'Database connection failed'
            ];
        }
    }
    
    /**
     * Check if MongoDB is healthy
     * 
     * @return bool True if MongoDB is accessible
     */
    public function checkMongoDB(): bool
    {
        try {
            $host = env('MONGO_HOST', 'mongodb');
            $port = env('MONGO_PORT', '27017');
            $username = env('MONGO_ROOT_USER', 'admin');
            $password = env('MONGO_ROOT_PASSWORD', 'mongo_password_change_me');
            
            $connectionString = "mongodb://{$username}:{$password}@{$host}:{$port}/?authSource=admin";
            $client = new MongoClient($connectionString, [
                'connectTimeoutMS' => 3000,
                'serverSelectionTimeoutMS' => 3000
            ]);
            
            $client->listDatabases();
            return true;
        } catch (\Exception $e) {
            Logger::warning('MongoDB health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get detailed MongoDB health information
     * 
     * @return array MongoDB health details
     */
    public function checkMongoDBDetailed(): array
    {
        try {
            $host = env('MONGO_HOST', 'mongodb');
            $port = env('MONGO_PORT', '27017');
            $username = env('MONGO_ROOT_USER', 'admin');
            $password = env('MONGO_ROOT_PASSWORD', 'mongo_password_change_me');
            
            $connectionString = "mongodb://{$username}:{$password}@{$host}:{$port}/?authSource=admin";
            $client = new MongoClient($connectionString, [
                'connectTimeoutMS' => 3000,
                'serverSelectionTimeoutMS' => 3000
            ]);
            
            $databases = $client->listDatabases();
            $dbList = [];
            foreach ($databases as $db) {
                $dbList[] = $db->getName();
            }
            
            return [
                'status' => 'healthy',
                'databases' => $dbList,
                'database_count' => count($dbList)
            ];
        } catch (\Exception $e) {
            Logger::warning('MongoDB detailed health check failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'unhealthy',
                'error' => 'MongoDB connection failed'
            ];
        }
    }
    
    /**
     * Check if Redis is healthy
     * 
     * @return bool True if Redis is accessible
     */
    public function checkRedis(): bool
    {
        try {
            $redis = new Redis();
            $host = env('REDIS_HOST', 'redis');
            $port = (int)env('REDIS_PORT', 6379);
            
            if (!@$redis->connect($host, $port, 2.0)) {
                return false;
            }
            
            $redis->ping();
            $redis->close();
            return true;
        } catch (\Exception $e) {
            Logger::warning('Redis health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get detailed Redis health information
     * 
     * @return array Redis health details
     */
    public function checkRedisDetailed(): array
    {
        try {
            $redis = new Redis();
            $host = env('REDIS_HOST', 'redis');
            $port = (int)env('REDIS_PORT', 6379);
            
            if (!@$redis->connect($host, $port, 2.0)) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Connection failed'
                ];
            }
            
            $info = $redis->info();
            $redis->close();
            
            return [
                'status' => 'healthy',
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_days' => isset($info['uptime_in_days']) ? (int)$info['uptime_in_days'] : 'unknown',
                'connected_clients' => isset($info['connected_clients']) ? (int)$info['connected_clients'] : 'unknown',
                'used_memory_human' => $info['used_memory_human'] ?? 'unknown'
            ];
        } catch (\Exception $e) {
            Logger::warning('Redis detailed health check failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'unhealthy',
                'error' => 'Redis connection failed'
            ];
        }
    }
    
    /**
     * Check disk space
     * 
     * @return array Disk space information
     */
    public function checkDiskSpace(): array
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;
            $usedPercent = round(($used / $total) * 100, 2);
            
            return [
                'total' => $this->formatBytes($total),
                'used' => $this->formatBytes($used),
                'free' => $this->formatBytes($free),
                'used_percent' => $usedPercent,
                'healthy' => $usedPercent < 90
            ];
        } catch (\Exception $e) {
            Logger::warning('Disk space check failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'error' => 'Disk check failed',
                'healthy' => false
            ];
        }
    }
    
    /**
     * Check memory usage
     * 
     * @return array Memory usage information
     */
    public function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $usedPercent = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;
        
        return [
            'used' => $this->formatBytes($memoryUsage),
            'limit' => $memoryLimit > 0 ? $this->formatBytes($memoryLimit) : 'unlimited',
            'used_percent' => $usedPercent,
            'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'healthy' => $usedPercent < 90
        ];
    }
    
    /**
     * Get PHP memory limit in bytes
     * 
     * @return int Memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return -1; // Unlimited
        }
        
        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));
        
        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Number of bytes
     * @return string Formatted string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
