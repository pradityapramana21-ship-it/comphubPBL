<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\Contracts\UserRepository;

final class Auth
{
    /** @var array<string, mixed>|null */
    private ?array $cachedUser = null;

    public function __construct(
        private readonly Session $session,
        private readonly Container $container,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = $this->session->get('auth_user_id');

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = $this->container
            ->make(UserRepository::class)
            ->findActiveById((int) $id);

        return $this->cachedUser = $user;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function role(): ?\App\Enums\UserRole
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        $role = $user['role'] ?? null;

        return $role instanceof \App\Enums\UserRole
            ? $role
            : \App\Enums\UserRole::tryFrom((string) $role);
    }

    public function isAdmin(): bool
    {
        return $this->role() === \App\Enums\UserRole::Admin;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->container->make(UserRepository::class)->findActiveByEmail($email);

        if ($user === null || ! password_verify($password, (string) ($user['password'] ?? ''))) {
            return null;
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    public function login(array $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->put('auth_user_id', (int) $user['id']);
        $this->cachedUser = $user;
        $this->container->make(Csrf::class)->rotate();

        if ($remember) {
            $this->issueRememberCookie((int) $user['id']);
        }
    }

    public function logout(): void
    {
        $this->clearRememberCookie();
        $this->session->destroy();
        $this->cachedUser = null;
    }

    public function bootFromRememberCookie(): void
    {
        if ($this->session->get('auth_user_id') !== null) {
            return;
        }

        $cookie = $_COOKIE[(string) Config::get('security.remember_cookie', 'comphub_remember')] ?? null;

        if (! is_string($cookie)) {
            return;
        }

        $parts = explode(':', $cookie);

        if (count($parts) !== 3) {
            return;
        }

        [$id, $token, $signature] = $parts;

        if (! ctype_digit($id) || $token === '') {
            return;
        }

        $expected = hash_hmac('sha256', $id . ':' . $token, (string) Config::get('app.key', ''));

        if (! hash_equals($expected, $signature)) {
            $this->clearRememberCookie();
            return;
        }

        $row = $this->container->make(UserRepository::class)->findActiveById((int) $id);

        if ($row === null) {
            return;
        }

        $stored = (string) ($row['remember_token'] ?? '');

        if ($stored === '' || ! hash_equals($stored, hash('sha256', $token))) {
            $this->clearRememberCookie();
            return;
        }

        $this->session->regenerate();
        $this->session->put('auth_user_id', (int) $row['id']);
        $this->cachedUser = $row;
    }

    public function refresh(): void
    {
        $this->cachedUser = null;
    }

    private function issueRememberCookie(int $userId): void
    {
        $plain = bin2hex(random_bytes(32));
        $this->container->make(UserRepository::class)->setRememberToken($userId, hash('sha256', $plain));

        $value = $userId . ':' . $plain;
        $signature = hash_hmac('sha256', $value, (string) Config::get('app.key', ''));
        $cookie = $value . ':' . $signature;

        $name = (string) Config::get('security.remember_cookie', 'comphub_remember');
        $_COOKIE[$name] = $cookie;

        if (! headers_sent()) {
            setcookie(
                $name,
                $cookie,
                [
                    'expires' => time() + ((int) Config::get('security.remember_ttl_days', 30) * 86400),
                    'path' => '/',
                    'secure' => (bool) Config::get('security.session.secure', false),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
            );
        }
    }

    private function clearRememberCookie(): void
    {
        $id = $this->session->get('auth_user_id');

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $this->container->make(UserRepository::class)->setRememberToken((int) $id, null);
        }

        $name = (string) Config::get('security.remember_cookie', 'comphub_remember');
        unset($_COOKIE[$name]);

        if (! headers_sent()) {
            setcookie(
                $name,
                '',
                ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'],
            );
        }
    }
}
