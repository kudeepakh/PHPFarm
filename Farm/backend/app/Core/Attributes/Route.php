<?php

namespace PHPFrarm\Core\Attributes;

use Attribute;

/**
 * Route Attribute for Controller Methods
 * 
 * @example
 * #[Route('/api/auth/login', method: 'POST', middleware: ['cors', 'rateLimit', 'jsonParser'])]
 * public function login(array $request): void { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public string $path;
    public string $method;
    public array $middleware;
    public ?string $description;

    public function __construct(
        string $path,
        string $method = 'GET',
        array $middleware = [],
        ?string $description = null
    ) {
        $this->path = $path;
        $this->method = strtoupper($method);
        $this->middleware = $middleware;
        $this->description = $description;
    }
}
