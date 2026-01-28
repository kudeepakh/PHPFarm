<?php

namespace PHPFrarm\Core\Authorization;

use PHPFrarm\Core\Logger;

/**
 * Policy Engine
 * 
 * Evaluates complex authorization rules beyond basic RBAC.
 * Supports time-based, quota-based, and custom policies.
 * 
 * Usage:
 *   $engine = new PolicyEngine();
 *   $engine->addRule(new TimeBasedPolicy(...));
 *   $allowed = $engine->can($user, 'read', $resource, $context);
 * 
 * @package PHPFrarm\Core\Authorization
 */
class PolicyEngine
{
    /** @var PolicyRule[] */
    private array $rules = [];

    /** @var string Evaluation mode: 'all' (AND) or 'any' (OR) */
    private string $mode = 'all';

    /**
     * Add policy rule to engine
     * 
     * @param PolicyRule $rule
     * @return self
     */
    public function addRule(PolicyRule $rule): self
    {
        $this->rules[] = $rule;
        
        // Sort by priority (higher first)
        usort($this->rules, function($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        return $this;
    }

    /**
     * Set evaluation mode
     * 
     * @param string $mode 'all' (AND) or 'any' (OR)
     * @return self
     */
    public function setMode(string $mode): self
    {
        if (!in_array($mode, ['all', 'any'])) {
            throw new \InvalidArgumentException("Invalid mode: $mode. Use 'all' or 'any'");
        }

        $this->mode = $mode;
        return $this;
    }

    /**
     * Check if user can perform action on resource
     * 
     * @param array $user User data with roles
     * @param string $action Action to perform (read, write, delete, etc.)
     * @param mixed $resource Resource being accessed
     * @param array $context Additional context (time, IP, location, etc.)
     * @return bool
     */
    public function can(array $user, string $action, $resource, array $context = []): bool
    {
        if (empty($this->rules)) {
            // No policies defined, default to allow
            return true;
        }

        $results = [];
        $evaluatedRules = [];

        foreach ($this->rules as $rule) {
            try {
                $result = $rule->evaluate($user, $action, $resource, $context);
                $results[] = $result;
                $evaluatedRules[] = [
                    'rule' => get_class($rule),
                    'result' => $result,
                    'priority' => $rule->getPriority()
                ];

                // Early exit for 'any' mode if rule allows
                if ($this->mode === 'any' && $result === true) {
                    Logger::debug('Policy allows (any mode)', [
                        'user_id' => $user['user_id'] ?? null,
                        'action' => $action,
                        'rule' => get_class($rule)
                    ]);
                    return true;
                }

                // Early exit for 'all' mode if rule denies
                if ($this->mode === 'all' && $result === false) {
                    Logger::debug('Policy denies (all mode)', [
                        'user_id' => $user['user_id'] ?? null,
                        'action' => $action,
                        'rule' => get_class($rule)
                    ]);
                    return false;
                }

            } catch (\Exception $e) {
                Logger::error('Policy evaluation error', [
                    'rule' => get_class($rule),
                    'error' => $e->getMessage()
                ]);
                
                // On error, deny access for safety
                if ($this->mode === 'all') {
                    return false;
                }
            }
        }

        // Final decision based on mode
        if ($this->mode === 'all') {
            $allowed = !in_array(false, $results, true);
        } else {
            $allowed = in_array(true, $results, true);
        }

        Logger::info('Policy evaluation complete', [
            'user_id' => $user['user_id'] ?? null,
            'action' => $action,
            'mode' => $this->mode,
            'allowed' => $allowed,
            'rules_evaluated' => count($results),
            'rules' => $evaluatedRules
        ]);

        return $allowed;
    }

    /**
     * Evaluate specific policy by name/class
     * 
     * @param string $policyClass Policy class name
     * @param array $user User data
     * @param string $action Action
     * @param mixed $resource Resource
     * @param array $context Context
     * @return bool|null Null if policy not found
     */
    public function evaluatePolicy(string $policyClass, array $user, string $action, $resource, array $context = []): ?bool
    {
        foreach ($this->rules as $rule) {
            if (get_class($rule) === $policyClass || is_a($rule, $policyClass)) {
                return $rule->evaluate($user, $action, $resource, $context);
            }
        }

        return null;
    }

    /**
     * Get all registered rules
     * 
     * @return PolicyRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Clear all rules
     * 
     * @return self
     */
    public function clearRules(): self
    {
        $this->rules = [];
        return $this;
    }
}
