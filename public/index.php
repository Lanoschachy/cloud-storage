<?php
declare(strict_types=1);

// Disable direct viewing of directory
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load configuration
$config = require dirname(__DIR__) . '/app/Config/config.php';

// Configure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', $config['session']['samesite']);
    if ($config['session']['secure']) {
        ini_set('session.cookie_secure', '1');
    }
    session_name($config['session']['name']);
    session_start();
}

// Initialize Repositories
$dataPath = $config['paths']['data'];
$storageFilesPath = $config['paths']['files'];

// Ensure directories exist and have restrictive permissions
foreach ([$dataPath, $config['paths']['storage'], $storageFilesPath, $config['paths']['trash']] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
}

$userRepo = new \App\Repositories\UserRepository($dataPath);
$folderRepo = new \App\Repositories\FolderRepository($dataPath);
$fileRepo = new \App\Repositories\FileRepository($dataPath);
$trashRepo = new \App\Repositories\TrashRepository($dataPath);
$activityRepo = new \App\Repositories\ActivityRepository($dataPath);
$settingsRepo = new \App\Repositories\SettingsRepository($dataPath);

// Initialize Services
$authService = new \App\Services\AuthService($userRepo, $config);
$storageUsageService = new \App\Services\StorageUsageService($fileRepo, $settingsRepo, $storageFilesPath);
$fileService = new \App\Services\FileService($fileRepo, $folderRepo, $trashRepo, $activityRepo, $storageUsageService, $storageFilesPath);
$folderService = new \App\Services\FolderService($folderRepo, $fileRepo, $trashRepo, $activityRepo);
$trashService = new \App\Services\TrashService($trashRepo, $fileRepo, $folderRepo, $activityRepo, $storageFilesPath);

// Initialize Middleware
$authMiddleware = new \App\Middleware\AuthMiddleware($authService);
$csrfMiddleware = new \App\Middleware\CsrfMiddleware();

// Initialize Controllers
$authController = new \App\Controllers\AuthController($authService);
$fileController = new \App\Controllers\FileController($fileRepo, $folderRepo, $fileService, $folderService, $storageUsageService);
$folderController = new \App\Controllers\FolderController($folderRepo, $folderService);
$previewController = new \App\Controllers\PreviewController($fileRepo, $fileService);
$trashController = new \App\Controllers\TrashController($trashService);
$activityController = new \App\Controllers\ActivityController($activityRepo);
$settingsController = new \App\Controllers\SettingsController($settingsRepo);

// Initialize Router
$router = new \App\Router();

// --- Auth Routes ---
$router->post('/api/auth/login', [$authController, 'login']);
$router->get('/api/auth/status', [$authController, 'status']);
$router->post('/api/auth/logout', [$authController, 'logout'], [$authMiddleware]);
$router->post('/api/auth/change-password', [$authController, 'changePassword'], [$authMiddleware, $csrfMiddleware]);

// --- File Routes (Private) ---
$router->get('/api/files/list', [$fileController, 'list'], [$authMiddleware]);
$router->post('/api/files/upload', [$fileController, 'upload'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/files/rename', [$fileController, 'rename'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/files/move', [$fileController, 'move'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/files/star', [$fileController, 'toggleStar'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/files/delete', [$fileController, 'delete'], [$authMiddleware, $csrfMiddleware]);
$router->get('/api/files/search', [$fileController, 'search'], [$authMiddleware]);

// --- Folder Routes (Private) ---
$router->get('/api/folders/tree', [$folderController, 'tree'], [$authMiddleware]);
$router->post('/api/folders/create', [$folderController, 'create'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/folders/rename', [$folderController, 'rename'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/folders/move', [$folderController, 'move'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/folders/delete', [$folderController, 'delete'], [$authMiddleware, $csrfMiddleware]);

// --- Preview & Download Routes (Private & Protected Stream) ---
$router->get('/api/files/preview', [$previewController, 'preview'], [$authMiddleware]);
$router->get('/api/files/download', [$previewController, 'download'], [$authMiddleware]);
$router->get('/api/files/text', [$previewController, 'textContent'], [$authMiddleware]);

// --- Trash Routes (Private) ---
$router->get('/api/trash/list', [$trashController, 'list'], [$authMiddleware]);
$router->post('/api/trash/restore', [$trashController, 'restore'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/trash/delete', [$trashController, 'delete'], [$authMiddleware, $csrfMiddleware]);
$router->post('/api/trash/empty', [$trashController, 'empty'], [$authMiddleware, $csrfMiddleware]);

// --- Activity & Settings Routes (Private) ---
$router->get('/api/activity/list', [$activityController, 'list'], [$authMiddleware]);
$router->get('/api/settings', [$settingsController, 'get'], [$authMiddleware]);
$router->post('/api/settings/update', [$settingsController, 'update'], [$authMiddleware, $csrfMiddleware]);

// --- UI Frontend App Route ---
$router->get('/', function () {
    require __DIR__ . '/app.php';
});

// Dispatch request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
