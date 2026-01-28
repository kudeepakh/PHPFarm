<?php

namespace PHPFrarm\Core\Authorization;

/**
 * Policy Rule Base Class
 * 
 * Abstract class for implementing custom authorization policies.
 * 
 * @package PHPFrarm\Core\Authorization
 */
abstract class PolicyRule
{
    protected string $name;
    protected int $priority = 100;

    /**
     * Evaluate if user can perform action on resource
     * 
     * @param array $user User data
     * @param string $action Action to perform
     * @param mixed $resource Resource being accessed
     * @param array $context Additional context
     * @return bool True to allow, false to deny
     */
    abstract public function evaluate(array $user, string $action, $resource, array $context = []): bool;

    /**
     * Get rule name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?? get_class($this);
    }

    /**
     * Get rule priority (higher = evaluated first)
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set rule priority
     * 
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
}


/**
 * Time-Based Policy
 * 
 * Restricts access based on time, day of week, or date range.
 * 
 * Examples:
 * - Office hours only (9am-5pm)
 * - Weekdays only
 * - Special event access (date range)
 */
class TimeBasedPolicy extends PolicyRule
{
    private ?string $startTime = null;
    private ?string $endTime = null;
    private array $allowedDays = [];
    private ?\DateTime $startDate = null;
    private ?\DateTime $endDate = null;
    private string $timezone = 'UTC';

    public function __construct(string $name = 'TimeBasedPolicy')
    {
        $this->name = $name;
        $this->priority = 200;
    }

    /**
     * Set allowed time range (24-hour format)
     * 
     * @param string $startTime "09:00"
     * @param string $endTime "17:00"
     * @return self
     */
    public function setTimeRange(string $startTime, string $endTime): self
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        return $this;
    }

    /**
     * Set allowed days of week
     * 
     * @param array $days [1=Monday, 2=Tuesday, ..., 7=Sunday]
     * @return self
     */
    public function setAllowedDays(array $days): self
    {
        $this->allowedDays = $days;
        return $this;
    }

    /**
     * Set date range
     * 
     * @param \DateTime $start Start date
     * @param \DateTime $end End date
     * @return self
     */
    public function setDateRange(\DateTime $start, \DateTime $end): self
    {
        $this->startDate = $start;
        $this->endDate = $end;
        return $this;
    }

    /**
     * Set timezone for evaluation
     * 
     * @param string $timezone
     * @return self
     */
    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function evaluate(array $user, string $action, $resource, array $context = []): bool
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));

        // Check date range
        if ($this->startDate && $this->endDate) {
            if ($now < $this->startDate || $now > $this->endDate) {
                return false;
            }
        }

        // Check day of week (1=Monday, 7=Sunday)
        if (!empty($this->allowedDays)) {
            $currentDay = (int)$now->format('N');
            if (!in_array($currentDay, $this->allowedDays)) {
                return false;
            }
        }

        // Check time range
        if ($this->startTime && $this->endTime) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $this->startTime || $currentTime > $this->endTime) {
                return false;
            }
        }

        return true;
    }
}


/**
 * Resource Quota Policy
 * 
 * Enforces usage limits on resources.
 * 
 * Examples:
 * - Max 10 API calls per minute
 * - Max 100 GB storage per user
 * - Max 5 concurrent sessions
 */
class ResourceQuotaPolicy extends PolicyRule
{
    private string $resourceType;
    private int $limit;
    private string $period; // 'minute', 'hour', 'day', 'total'
    private $usageCallback;

    public function __construct(string $resourceType, int $limit, string $period = 'total')
    {
        $this->name = "QuotaPolicy:$resourceType";
        $this->priority = 150;
        $this->resourceType = $resourceType;
        $this->limit = $limit;
        $this->period = $period;
    }

    /**
     * Set callback to fetch current usage
     * 
     * @param callable $callback function(userId, resourceType, period): int
     * @return self
     */
    public function setUsageCallback(callable $callback): self
    {
        $this->usageCallback = $callback;
        return $this;
    }

    public function evaluate(array $user, string $action, $resource, array $context = []): bool
    {
        if (!$this->usageCallback) {
            // No usage tracking configured, allow
            return true;
        }

        $userId = $user['user_id'] ?? null;
        if (!$userId) {
            return false;
        }

        // Get current usage
        $currentUsage = call_user_func(
            $this->usageCallback,
            $userId,
            $this->resourceType,
            $this->period
        );

        // Check if under limit
        return $currentUsage < $this->limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }
}


/**
 * Advanced User Policy
 * 
 * Complex user-specific rules (VIP users, beta testers, etc.)
 */
class AdvancedUserPolicy extends PolicyRule
{
    private $conditionCallback;

    public function __construct(string $name, callable $condition)
    {
        $this->name = $name;
        $this->priority = 100;
        $this->conditionCallback = $condition;
    }

    public function evaluate(array $user, string $action, $resource, array $context = []): bool
    {
        return call_user_func($this->conditionCallback, $user, $action, $resource, $context);
    }
}
