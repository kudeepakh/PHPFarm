<?php

namespace App\Core\Cache;

/**
 * Cache Statistics Tracker
 * 
 * Tracks cache hits, misses, and other metrics.
 */
class CacheStatistics
{
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'flushes' => 0,
        'bytes_stored' => 0,
        'operations' => 0,
    ];

    private int $startTime;

    public function __construct()
    {
        $this->startTime = time();
    }

    public function recordHit(string $key): void
    {
        $this->stats['hits']++;
        $this->stats['operations']++;
    }

    public function recordMiss(string $key): void
    {
        $this->stats['misses']++;
        $this->stats['operations']++;
    }

    public function recordSet(string $key, int $bytes): void
    {
        $this->stats['sets']++;
        $this->stats['bytes_stored'] += $bytes;
        $this->stats['operations']++;
    }

    public function recordDelete(string $key): void
    {
        $this->stats['deletes']++;
        $this->stats['operations']++;
    }

    public function recordFlush(string $type, int $count = 0): void
    {
        $this->stats['flushes']++;
        $this->stats['operations']++;
    }

    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;
        $uptime = time() - $this->startTime;

        return array_merge($this->stats, [
            'hit_rate' => round($hitRate, 2),
            'miss_rate' => round(100 - $hitRate, 2),
            'uptime_seconds' => $uptime,
            'operations_per_second' => $uptime > 0 ? round($this->stats['operations'] / $uptime, 2) : 0,
        ]);
    }

    public function reset(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'flushes' => 0,
            'bytes_stored' => 0,
            'operations' => 0,
        ];
        $this->startTime = time();
    }
}
