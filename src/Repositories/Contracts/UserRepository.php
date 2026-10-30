<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;
use App\Enums\AccountStatus;
use App\Enums\UserRole;

interface UserRepository
{
    /** @return array<string, mixed> */
    public function create(string $name, string $email, string $passwordHash, UserRole $role): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array;

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array;

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array;

    public function emailExists(string $email, ?int $exceptId = null): bool;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function updateProfile(int $id, array $attributes): array;

    /** @return array<string, mixed> */
    public function updateStatus(int $id, AccountStatus $status, ?string $note = null): array;

    /** @return array<string, mixed> */
    public function updateRole(int $id, UserRole $role): array;

    public function delete(int $id): void;

    /** @return array<string, mixed> */
    public function updatePassword(int $id, string $passwordHash): array;

    public function setRememberToken(int $id, ?string $tokenHash): void;

    public function markEventsSeen(int $id): void;

    /** @param array<string, mixed> $filters */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): Paginator;

    /** @return array<string, int> */
    public function countByRole(): array;

    public function countAll(): int;
}
