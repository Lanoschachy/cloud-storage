<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use App\Services\AuthService;

class AuthMiddleware
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function handle(): bool
    {
        if (!$this->authService->check()) {
            Response::error('Unauthenticated', 401);
            return false;
        }
        return true;
    }
}
