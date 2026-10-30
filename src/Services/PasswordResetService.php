<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\Contracts\PasswordResetRepository;
use App\Repositories\Contracts\UserRepository;
use App\Support\Mailer;

final class PasswordResetService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetRepository $tokens,
        private readonly Mailer $mailer,
        private readonly Session $session,
    ) {
    }

    public function request(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = $this->users->findActiveByEmail($email);

        if ($user === null) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->tokens->put($email, hash('sha256', $token));

        $link = url('/atur-sandi/' . $token . '?email=' . rawurlencode($email));
        $this->mailer->send(
            $email,
            'Atur ulang kata sandi CompHub',
            "Halo {$user['name']},\n\nBuka tautan berikut untuk mengatur ulang kata sandi Anda:\n{$link}\n\n"
            . "Tautan berlaku 60 menit. Abaikan email ini jika Anda tidak meminta pengaturan ulang.\n",
        );
    }

    public function reset(string $email, string $token, string $password): void
    {
        $email = mb_strtolower(trim($email));
        $row = $this->tokens->find($email);

        if ($row === null || $token === '' || ! hash_equals((string) $row['token'], hash('sha256', $token))) {
            throw new ValidationException(['token' => ['Tautan atur ulang tidak valid.']]);
        }

        $created = strtotime((string) ($row['created_at'] ?? ''));

        if ($created === false || $created + ((int) Config::get('security.password_reset_ttl_minutes', 60) * 60) < time()) {
            $this->tokens->delete($email);

            throw new ValidationException(['token' => ['Tautan atur ulang sudah kedaluwarsa.']]);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            $this->tokens->delete($email);

            throw new ValidationException(['token' => ['Tautan atur ulang tidak valid.']]);
        }

        $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->users->setRememberToken((int) $user['id'], null);
        $this->tokens->delete($email);

        if ($this->session->get('auth_user_id') !== null) {
            $this->session->invalidateOthersForUser((int) $user['id'], $this->session->id());
        }
    }
}
