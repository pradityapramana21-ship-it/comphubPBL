<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Support\Collection;

interface CategoryRepository
{
    /** @return Collection<int, Category> */
    public function allActive(): Collection;

    public function findById(int $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function create(array $attributes): Category;

    public function update(int $id, array $attributes): Category;

    public function setActive(int $id, bool $isActive): Category;

    public function countAll(): int;
}
