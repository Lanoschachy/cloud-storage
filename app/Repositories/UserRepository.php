<?php
declare(strict_types=1);

namespace App\Repositories;

class UserRepository extends JsonRepository
{
    public function __construct(string $dataPath)
    {
        parent::__construct($dataPath . DIRECTORY_SEPARATOR . 'user.json');
        
        // Initialize default user if not exists
        $user = $this->read();
        if (empty($user) || !isset($user['username'])) {
            $this->write([
                'username' => 'admin',
                // Default password: password123 (Harus diganti setelah setup)
                'password_hash' => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]),
                'created_at' => date('Y-m-d H:i:s'),
                'last_login_at' => null,
                'last_login_ip' => null,
                'failed_login_attempts' => 0,
                'lockout_until' => null,
            ]);
        }
    }

    public function getUser(): array
    {
        return $this->read();
    }

    public function updateUser(array $newData): bool
    {
        return $this->mutate(function (&$user) use ($newData) {
            $user = array_merge($user, $newData);
            return true;
        }) !== false;
    }
}
