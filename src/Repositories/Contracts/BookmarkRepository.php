<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;

interface BookmarkRepository
{
    public function add(int $userId, int $eventId): bool;

    public function remove(int $userId, int $eventId): bool;

    public function exists(int $userId, int $eventId): bool;

    /** @param list<int> $eventIds @return list<int> */
    public function eventIdsFor(int $userId, array $eventIds): array;

    public function paginateForUser(int $userId, int $perPage = 12, int $page = 1): Paginator;

    public function countForUser(int $userId): int;

    public function countForEvent(int $eventId): int;

    public function removeForEvent(int $eventId): int;

    public function countAll(): int;
}
