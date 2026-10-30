<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;

interface ProviderProfileRepository
{
    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function create(array $attributes): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array;

    public function paginateVerificationQueue(int $perPage = 20): Paginator;

    public function paginateForAdmin(?string $keyword = null, string $status = '', int $perPage = 20, int $page = 1): Paginator;

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array;

    /** @return array<string, mixed>|null */
    public function findVerifiedById(int $id): ?array;

    public function paginatePublicDirectory(?string $keyword = null, int $perPage = 12): Paginator;

    /** @return array<string, mixed>|null */
    public function findVerifiedBySlug(string $slug): ?array;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function update(int $id, array $attributes): array;

    public function countAll(): int;

    /** @return array<string, int> */
    public function countByVerificationStatus(): array;
}
