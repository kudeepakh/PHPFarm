<?php

namespace PHPFrarm\Core\Queue;

use PHPFrarm\Core\Logger;

/**
 * Job Worker
 * 
 * Background process that continuously processes queued jobs.
 * Run via: php artisan queue:work
 */
class Worker
{
    private JobQueue $queue;
    private bool $shouldQuit = false;
    private int $sleep = 3; // seconds to sleep when no jobs
    private int $maxJobs = 0; // 0 = unlimited
    private int $maxTime = 0; // 0 = unlimited (seconds)
    private int $processedJobs = 0;
    private float $startTime;

    public function __construct()
    {
        $this->queue = JobQueue::getInstance();
        $this->startTime = microtime(true);
    }

    /**
     * Run the worker loop
     */
    public function run(array $options = []): void
    {
        $this->maxJobs = $options['max_jobs'] ?? 0;
        $this->maxTime = $options['max_time'] ?? 0;
        $this->sleep = $options['sleep'] ?? 3;

        Logger::info('Queue worker started', [
            'max_jobs' => $this->maxJobs ?: 'unlimited',
            'max_time' => $this->maxTime ? "{$this->maxTime}s" : 'unlimited',
            'sleep' => "{$this->sleep}s",
        ]);

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }

        while (!$this->shouldQuit) {
            // Check if we should stop
            if ($this->shouldStop()) {
                break;
            }

            // Process signals if available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Get next job
            $jobData = $this->queue->pop(1);

            if ($jobData === null) {
                // No jobs available, sleep
                sleep($this->sleep);
                continue;
            }

            $this->processJob($jobData);
        }

        Logger::info('Queue worker stopped', [
            'processed_jobs' => $this->processedJobs,
            'runtime' => round(microtime(true) - $this->startTime, 2) . 's',
        ]);
    }

    /**
     * Process a single job
     */
    private function processJob(array $jobData): void
    {
        $jobId = $jobData['id'];
        $jobJson = $jobData['job'];

        Logger::debug('Processing job', ['job_id' => $jobId]);

        try {
            // Deserialize the job
            $job = Job::deserialize($jobJson);
            
            if ($job === null) {
                Logger::error('Failed to deserialize job', ['job_id' => $jobId]);
                $this->queue->fail($jobId, $jobJson, 'Deserialization failed');
                return;
            }

            // Increment attempt count
            $attempts = $job->getAttempts() + 1;
            $job->setAttempts($attempts);

            Logger::info('Executing job', [
                'job_id' => $jobId,
                'job' => $job->getName(),
                'attempt' => $attempts,
            ]);

            // Execute the job
            $success = $job->handle();

            if ($success) {
                $this->queue->complete($jobId);
                $this->processedJobs++;
                
                Logger::info('Job completed successfully', [
                    'job_id' => $jobId,
                    'job' => $job->getName(),
                ]);
            } else {
                throw new \Exception('Job returned false');
            }

        } catch (\Exception $e) {
            $this->handleJobFailure($jobId, $jobJson, $job ?? null, $e);
        }
    }

    /**
     * Handle job failure with retry logic
     */
    private function handleJobFailure(string $jobId, string $jobJson, ?JobInterface $job, \Exception $e): void
    {
        Logger::warning('Job execution failed', [
            'job_id' => $jobId,
            'error' => $e->getMessage(),
        ]);

        // Remove from processing queue
        $this->queue->complete($jobId);

        if ($job === null) {
            $this->queue->fail($jobId, $jobJson, $e->getMessage());
            return;
        }

        $attempts = $job->getAttempts();
        $maxAttempts = $job->getMaxAttempts();

        if ($attempts < $maxAttempts) {
            // Retry with delay
            $delay = $job->getRetryDelay();
            
            Logger::info('Retrying job', [
                'job_id' => $jobId,
                'job' => $job->getName(),
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
                'retry_delay' => $delay,
            ]);

            $this->queue->retry($jobId, $job, $delay);
        } else {
            // Max retries exceeded, mark as failed
            Logger::error('Job failed after max retries', [
                'job_id' => $jobId,
                'job' => $job->getName(),
                'attempts' => $attempts,
            ]);

            $job->failed($e);
            $this->queue->fail($jobId, $jobJson, $e->getMessage());
        }
    }

    /**
     * Check if worker should stop
     */
    private function shouldStop(): bool
    {
        // Check max jobs
        if ($this->maxJobs > 0 && $this->processedJobs >= $this->maxJobs) {
            Logger::info('Max jobs reached, stopping worker');
            return true;
        }

        // Check max time
        if ($this->maxTime > 0) {
            $runtime = microtime(true) - $this->startTime;
            if ($runtime >= $this->maxTime) {
                Logger::info('Max time reached, stopping worker');
                return true;
            }
        }

        return false;
    }

    /**
     * Signal handler for graceful shutdown
     */
    public function shutdown(): void
    {
        Logger::info('Shutdown signal received');
        $this->shouldQuit = true;
    }

    /**
     * Process a single job and exit (for testing)
     */
    public function runOnce(): bool
    {
        $jobData = $this->queue->pop(1);
        
        if ($jobData === null) {
            return false;
        }

        $this->processJob($jobData);
        return true;
    }
}
