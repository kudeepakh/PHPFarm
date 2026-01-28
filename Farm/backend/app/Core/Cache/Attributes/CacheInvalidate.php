<?php

namespace App\Core\Cache\Attributes;

use Attribute;

/**
 * Cache Invalidation Attribute
 * 
 * Automatically invalidate caches when this route is executed.
 * Useful for write operations (POST, PUT, DELETE).
 * 
 * Usage Examples:
 * 
 * Invalidate by tags:
 * #[CacheInvalidate(tags: ['users', 'profiles'])]
 * public function updateUser(int $id) { }
 * 
 * Invalidate by pattern:
 * #[CacheInvalidate(pattern: 'user:*')]
 * public function deleteUser(int $id) { }
 * 
 * Invalidate specific keys:
 * #[CacheInvalidate(keys: ['user:{id}:profile', 'user:{id}:settings'])]
 * public function updateUserProfile(int $id) { }
 * 
 * Conditional invalidation:
 * #[CacheInvalidate(tags: ['posts'], when: 'post.status == "published"')]
 * public function updatePost(int $id) { }
 * 
 * Invalidate all:
 * #[CacheInvalidate(all: true)]
 * public function rebuildCache() { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class CacheInvalidate
{
    /**
     * @param array $tags Cache tags to invalidate
     * @param array $keys Specific cache keys to invalidate (supports placeholders)
     * @param string|null $pattern Pattern to match keys (supports wildcards)
     * @param bool $all Invalidate all caches
     * @param string|null $when Condition that must be true to invalidate
     * @param string $timing When to invalidate: 'before' or 'after' route execution
     * @param bool $cascade Cascade invalidation to dependent caches
     * @param array $cascadeTags Additional tags to invalidate if cascade is true
     */
    public function __construct(
        public array $tags = [],
        public array $keys = [],
        public ?string $pattern = null,
        public bool $all = false,
        public ?string $when = null,
        public string $timing = 'after', // 'before' or 'after'
        public bool $cascade = false,
        public array $cascadeTags = []
    ) {
        if (!in_array($this->timing, ['before', 'after'])) {
            throw new \InvalidArgumentException('Timing must be "before" or "after"');
        }

        if ($this->all && (!empty($this->tags) || !empty($this->keys) || $this->pattern !== null)) {
            throw new \InvalidArgumentException('Cannot specify tags, keys, or pattern when all=true');
        }
    }

    /**
     * Check if invalidation should be executed
     */
    public function shouldInvalidate(array $context = []): bool
    {
        if ($this->when === null) {
            return true;
        }

        return $this->evaluateCondition($this->when, $context);
    }

    /**
     * Evaluate simple condition expressions
     */
    private function evaluateCondition(string $condition, array $context): bool
    {
        // Handle simple boolean checks
        if (strpos($condition, '==') === false && strpos($condition, '!=') === false) {
            $value = $this->getNestedValue($condition, $context);
            return (bool) $value;
        }

        // Handle equality checks
        if (strpos($condition, '==') !== false) {
            [$key, $expectedValue] = array_map('trim', explode('==', $condition, 2));
            $actualValue = $this->getNestedValue($key, $context);
            $expectedValue = trim($expectedValue, "'\"");
            return $actualValue == $expectedValue;
        }

        // Handle inequality checks
        if (strpos($condition, '!=') !== false) {
            [$key, $expectedValue] = array_map('trim', explode('!=', $condition, 2));
            $actualValue = $this->getNestedValue($key, $context);
            $expectedValue = trim($expectedValue, "'\"");
            return $actualValue != $expectedValue;
        }

        return false;
    }

    /**
     * Get nested value from context using dot notation
     */
    private function getNestedValue(string $key, array $context)
    {
        $keys = explode('.', $key);
        $value = $context;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } elseif (is_object($value) && isset($value->$k)) {
                $value = $value->$k;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get all tags to invalidate (including cascade tags)
     */
    public function getAllTags(): array
    {
        if (!$this->cascade) {
            return $this->tags;
        }

        return array_unique(array_merge($this->tags, $this->cascadeTags));
    }
}
