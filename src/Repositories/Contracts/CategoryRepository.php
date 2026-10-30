<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CategoryRepository
{
    /** @return list<array<string, mixed>> */
    public function allActive(): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function create(array $attributes): array;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function update(int $id, array $attributes): array;

    /** @return array<string, mixed> */
    public function setActive(int $id, bool $isActive): array;

    public function countAll(): int;
}
