<?php

namespace PHPFrarm\Modules\User\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\I18n\Translator;
use PHPFrarm\Core\Response;

/**
 * User Module Health Controller
 */
#[RouteGroup('/api/users', middleware: ['cors'])]
class UserHealthController
{
    /**
     * Module health check
     *
     * GET /api/users/health
     */
    #[Route('/health', method: 'GET', description: 'User module health')]
    public function health(array $request): void
    {
        $status = Translator::translate('user.module.running');
        Response::success(['status' => $status], 'user.module.running');
    }
}
