<?php

namespace PHPFrarm\Core\Queue;

use PHPFrarm\Core\Logger;

/**
 * Redis-based Job Queue
 * 
 * Simple job queue using Redis for async processing.
 * Supports delayed jobs, retries, and failed job tracking.
 */
class JobQueue
{
    private static ?JobQueue $instance = null;
    private ?\Redis $redis = null;
    
    // Queue names
    private const QUEUE_DEFAULT = 'phpfrarm:jobs:default';
    private const QUEUE_HIGH = 'phpfrarm:jobs:high';
    private const QUEUE_LOW = 'phpfrarm:jobs:low';
    private const QUEUE_DELAYED = 'phpfrarm:jobs:delayed';
    private const QUEUE_FAILED = 'phpfrarm:jobs:failed';
    private const QUEUE_PROCESSING = 'phpfrarm:jobs:processing';

    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to Redis
     */
    private function connect(): void
    {
        try {
            $this->redis = new \Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $password = $_ENV['REDIS_PASSWORD'] ?? null;

            $connected = $this->redis->connect($host, $port, 5.0);
            if (!$connected) {
                throw new \Exception("Could not connect to Redis at $host:$port");
            }

            if (!empty($password)) {
                $this->redis->auth($password);
            }

            // Select database 1 for queues (0 is usually for cache)
            $this->redis->select(1);

            Logger::debug('JobQueue connected to Redis', ['host' => $host, 'port' => $port]);
        } catch (\Exception $e) {
            Logger::error('JobQueue Redis connection failed', ['error' => $e->getMessage()]);
            $this->redis = null;
        }
    }

    /**
     * Check if queue is available
     */
    public function isAvailable(): bool
    {
        return $this->redis !== null;
    }

    /**
     * Dispatch a job to the queue
     * 
     * @param JobInterface $job The job to dispatch
     * @param string $priority 'high', 'default', or 'low'
     * @param int $delay Delay in seconds before job becomes available
     * @return string|null Job ID if successful
     */
    public static function dispatch(JobInterface $job, string $priority = 'default', int $delay = 0): ?string
    {
        $instance = self::getInstance();
        
        if (!$instance->isAvailable()) {
            Logger::warning('Queue not available, executing job synchronously', [
                'job' => $job->getName()
            ]);
            // Fallback: execute synchronously
            try {
                $job->handle();
                return 'sync_' . bin2hex(random_bytes(8));
            } catch (\Exception $e) {
                Logger::error('Sync job execution failed', [
                    'job' => $job->getName(),
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        return $instance->push($job, $priority, $delay);
    }

    /**
     * Push job to queue
     */
    private function push(JobInterface $job, string $priority, int $delay): ?string
    {
        try {
            $jobId = bin2hex(random_bytes(16));
            $jobData = [
                'id' => $jobId,
                'job' => $job->serialize(),
                'priority' => $priority,
                'queued_at' => time(),
                'available_at' => time() + $delay,
            ];

            $serialized = json_encode($jobData);

            if ($delay > 0) {
                // Add to delayed queue (sorted set by available_at timestamp)
                $this->redis->zAdd(self::QUEUE_DELAYED, time() + $delay, $serialized);
            } else {
                // Add to appropriate priority queue
                $queue = $this->getQueueName($priority);
                $this->redis->rPush($queue, $serialized);
            }

            Logger::info('Job queued', [
                'job_id' => $jobId,
                'job' => $job->getName(),
                'priority' => $priority,
                'delay' => $delay,
            ]);

            return $jobId;
        } catch (\Exception $e) {
            Logger::error('Failed to queue job', [
                'job' => $job->getName(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Pop next job from queue (for worker)
     */
    public function pop(int $timeout = 5): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            // First, move any delayed jobs that are now ready
            $this->moveDelayedJobs();

            // Try queues in priority order
            $queues = [self::QUEUE_HIGH, self::QUEUE_DEFAULT, self::QUEUE_LOW];
            
            foreach ($queues as $queue) {
                $result = $this->redis->blPop([$queue], 1);
                if ($result) {
                    $jobData = json_decode($result[1], true);
                    if ($jobData) {
                        // Move to processing queue
                        $this->redis->hSet(self::QUEUE_PROCESSING, $jobData['id'], $result[1]);
                        return $jobData;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Logger::error('Failed to pop job from queue', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Move delayed jobs that are now ready to their priority queues
     */
    private function moveDelayedJobs(): void
    {
        $now = time();
        $readyJobs = $this->redis->zRangeByScore(self::QUEUE_DELAYED, '-inf', (string)$now);

        foreach ($readyJobs as $jobJson) {
            $jobData = json_decode($jobJson, true);
            if ($jobData) {
                $queue = $this->getQueueName($jobData['priority'] ?? 'default');
                $this->redis->rPush($queue, $jobJson);
                $this->redis->zRem(self::QUEUE_DELAYED, $jobJson);
            }
        }
    }

    /**
     * Mark job as complete (remove from processing)
     */
    public function complete(string $jobId): void
    {
        if ($this->isAvailable()) {
            $this->redis->hDel(self::QUEUE_PROCESSING, $jobId);
        }
    }

    /**
     * Mark job as failed
     */
    public function fail(string $jobId, string $jobData, string $error): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        try {
            $this->redis->hDel(self::QUEUE_PROCESSING, $jobId);
            
            $failedData = json_encode([
                'job_data' => $jobData,
                'error' => $error,
                'failed_at' => date('Y-m-d H:i:s'),
            ]);
            
            $this->redis->rPush(self::QUEUE_FAILED, $failedData);
        } catch (\Exception $e) {
            Logger::error('Failed to record job failure', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Retry a failed job
     */
    public function retry(string $jobId, JobInterface $job, int $delay = 0): ?string
    {
        return $this->push($job, 'default', $delay);
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        if (!$this->isAvailable()) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'high_priority' => $this->redis->lLen(self::QUEUE_HIGH),
            'default' => $this->redis->lLen(self::QUEUE_DEFAULT),
            'low_priority' => $this->redis->lLen(self::QUEUE_LOW),
            'delayed' => $this->redis->zCard(self::QUEUE_DELAYED),
            'processing' => $this->redis->hLen(self::QUEUE_PROCESSING),
            'failed' => $this->redis->lLen(self::QUEUE_FAILED),
        ];
    }

    /**
     * Get queue name by priority
     */
    private function getQueueName(string $priority): string
    {
        return match ($priority) {
            'high' => self::QUEUE_HIGH,
            'low' => self::QUEUE_LOW,
            default => self::QUEUE_DEFAULT,
        };
    }

    /**
     * Clear all queues (for testing)
     */
    public function clear(): void
    {
        if ($this->isAvailable()) {
            $this->redis->del([
                self::QUEUE_HIGH,
                self::QUEUE_DEFAULT,
                self::QUEUE_LOW,
                self::QUEUE_DELAYED,
                self::QUEUE_PROCESSING,
                self::QUEUE_FAILED,
            ]);
        }
    }
}
