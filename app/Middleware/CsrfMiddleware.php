<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use App\Helpers\Security;

class CsrfMiddleware
{
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
            if (!Security::validateCsrfToken($token)) {
                Response::error('Invalid or expired CSRF token.', 403);
                return false;
            }
        }
        return true;
    }
}
