<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, string|int> $query
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $page,
        public readonly string $path,
        public readonly array $query = [],
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        return min($this->page * $this->perPage, $this->total);
    }

    public function url(int $page): string
    {
        $query = $this->query;

        if ($page > 1) {
            $query['page'] = $page;
        } else {
            unset($query['page']);
        }

        $queryString = http_build_query($query);

        return $this->path . ($queryString !== '' ? '?' . $queryString : '');
    }
}
