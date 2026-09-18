<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\Response;

class AuthController
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(): void
    {
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput, true);
        $username = trim($json['username'] ?? $_POST['username'] ?? '');
        $password = trim($json['password'] ?? $_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::error('Username dan password harus diisi.');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $result = $this->authService->attempt($username, $password, $ip);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'Login gagal.', 401);
            return;
        }

        Response::success([
            'username' => $result['username'],
            'csrf_token' => $result['csrf_token'],
        ], 'Login berhasil.');
    }

    public function status(): void
    {
        $isAuth = $this->authService->check();
        if (!$isAuth) {
            Response::json(['authenticated' => false]);
            return;
        }

        Response::json([
            'authenticated' => true,
            'username' => $_SESSION['username'] ?? 'User',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function logout(): void
    {
        $this->authService->logout();
        Response::success([], 'Logout berhasil.');
    }

    public function changePassword(): void
    {
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput, true);

        $currentPassword = $json['current_password'] ?? '';
        $newPassword = $json['new_password'] ?? '';

        $result = $this->authService->changePassword($currentPassword, $newPassword);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'Password berhasil diubah.');
    }
}
