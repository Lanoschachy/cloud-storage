<?php
declare(strict_types=1);

namespace App;

use App\Helpers\Response;

class Router
{
    protected array $routes = [];
    protected array $middleware = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    protected function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => rtrim($path, '/') ?: '/',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $parsedUrl = parse_url($requestUri);
        $path = rtrim($parsedUrl['path'] ?? '/', '/') ?: '/';

        // Remove base path if run from subfolder
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && $scriptName !== '\\' && !empty($scriptName)) {
            if (str_starts_with($path, $scriptName)) {
                $path = substr($path, strlen($scriptName));
                $path = rtrim($path, '/') ?: '/';
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $route['path'] === $path) {
                // Execute middleware
                foreach ($route['middleware'] as $mw) {
                    if (!$mw->handle()) {
                        return;
                    }
                }
                // Execute handler
                call_user_func($route['handler']);
                return;
            }
        }

        // Not Found
        if (str_starts_with($path, '/api/')) {
            Response::error('Endpoint not found', 404);
        } else {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
        }
    }
}
