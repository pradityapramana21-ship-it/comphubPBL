<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('_csrf');

        if (! is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->session->put('_csrf', $token);
        }

        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    public function rotate(): void
    {
        $this->session->put('_csrf', bin2hex(random_bytes(32)));
    }

    public function verify(?string $token): bool
    {
        $known = $this->session->get('_csrf');

        return is_string($known) && is_string($token) && $token !== ''
            && hash_equals($known, $token);
    }
}
