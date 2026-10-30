<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Repositories\Contracts\PasswordResetRepository;

final class PdoPasswordResetRepository extends PdoRepository implements PasswordResetRepository
{
    public function put(string $email, string $tokenHash): void
    {
        $this->database->execute(
            'INSERT INTO password_reset_tokens (email, token, created_at)
             VALUES (:email, :token, now())
             ON CONFLICT (email) DO UPDATE SET token = EXCLUDED.token, created_at = now()',
            ['email' => mb_strtolower(trim($email)), 'token' => $tokenHash],
        );
    }

    public function find(string $email): ?array
    {
        return $this->database->selectOne(
            'SELECT * FROM password_reset_tokens WHERE email = :email',
            ['email' => mb_strtolower(trim($email))],
        );
    }

    public function delete(string $email): void
    {
        $this->database->execute(
            'DELETE FROM password_reset_tokens WHERE email = :email',
            ['email' => mb_strtolower(trim($email))],
        );
    }
}
