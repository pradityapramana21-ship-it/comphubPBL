<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\EventRepository;

final class BookmarkService
{
    public function __construct(
        private readonly Database $database,
        private readonly BookmarkRepository $bookmarks,
        private readonly EventRepository $events,
    ) {
    }

    public function add(int $userId, int $eventId): bool
    {
        return $this->database->transaction(function () use ($userId, $eventId): bool {
            if (! $this->bookmarks->add($userId, $eventId)) {
                return false;
            }

            $this->events->incrementBookmarkCount($eventId);

            return true;
        });
    }

    public function remove(int $userId, int $eventId): bool
    {
        return $this->database->transaction(function () use ($userId, $eventId): bool {
            if (! $this->bookmarks->remove($userId, $eventId)) {
                return false;
            }

            $this->events->decrementBookmarkCount($eventId);

            return true;
        });
    }

    public function toggle(int $userId, int $eventId): bool
    {
        if ($this->isSaved($userId, $eventId)) {
            $this->remove($userId, $eventId);

            return false;
        }

        $this->add($userId, $eventId);

        return true;
    }

    public function isSaved(int $userId, int $eventId): bool
    {
        return $this->bookmarks->exists($userId, $eventId);
    }

    /** @param list<int> $eventIds @return array<int, bool> */
    public function savedMap(int $userId, array $eventIds): array
    {
        $saved = array_fill_keys($eventIds, false);

        foreach ($this->bookmarks->eventIdsFor($userId, $eventIds) as $eventId) {
            $saved[$eventId] = true;
        }

        return $saved;
    }
}
