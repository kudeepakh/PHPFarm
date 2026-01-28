<?php

namespace PHPFrarm\Modules\System\Services;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * System Metrics Service
 * 
 * Handles all system health and statistics collection.
 * Extracted from SystemController to follow SRP and testability.
 */
class SystemMetricsService
{
    /**
     * Check database health
     */
    public function checkDatabaseHealth(): array
    {
        return [
            'mysql' => $this->checkMySQL(),
            'mongodb' => $this->checkMongoDB(),
            'redis' => $this->checkRedis(),
        ];
    }

    /**
     * Get system health metrics
     */
    public function getHealthMetrics(): array
    {
        $startTime = microtime(true);

        $dbHealth = $this->checkDatabaseHealth();
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        $metrics = [
            'uptime' => $this->getUptime(),
            'response_time' => $responseTime,
            'cpu_usage' => $this->getCPUUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'storage_usage' => $this->getStorageUsage(),
            'error_rate' => $this->getErrorRate(),
            'active_connections' => $this->getActiveConnections(),
        ];

        // Calculate status
        $status = 'healthy';
        if (!$dbHealth['mysql'] || !$dbHealth['mongodb'] || !$dbHealth['redis']) {
            $status = 'degraded';
        }
        if ($metrics['memory_usage'] > 90 || $metrics['cpu_usage'] > 90 || $metrics['storage_usage'] > 90) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'databases' => $dbHealth,
            'metrics' => $metrics,
            'last_check' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Get system statistics
     */
    public function getSystemStats(): array
    {
        return [
            'total_users' => $this->getTotalUsers(),
            'active_sessions' => $this->getActiveSessions(),
            'api_calls_today' => $this->getAPICallsToday(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'job_stats' => $this->getJobStats(),
        ];
    }

    // Private helper methods

    private function checkMySQL(): bool
    {
        try {
            Database::enableRawQueries();
            Database::execute("SELECT 1");
            Database::disableRawQueries();
            return true;
        } catch (\Exception $e) {
            Database::disableRawQueries();
            Logger::warning('MySQL health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function checkMongoDB(): bool
    {
        try {
            if (!class_exists('\MongoDB\Client')) {
                return false;
            }
            $mongo = new \MongoDB\Client($_ENV['MONGODB_URI'] ?? 'mongodb://mongodb:27017');
            $mongo->listDatabases();
            return true;
        } catch (\Exception $e) {
            Logger::warning('MongoDB health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $redis = new \Redis();
            $redis->connect(
                $_ENV['REDIS_HOST'] ?? 'redis',
                (int)($_ENV['REDIS_PORT'] ?? 6379)
            );
            $redis->ping();
            return true;
        } catch (\Exception $e) {
            Logger::warning('Redis health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getUptime(): int
    {
        if (function_exists('posix_times')) {
            $times = posix_times();
            return (int)($times['ticks'] / 100);
        }

        $uptimeFile = '/tmp/phpfrarm_start_time';
        if (file_exists($uptimeFile)) {
            $startTime = (int)file_get_contents($uptimeFile);
            return time() - $startTime;
        }

        file_put_contents($uptimeFile, (string)time());
        return 0;
    }

    private function getMemoryUsage(): float
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');

        $unit = strtoupper(substr($memoryLimit, -1));
        $limit = (int)$memoryLimit;

        switch($unit) {
            case 'G': $limit *= 1024;
            case 'M': $limit *= 1024;
            case 'K': $limit *= 1024;
        }

        return $limit > 0 ? round(($memoryUsage / $limit) * 100, 2) : 0;
    }

    private function getCPUUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpuCount = $this->getCPUCount();
            return min(round(($load[0] / $cpuCount) * 100, 2), 100);
        }
        return 0;
    }

    private function getCPUCount(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int)($_ENV['NUMBER_OF_PROCESSORS'] ?? 1);
        }
        return (int)shell_exec('nproc') ?: 1;
    }

    private function getStorageUsage(): float
    {
        $total = disk_total_space('/var/www');
        $free = disk_free_space('/var/www');
        if ($total === false || $free === false) {
            return 0;
        }
        $used = $total - $free;
        return $total > 0 ? round(($used / $total) * 100, 2) : 0;
    }

    private function getErrorRate(): float
    {
        try {
            if (!class_exists('\MongoDB\Client')) {
                return 0.0;
            }
            $mongo = new \MongoDB\Client($_ENV['MONGODB_URI'] ?? 'mongodb://mongodb:27017');
            $db = $mongo->selectDatabase('phpfrarm_logs');
            $collection = $db->selectCollection('application_logs');

            $yesterday = new \MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);

            $totalRequests = $collection->countDocuments([
                'timestamp' => ['$gte' => $yesterday]
            ]);

            $errorRequests = $collection->countDocuments([
                'timestamp' => ['$gte' => $yesterday],
                'level' => ['$in' => ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']]
            ]);

            return $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0.0;
        } catch (\Exception $e) {
            Logger::warning('Error rate calculation failed', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    private function getActiveConnections(): int
    {
        try {
            Database::enableRawQueries();
            $result = Database::execute("SHOW STATUS LIKE 'Threads_connected'");
            Database::disableRawQueries();
            return (int)($result[0]['Value'] ?? 0);
        } catch (\Exception $e) {
            Database::disableRawQueries();
            return 0;
        }
    }

    private function getTotalUsers(): int
    {
        try {
            $result = Database::callProcedure('sp_get_total_users', []);
            return (int)($result[0]['total_users'] ?? 0);
        } catch (\Exception $e) {
            Logger::warning('Failed to get total users', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getActiveSessions(): int
    {
        try {
            Database::enableRawQueries();
            $result = Database::execute("
                SELECT COUNT(DISTINCT user_id) as active_count
                FROM user_tokens
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND expires_at > NOW()
            ");
            Database::disableRawQueries();
            return (int)($result[0]['active_count'] ?? 0);
        } catch (\Exception $e) {
            Database::disableRawQueries();
            return 0;
        }
    }

    private function getAPICallsToday(): int
    {
        try {
            if (!class_exists('\MongoDB\Client')) {
                return 0;
            }
            $mongo = new \MongoDB\Client($_ENV['MONGODB_URI'] ?? 'mongodb://mongodb:27017');
            $db = $mongo->selectDatabase('phpfrarm_logs');
            $collection = $db->selectCollection('access_logs');

            $today = new \MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);

            return $collection->countDocuments([
                'timestamp' => ['$gte' => $today]
            ]);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheHitRate(): float
    {
        try {
            $redis = new \Redis();
            $redis->connect(
                $_ENV['REDIS_HOST'] ?? 'redis',
                (int)($_ENV['REDIS_PORT'] ?? 6379)
            );

            $info = $redis->info('stats');
            $hits = (int)($info['keyspace_hits'] ?? 0);
            $misses = (int)($info['keyspace_misses'] ?? 0);
            $total = $hits + $misses;

            return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getJobStats(): array
    {
        try {
            $redis = new \Redis();
            $redis->connect(
                $_ENV['REDIS_HOST'] ?? 'redis',
                (int)($_ENV['REDIS_PORT'] ?? 6379)
            );

            $pending = $redis->lLen('jobs:queue');
            $failed = $redis->lLen('jobs:failed');

            return [
                'pending' => $pending ?: 0,
                'failed' => $failed ?: 0
            ];
        } catch (\Exception $e) {
            return [
                'pending' => 0,
                'failed' => 0
            ];
        }
    }
}
