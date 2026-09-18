<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Helpers\Security;

class AuthService
{
    protected UserRepository $userRepo;
    protected array $config;

    public function __construct(UserRepository $userRepo, array $config)
    {
        $this->userRepo = $userRepo;
        $this->config = $config;
    }

    public function attempt(string $username, string $password, string $ipAddress): array
    {
        $user = $this->userRepo->getUser();
        $now = time();

        // Check Lockout
        if (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > $now) {
            $remaining = strtotime($user['lockout_until']) - $now;
            return [
                'success' => false,
                'error' => "Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$remaining} detik.",
                'locked' => true,
            ];
        }

        // Verify username & password
        if ($username !== $user['username'] || !password_verify($password, $user['password_hash'])) {
            $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
            $maxAttempts = $this->config['security']['max_login_attempts'] ?? 5;
            $lockoutDuration = $this->config['security']['lockout_duration'] ?? 900;
            $lockoutUntil = null;

            if ($attempts >= $maxAttempts) {
                $lockoutUntil = date('Y-m-d H:i:s', $now + $lockoutDuration);
            }

            $this->userRepo->updateUser([
                'failed_login_attempts' => $attempts,
                'lockout_until' => $lockoutUntil,
            ]);

            return [
                'success' => false,
                'error' => 'Username atau password salah.',
                'attempts' => $attempts,
            ];
        }

        // Reset failed attempts upon success
        $this->userRepo->updateUser([
            'failed_login_attempts' => 0,
            'lockout_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ipAddress,
        ]);

        // Regenerate session id to protect against session fixation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = $now;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return [
            'success' => true,
            'username' => $user['username'],
            'csrf_token' => $_SESSION['csrf_token'],
        ];
    }

    public function check(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public function changePassword(string $currentPassword, string $newPassword): array
    {
        $user = $this->userRepo->getUser();
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Password saat ini salah.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password baru minimal 8 karakter.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->userRepo->updateUser([
            'password_hash' => $newHash,
        ]);

        return ['success' => true];
    }
}
