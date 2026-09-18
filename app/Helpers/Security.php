<?php
declare(strict_types=1);

namespace App\Helpers;

class Security
{
    /**
     * Sanitize user filenames to prevent directory traversal and null byte injections.
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Strip null bytes
        $filename = str_replace(chr(0), '', $filename);
        // Use basename to prevent path traversal
        $filename = basename($filename);
        // Remove unsafe characters, but preserve international characters and spaces
        $filename = preg_replace('/[\\/\\\\:*?"<>|]/', '_', $filename);
        // Trim dots and spaces
        $filename = trim($filename, ". \t\n\r\0\x0B");

        if ($filename === '') {
            $filename = 'unnamed_file_' . time();
        }

        return $filename;
    }

    /**
     * Generate safe random hex storage filename.
     */
    public static function generateStoredName(string $originalExtension): string
    {
        $cleanExt = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $originalExtension));
        $randomHex = bin2hex(random_bytes(16));
        return $cleanExt !== '' ? "{$randomHex}.{$cleanExt}" : $randomHex;
    }

    /**
     * Verify if realpath is safely contained in the allowed directory.
     */
    public static function isPathContained(string $baseDir, string $filePath): bool
    {
        $realBase = realpath($baseDir);
        $realPath = realpath($filePath);

        if ($realBase === false || $realPath === false) {
            return false;
        }

        return str_starts_with($realPath, $realBase);
    }

    /**
     * Escape output for HTML context.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate CSRF Token and store in session.
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF Token.
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
