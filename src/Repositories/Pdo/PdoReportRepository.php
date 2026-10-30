<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Repositories\Contracts\ReportRepository;

final class PdoReportRepository extends PdoRepository implements ReportRepository
{
    private const SELECT = 'SELECT r.*, e.title AS event_title, e.slug AS event_slug,
        u.name AS reporter_name, u.email AS reporter_email
        FROM event_reports r
        JOIN events e ON e.id = r.event_id
        JOIN users u ON u.id = r.reporter_user_id';

    public function create(int $eventId, int $reporterUserId, ReportReason $reason, ?string $description): array
    {
        $id = $this->database->insert(
            'INSERT INTO event_reports (event_id, reporter_user_id, reason_code, description)
             VALUES (:event_id, :reporter_user_id, :reason, :description) RETURNING id',
            [
                'event_id' => $eventId,
                'reporter_user_id' => $reporterUserId,
                'reason' => $reason->value,
                'description' => $description,
            ],
        );

        return $this->findById($id) ?? [];
    }

    public function findById(int $id): ?array
    {
        return $this->database->selectOne(self::SELECT . ' WHERE r.id = :id', ['id' => $id]);
    }

    public function findExisting(int $eventId, int $reporterUserId): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE r.event_id = :event_id AND r.reporter_user_id = :user_id',
            ['event_id' => $eventId, 'user_id' => $reporterUserId],
        );
    }

    public function paginateOpenQueue(int $perPage = 20): Paginator
    {
        $where = "r.status IN ('open', 'reviewing')";

        return $this->paginate(
            'SELECT count(*) AS total FROM event_reports r WHERE ' . $where,
            self::SELECT . ' WHERE ' . $where . ' ORDER BY CASE r.status WHEN \'open\' THEN 0 ELSE 1 END,
                r.created_at ASC',
            [],
            1,
            $perPage,
            '/admin/laporan',
        );
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 20): Paginator
    {
        $where = ['1 = 1'];
        $params = [];
        $status = (string) ($filters['status'] ?? '');

        if (in_array($status, ReportStatus::values(), true)) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        foreach (['reason' => 'r.reason_code', 'event_id' => 'r.event_id'] as $key => $column) {
            $value = (string) ($filters[$key] ?? '');

            if ($value !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total FROM event_reports r WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY r.created_at DESC, r.id DESC',
            $params,
            (int) ($filters['page'] ?? 1),
            $perPage,
            '/admin/laporan',
            array_filter($filters, static fn (mixed $v): bool => is_scalar($v) && (string) $v !== ''),
        );
    }

    public function updateStatus(int $id, ReportStatus $status, int $actorUserId, ?string $note = null): array
    {
        $resolved = in_array($status, [ReportStatus::Resolved, ReportStatus::Dismissed], true);

        $this->database->execute(
            'UPDATE event_reports SET status = :status, resolution_note = :note,
                resolved_by = :actor, resolved_at = :resolved_at, updated_at = now() WHERE id = :id',
            [
                'status' => $status->value,
                'note' => $note,
                'actor' => $resolved ? $actorUserId : null,
                'resolved_at' => $resolved ? $this->now() : null,
                'id' => $id,
            ],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Laporan tidak ditemukan.');
    }

    public function findForUser(int $userId, int $limit = 50): array
    {
        return $this->database->select(
            self::SELECT . ' WHERE r.reporter_user_id = :user_id
             ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, min(100, $limit)),
            ['user_id' => $userId],
        );
    }

    public function countOpenForUser(int $userId): int
    {
        return (int) ($this->database->selectOne(
            "SELECT count(*) AS total FROM event_reports
             WHERE reporter_user_id = :user_id AND status IN ('open', 'reviewing')",
            ['user_id' => $userId],
        )['total'] ?? 0);
    }

    public function countOpenForEvent(int $eventId): int
    {
        return (int) ($this->database->selectOne(
            "SELECT count(*) AS total FROM event_reports
             WHERE event_id = :event_id AND status IN ('open', 'reviewing')",
            ['event_id' => $eventId],
        )['total'] ?? 0);
    }

    public function countOpen(): int
    {
        return (int) ($this->database->selectOne(
            "SELECT count(*) AS total FROM event_reports WHERE status IN ('open', 'reviewing')",
        )['total'] ?? 0);
    }
}
