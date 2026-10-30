<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\Repositories\Contracts\BookmarkRepository;

final class PdoBookmarkRepository extends PdoRepository implements BookmarkRepository
{
    public function add(int $userId, int $eventId): bool
    {
        return $this->database->execute(
            'INSERT INTO bookmarks (user_id, event_id) VALUES (:user_id, :event_id)
             ON CONFLICT (user_id, event_id) DO NOTHING',
            ['user_id' => $userId, 'event_id' => $eventId],
        ) > 0;
    }

    public function remove(int $userId, int $eventId): bool
    {
        return $this->database->execute(
            'DELETE FROM bookmarks WHERE user_id = :user_id AND event_id = :event_id',
            ['user_id' => $userId, 'event_id' => $eventId],
        ) > 0;
    }

    public function exists(int $userId, int $eventId): bool
    {
        return $this->database->selectOne(
            'SELECT 1 FROM bookmarks WHERE user_id = :user_id AND event_id = :event_id',
            ['user_id' => $userId, 'event_id' => $eventId],
        ) !== null;
    }

    public function eventIdsFor(int $userId, array $eventIds): array
    {
        $eventIds = array_values(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0));

        if ($eventIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['user_id' => $userId];

        foreach ($eventIds as $index => $eventId) {
            $placeholders[] = ':event_' . $index;
            $params['event_' . $index] = $eventId;
        }

        $rows = $this->database->select(
            'SELECT event_id FROM bookmarks WHERE user_id = :user_id
             AND event_id IN (' . implode(', ', $placeholders) . ')',
            $params,
        );

        return array_map(static fn (array $row): int => (int) $row['event_id'], $rows);
    }

    public function paginateForUser(int $userId, int $perPage = 12, int $page = 1): Paginator
    {
        $where = "b.user_id = :user_id AND e.status = 'approved' AND e.archived_at IS NULL
            AND c.is_active = TRUE AND p.verification_status = 'verified'";
        $from = ' FROM bookmarks b
            JOIN events e ON e.id = b.event_id
            JOIN categories c ON c.id = e.category_id
            JOIN provider_profiles p ON p.id = e.provider_profile_id';

        return $this->paginate(
            'SELECT count(*) AS total' . $from . ' WHERE ' . $where,
            'SELECT e.*, c.name AS category_name, c.slug AS category_slug,
                p.organization_name, p.campus_name, b.created_at AS saved_at' . $from . '
             WHERE ' . $where . ' ORDER BY b.created_at DESC',
            ['user_id' => $userId],
            $page,
            $perPage,
            '/tersimpan',
        );
    }

    public function countForUser(int $userId): int
    {
        return (int) ($this->database->selectOne(
            'SELECT count(*) AS total FROM bookmarks WHERE user_id = :user_id',
            ['user_id' => $userId],
        )['total'] ?? 0);
    }

    public function countForEvent(int $eventId): int
    {
        return (int) ($this->database->selectOne(
            'SELECT count(*) AS total FROM bookmarks WHERE event_id = :event_id',
            ['event_id' => $eventId],
        )['total'] ?? 0);
    }

    public function removeForEvent(int $eventId): int
    {
        return $this->database->execute('DELETE FROM bookmarks WHERE event_id = :event_id', ['event_id' => $eventId]);
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne('SELECT count(*) AS total FROM bookmarks')['total'] ?? 0);
    }
}
