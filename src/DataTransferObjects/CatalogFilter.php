<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Core\Request;

final class CatalogFilter
{
    public function __construct(
        public readonly string $q = '',
        public readonly string $category = '',
        public readonly string $mode = '',
        public readonly string $status = 'open',
        public readonly string $sort = 'newest',
        public readonly int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $q = trim((string) $request->input('q', ''));
        $q = mb_substr($q, 0, 100);
        $category = (string) $request->input('category', '');
        $mode = (string) $request->input('mode', '');
        $status = (string) $request->input('status', 'open');
        $sort = (string) $request->input('sort', 'newest');
        $page = max(1, (int) $request->input('page', 1));

        if (preg_match('/^[a-z0-9-]{1,80}$/', $category) !== 1) {
            $category = '';
        }

        if (! in_array($mode, ['online', 'offline', 'hybrid'], true)) {
            $mode = '';
        }

        if (! in_array($status, ['open', 'closed', ...\App\Enums\EventStatus::values()], true)) {
            $status = 'open';
        }

        if (! in_array($sort, ['newest', 'deadline', 'popular'], true)) {
            $sort = 'newest';
        }

        return new self($q, $category, $mode, $status, $sort, $page);
    }

    /** @return array<string, string|int> */
    public function query(): array
    {
        return array_filter([
            'q' => $this->q,
            'category' => $this->category,
            'mode' => $this->mode,
            'status' => $this->status !== 'open' ? $this->status : '',
            'sort' => $this->sort !== 'newest' ? $this->sort : '',
        ], static fn (mixed $value): bool => $value !== '' && $value !== null);
    }

    public function isDefault(): bool
    {
        return $this->q === '' && $this->category === '' && $this->mode === ''
            && $this->status === 'open' && $this->sort === 'newest';
    }
}
