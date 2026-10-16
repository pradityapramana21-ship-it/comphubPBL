<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepository;
use Illuminate\Support\Collection;

class EloquentCategoryRepository implements CategoryRepository
{
    public function allActive(): Collection
    {
        return Category::query()->active()->orderBy('name')->get();
    }

    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::query()->where('slug', $slug)->first();
    }

    public function create(array $attributes): Category
    {
        return Category::create($attributes);
    }

    public function update(int $id, array $attributes): Category
    {
        $category = Category::findOrFail($id);
        $category->fill($attributes)->save();

        return $category->refresh();
    }

    public function setActive(int $id, bool $isActive): Category
    {
        $category = Category::findOrFail($id);
        $category->forceFill(['is_active' => $isActive])->save();

        return $category->refresh();
    }

    public function countAll(): int
    {
        return Category::query()->count();
    }
}
