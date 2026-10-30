<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private bool $started = false;

    public function __construct()
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            if (! isset($_SESSION)) {
                $_SESSION = [];
            }

            return;
        }

        if (headers_sent()) {
            $this->started = true;

            if (! isset($_SESSION)) {
                $_SESSION = [];
            }

            return;
        }

        $directory = dirname(__DIR__, 2) . '/storage/sessions';

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        session_save_path($directory);
        session_name((string) Config::get('app.session.name', 'comphub_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (string) Config::get('app.session.path', '/'),
            'secure' => (bool) Config::get('security.session.secure', false),
            'httponly' => true,
            'samesite' => (string) Config::get('security.session.same_site', 'Lax'),
        ]);

        session_start();
        $this->started = true;

        if (! isset($_SESSION)) {
            $_SESSION = [];
        }

    }

    public function ageFlash(): void
    {
        $this->start();
        $_SESSION['_flash'] = is_array($_SESSION['_new_flash'] ?? null) ? $_SESSION['_new_flash'] : [];
        $_SESSION['_new_flash'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(bool $destroyOld = true): void
    {
        $this->start();

        if (headers_sent()) {
            return;
        }

        session_regenerate_id($destroyOld);
    }

    public function destroy(): void
    {
        $this->start();

        $_SESSION = [];

        if (! headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public function flash(string $key, mixed $value): void
    {
        $this->start();

        if (! isset($_SESSION['_new_flash']) || ! is_array($_SESSION['_new_flash'])) {
            $_SESSION['_new_flash'] = [];
        }

        $_SESSION['_new_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        $flash = $_SESSION['_flash'] ?? [];

        return is_array($flash) && array_key_exists($key, $flash) ? $flash[$key] : $default;
    }

    public function id(): string
    {
        $this->start();

        return session_id();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $this->start();

        return $_SESSION;
    }

    public function invalidateOthersForUser(int $userId, ?string $keepSessionId = null): int
    {
        $directory = session_save_path() ?: (dirname(__DIR__, 2) . '/storage/sessions');
        $count = 0;

        foreach (glob(rtrim($directory, '/') . '/sess_*') ?: [] as $file) {
            $id = substr(basename($file), 5);

            if ($keepSessionId !== null && hash_equals($keepSessionId, $id)) {
                continue;
            }

            $contents = file_get_contents($file);

            if (is_string($contents) && preg_match('/auth_user_id\|i:' . $userId . ';/', $contents) === 1) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
