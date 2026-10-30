<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Repositories\Contracts\CategoryRepository;

final class PdoCategoryRepository extends PdoRepository implements CategoryRepository
{
    public function allActive(): array
    {
        return $this->database->select(
            'SELECT * FROM categories WHERE is_active = TRUE ORDER BY name ASC',
        );
    }

    public function findById(int $id): ?array
    {
        return $this->findRow('categories', $id);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->database->selectOne('SELECT * FROM categories WHERE slug = :slug', ['slug' => $slug]);
    }

    public function create(array $attributes): array
    {
        $id = $this->database->insert(
            'INSERT INTO categories (name, slug, description, is_active)
             VALUES (:name, :slug, :description, :is_active) RETURNING id',
            [
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ],
        );

        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $attributes): array
    {
        return $this->updateById('categories', $id, $attributes, ['name', 'slug', 'description'])
            ?? throw new \RuntimeException('Kategori tidak ditemukan.');
    }

    public function setActive(int $id, bool $isActive): array
    {
        $this->database->execute(
            'UPDATE categories SET is_active = :active, updated_at = now() WHERE id = :id',
            ['active' => $isActive, 'id' => $id],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Kategori tidak ditemukan.');
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne('SELECT count(*) AS total FROM categories')['total'] ?? 0);
    }
}
