<?php

namespace Farm\Backend\Tests\Factories;

/**
 * Factory Registry
 * 
 * Central registry for all test data factories.
 * Provides factory resolution by name.
 * 
 * Usage:
 * ```php
 * $registry = FactoryRegistry::getInstance();
 * $user = $registry->make('User')->create();
 * ```
 */
class FactoryRegistry
{
    private static ?FactoryRegistry $instance = null;
    private array $factories = [];

    private function __construct()
    {
        $this->registerDefaultFactories();
    }

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Register default factories
     * 
     * @return void
     */
    private function registerDefaultFactories(): void
    {
        $this->register(UserFactory::class);
    }

    /**
     * Register factory
     * 
     * @param string $factoryClass
     * @return void
     */
    public function register(string $factoryClass): void
    {
        // Extract factory name (e.g., UserFactory -> User)
        $className = basename(str_replace('\\', '/', $factoryClass));
        $name = str_replace('Factory', '', $className);
        
        $this->factories[$name] = $factoryClass;
    }

    /**
     * Get factory by name
     * 
     * @param string $name
     * @return Factory
     */
    public function get(string $name): Factory
    {
        if (!isset($this->factories[$name])) {
            throw new \InvalidArgumentException("Factory not found: $name");
        }
        
        $factoryClass = $this->factories[$name];
        return $factoryClass::new();
    }

    /**
     * Create new factory instance
     * 
     * @param string $name
     * @return Factory
     */
    public function make(string $name): Factory
    {
        return $this->get($name);
    }

    /**
     * Check if factory exists
     * 
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    /**
     * Get all registered factories
     * 
     * @return array
     */
    public function all(): array
    {
        return $this->factories;
    }

    /**
     * Clear all registered factories
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->factories = [];
    }
}
