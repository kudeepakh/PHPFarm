<?php

namespace PHPFrarm\Core\Attributes;

use Attribute;

/**
 * Route Group Attribute for Controllers
 * Apply common prefix and middleware to all routes in a controller
 * 
 * @example
 * #[RouteGroup('/api/auth', middleware: ['cors', 'rateLimit'])]
 * class AuthController { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup
{
    public string $prefix;
    public array $middleware;

    public function __construct(
        string $prefix = '',
        array $middleware = []
    ) {
        $this->prefix = $prefix;
        $this->middleware = $middleware;
    }
}
