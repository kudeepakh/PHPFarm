<?php

namespace PHPFrarm\Core\Attributes;

use Attribute;

/**
 * Public attribute - Marks a route as public (no authentication required)
 * 
 * Use sparingly and only with explicit security approval.
 * All routes require authentication by default.
 * 
 * Usage:
 * #[Public]
 * #[Route('/health', method: 'GET')]
 * public function health(): void { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class PublicRoute
{
    public function __construct(
        public ?string $reason = null
    ) {}
}
