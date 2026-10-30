<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\ValidationException;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\UserRepository;
use App\Support\RateLimiter;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProviderProfileRepository $providers,
        private readonly Auth $auth,
        private readonly RateLimiter $limiter,
        private readonly Session $session,
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function registerUser(array $data): array
    {
        $email = (string) $data['email'];

        if ($this->users->emailExists($email)) {
            throw new ValidationException(['email' => ['Email sudah terdaftar.']], ['name' => $data['name'] ?? '']);
        }

        return $this->users->create(
            (string) $data['name'],
            $email,
            password_hash((string) $data['password'], PASSWORD_DEFAULT),
            UserRole::User,
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    /** @return array<string, mixed> */
    public function login(string $email, string $password, bool $remember, string $ip): array
    {
        $key = 'login:' . $ip . ':' . mb_strtolower(trim($email));
        $limit = (array) Config::get('security.rate_limits.login', ['attempts' => 5, 'decay' => 900]);

        if ($this->limiter->tooManyAttempts($key, (int) $limit['attempts'])) {
            throw new HttpException(429, 'Terlalu banyak percobaan masuk. Coba lagi nanti.');
        }

        $user = $this->users->findByEmail($email);
        $valid = $user !== null && password_verify($password, (string) $user['password']);
        $status = $user === null ? null : ($user['account_status'] ?? null);
        $active = $valid && (($status instanceof AccountStatus ? $status : AccountStatus::tryFrom((string) $status)) === AccountStatus::Active);

        if (! $valid || ! $active) {
            $this->limiter->hit($key, (int) $limit['decay']);

            throw new ValidationException(['email' => ['Email atau kata sandi salah.']], ['email' => $email]);
        }

        $role = $user['role'] ?? null;
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        if (! in_array($role, [UserRole::User, UserRole::Admin], true)) {
            $this->limiter->hit($key, (int) $limit['decay']);

            throw new ValidationException(['email' => ['Email atau kata sandi salah.']], ['email' => $email]);
        }

        $this->limiter->clear($key);
        $this->auth->login($user, $remember);

        return $user;
    }

    public function logout(): void
    {
        $this->auth->logout();
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->users->findById($userId);

        if ($user === null || ! password_verify($currentPassword, (string) $user['password'])) {
            throw new ValidationException(['current_password' => ['Kata sandi saat ini salah.']]);
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));
        $this->users->setRememberToken($userId, null);
        $this->session->invalidateOthersForUser($userId, $this->session->id());
    }

    /** @return array<string, mixed>|null */
    public function providerProfile(int $userId): ?array
    {
        return $this->providers->findByUserId($userId);
    }

}
