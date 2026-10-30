<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface PasswordResetRepository
{
    public function put(string $email, string $tokenHash): void;

    /** @return array<string, mixed>|null */
    public function find(string $email): ?array;

    public function delete(string $email): void;
}
