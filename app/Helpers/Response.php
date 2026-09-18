<?php
declare(strict_types=1);

namespace App\Helpers;

class Response
{
    /**
     * Send JSON response.
     */
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send error JSON response.
     */
    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        self::json(array_merge([
            'success' => false,
            'error' => $message,
        ], $extra), $statusCode);
    }

    /**
     * Send success JSON response.
     */
    public static function success(mixed $data = [], string $message = 'Success', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
