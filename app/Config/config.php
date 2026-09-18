<?php
declare(strict_types=1);

// Application Configuration
return [
    'app_name' => 'Private Cloud Storage',
    'app_env' => 'production',
    'debug' => false,
    
    // Path configurations
    'paths' => [
        'root'    => dirname(__DIR__, 2),
        'app'     => dirname(__DIR__),
        'data'    => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data',
        'storage' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage',
        'files'   => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'files',
        'trash'   => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'trash',
    ],
    
    // Session settings
    'session' => [
        'name' => 'PCS_SESSION',
        'lifetime' => 86400 * 7, // 7 days
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // Security & Rate Limiting
    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration'   => 900, // 15 minutes
        'csrf_token_name'    => 'csrf_token',
        'csrf_header_name'   => 'HTTP_X_CSRF_TOKEN',
    ],

    // Trash Retention
    'trash' => [
        'retention_days' => 30,
        'auto_cleanup'   => true,
    ],

    // Preview limits
    'preview' => [
        'max_text_size' => 5 * 1024 * 1024, // 5MB max for text/code preview in memory
        'stream_chunk_size' => 64 * 1024,   // 64KB for chunk streaming
    ],
];
