<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventStatus;
use App\Repositories\Contracts\EventRepository;
use App\Support\DateHelper;

final class PdoEventRepository extends PdoRepository implements EventRepository
{
    private const SELECT = 'SELECT e.*, c.name AS category_name, c.slug AS category_slug,
        p.organization_name, p.campus_name, p.user_id AS provider_user_id,
        p.verification_status AS provider_verification_status
        FROM events e
        JOIN categories c ON c.id = e.category_id
        JOIN provider_profiles p ON p.id = e.provider_profile_id';

    private const FROM = ' FROM events e
        JOIN categories c ON c.id = e.category_id
        JOIN provider_profiles p ON p.id = e.provider_profile_id';

    public function paginatePublicCatalog(CatalogFilter $filter): Paginator
    {
        $where = ["e.status = 'approved'", 'e.archived_at IS NULL', 'c.is_active = TRUE',
            "p.verification_status = 'verified'"];
        $params = [];

        if ($filter->status === 'closed') {
            $where[] = 'e.registration_deadline < now()';
        } elseif ($filter->status === 'upcoming') {
            $where[] = 'e.registration_start_at IS NOT NULL AND e.registration_start_at > now()';
        } elseif ($filter->status === 'open') {
            $where[] = '(e.registration_start_at IS NULL OR e.registration_start_at <= now())';
            $where[] = 'e.registration_deadline >= now()';
        }

        $this->applyPublicFilters($where, $params, $filter);
        $clause = implode(' AND ', $where);
        $order = $this->order($filter->sort);

        return $this->paginate(
            'SELECT count(*) AS total' . self::FROM . ' WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY ' . $order,
            $params,
            $filter->page,
            12,
            '/kegiatan',
            $filter->query(),
        );
    }

    public function findLatestOpen(int $limit): array
    {
        return $this->database->select(
            self::SELECT . " WHERE e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
                AND p.verification_status = 'verified'
                AND (e.registration_start_at IS NULL OR e.registration_start_at <= now())
                AND e.registration_deadline >= now()
                ORDER BY e.published_at DESC, e.id DESC LIMIT " . max(1, min(50, $limit)),
            [],
        );
    }

    public function findDeadlineSoon(int $limit): array
    {
        return $this->database->select(
            self::SELECT . " WHERE e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
                AND p.verification_status = 'verified'
                AND (e.registration_start_at IS NULL OR e.registration_start_at <= now())
                AND e.registration_deadline >= now()
                AND e.registration_deadline <= now() + interval '14 days'
                ORDER BY e.registration_deadline ASC, e.id ASC LIMIT " . max(1, min(50, $limit)),
            [],
        );
    }

    public function findApprovedByProvider(int $providerProfileId, int $limit = 50): array
    {
        return $this->database->select(
            self::SELECT . " WHERE e.provider_profile_id = :provider_id
                AND e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
                AND p.verification_status = 'verified'
                ORDER BY e.registration_deadline >= now() DESC, e.published_at DESC, e.id DESC LIMIT "
                . max(1, min(100, $limit)),
            ['provider_id' => $providerProfileId],
        );
    }

    public function paginateForProvider(int $providerProfileId, CatalogFilter $filter): Paginator
    {
        $where = ['e.provider_profile_id = :provider_id'];
        $params = ['provider_id' => $providerProfileId];

        if (in_array($filter->status, EventStatus::values(), true)) {
            $where[] = 'e.status = :status';
            $params['status'] = $filter->status;
        }

        if ($filter->q !== '') {
            $where[] = 'e.title ILIKE :keyword';
            $params['keyword'] = '%' . $filter->q . '%';
        }

        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total' . self::FROM . ' WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY e.updated_at DESC, e.id DESC',
            $params,
            $filter->page,
            15,
            '/penyedia/kegiatan',
            $filter->query(),
        );
    }

    public function paginateForAdmin(CatalogFilter $filter, ?int $providerProfileId = null): Paginator
    {
        $where = ['1 = 1'];
        $params = [];

        if (in_array($filter->status, EventStatus::values(), true)) {
            $where[] = 'e.status = :status';
            $params['status'] = $filter->status;
        }

        if ($providerProfileId !== null) {
            $where[] = 'e.provider_profile_id = :provider_id';
            $params['provider_id'] = $providerProfileId;
        }

        $this->applyPublicFilters($where, $params, $filter);
        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total' . self::FROM . ' WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY e.created_at DESC, e.id DESC',
            $params,
            $filter->page,
            20,
            '/admin/kegiatan',
            $filter->query(),
        );
    }

    public function paginateReviewQueue(int $perPage = 20): Paginator
    {
        $where = "e.status IN ('pending', 'changes_requested')";

        return $this->paginate(
            'SELECT count(*) AS total' . self::FROM . ' WHERE ' . $where,
            self::SELECT . ' WHERE ' . $where . ' ORDER BY e.risk_score DESC, e.submitted_at ASC, e.id ASC',
            [],
            1,
            $perPage,
            '/admin/moderasi',
        );
    }

    public function create(array $attributes): array
    {
        $allowed = [
            'provider_profile_id', 'category_id', 'title', 'slug', 'description', 'requirements',
            'registration_url', 'mode', 'location_text', 'platform_url', 'event_start_at',
            'event_end_at', 'registration_deadline', 'deadline_confirmed', 'status', 'risk_score', 'submitted_at',
            'published_at', 'archived_at', 'rejection_reason', 'organizer_name', 'source_url',
            'source_checked_at', 'source_note', 'event_type', 'registration_start_at', 'poster_path',
            'guidebook_path', 'organizer_email', 'contact_number', 'revision_token_hash',
            'revision_token_expires_at', 'revision_token_revoked_at', 'revision_token_last_used_at',
        ];
        $data = array_intersect_key($attributes, array_flip($allowed));
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $id = $this->database->insert(
            'INSERT INTO events (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ') RETURNING id',
            $data,
        );

        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $attributes): array
    {
        $allowed = [
            'provider_profile_id', 'category_id', 'title', 'slug', 'description', 'requirements',
            'registration_url', 'mode', 'location_text', 'platform_url', 'event_start_at', 'event_end_at',
            'registration_deadline', 'deadline_confirmed', 'status', 'risk_score', 'submitted_at', 'published_at',
            'archived_at', 'rejection_reason', 'organizer_name', 'source_url',
            'source_checked_at', 'source_note', 'event_type', 'registration_start_at', 'poster_path',
            'guidebook_path', 'organizer_email', 'contact_number', 'revision_token_hash',
            'revision_token_expires_at', 'revision_token_revoked_at', 'revision_token_last_used_at',
        ];

        return $this->updateById('events', $id, $attributes, $allowed)
            ?? throw new \RuntimeException('Kegiatan tidak ditemukan.');
    }

    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM events WHERE id = :id', ['id' => $id]);
    }

    public function archive(int $id): void
    {
        $this->database->execute(
            "UPDATE events SET status = 'archived', archived_at = now(), updated_at = now() WHERE id = :id",
            ['id' => $id],
        );
    }

    public function availableMonths(): array
    {
        $rows = $this->database->select(
            "SELECT DISTINCT to_char(e.event_start_at AT TIME ZONE 'Asia/Jakarta', 'YYYY-MM') AS value
             FROM events e
             JOIN categories c ON c.id = e.category_id
             JOIN provider_profiles p ON p.id = e.provider_profile_id
             WHERE e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
               AND p.verification_status = 'verified'
             ORDER BY value ASC",
        );
        $dates = new DateHelper();
        $months = [];

        foreach ($rows as $row) {
            $value = (string) $row['value'];
            $months[] = ['value' => $value, 'label' => $dates->monthLabel($value)];
        }

        return $months;
    }

    public function countSubmissionsSince(int $providerProfileId, \DateTimeInterface $since): int
    {
        return (int) ($this->database->selectOne(
            'SELECT count(*) AS total FROM events
             WHERE provider_profile_id = :provider_id AND submitted_at IS NOT NULL
               AND submitted_at >= :since',
            [
                'provider_id' => $providerProfileId,
                'since' => $since->format('Y-m-d H:i:sP'),
            ],
        )['total'] ?? 0);
    }

    public function findPossibleDuplicates(int $providerProfileId, string $title, \DateTimeInterface $startDate): array
    {
        $start = $startDate->format('Y-m-d H:i:sP');
        $from = (new \DateTimeImmutable($start))->modify('-3 days')->format('Y-m-d H:i:sP');
        $to = (new \DateTimeImmutable($start))->modify('+3 days')->format('Y-m-d H:i:sP');

        return $this->database->select(
            self::SELECT . " WHERE e.provider_profile_id = :provider_id
                AND e.status <> 'archived'
                AND e.event_start_at BETWEEN :from AND :to
                ORDER BY e.event_start_at ASC LIMIT 20",
            ['provider_id' => $providerProfileId, 'from' => $from, 'to' => $to],
        );
    }

    public function incrementBookmarkCount(int $id): void
    {
        $this->database->execute(
            'UPDATE events SET bookmark_count = bookmark_count + 1, updated_at = now() WHERE id = :id',
            ['id' => $id],
        );
    }

    public function decrementBookmarkCount(int $id): void
    {
        $this->database->execute(
            'UPDATE events SET bookmark_count = GREATEST(0, bookmark_count - 1), updated_at = now()
             WHERE id = :id',
            ['id' => $id],
        );
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne('SELECT count(*) AS total FROM events')['total'] ?? 0);
    }

    public function countOpen(): int
    {
        return (int) ($this->database->selectOne(
            "SELECT count(*) AS total" . self::FROM . "
             WHERE e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
               AND p.verification_status = 'verified'
               AND (e.registration_start_at IS NULL OR e.registration_start_at <= now())
               AND e.registration_deadline >= now()",
        )['total'] ?? 0);
    }

    public function countByStatus(): array
    {
        $rows = $this->database->select('SELECT status, count(*) AS total FROM events GROUP BY status');
        $counts = array_fill_keys(EventStatus::values(), 0);

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function findApprovedBySlug(string $slug): ?array
    {
        return $this->database->selectOne(
            self::SELECT . " WHERE e.slug = :slug AND e.status = 'approved' AND e.archived_at IS NULL
                AND c.is_active = TRUE AND p.verification_status = 'verified'",
            ['slug' => $slug],
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE e.slug = :slug',
            ['slug' => $slug],
        );
    }

    public function countNewSince(string $since): int
    {
        return (int) ($this->database->selectOne(
            "SELECT count(*) AS total" . self::FROM . "
             WHERE e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
               AND p.verification_status = 'verified' AND e.published_at > :since",
            ['since' => $since],
        )['total'] ?? 0);
    }

    public function findNewSince(string $since, int $limit = 50): array
    {
        return $this->database->select(
            self::SELECT . " WHERE e.status = 'approved' AND e.archived_at IS NULL AND c.is_active = TRUE
                AND p.verification_status = 'verified'
                AND e.published_at > :since
                ORDER BY e.published_at DESC, e.id DESC LIMIT " . max(1, min(100, $limit)),
            ['since' => $since],
        );
    }

    public function findByIdForProvider(int $id, int $providerProfileId): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE e.id = :id AND e.provider_profile_id = :provider_id',
            ['id' => $id, 'provider_id' => $providerProfileId],
        );
    }

    public function findById(int $id): ?array
    {
        return $this->database->selectOne(self::SELECT . ' WHERE e.id = :id', ['id' => $id]);
    }

    public function incrementViewCount(int $id): void
    {
        $this->database->execute('UPDATE events SET view_count = view_count + 1 WHERE id = :id', ['id' => $id]);
    }

    /** @param list<string> $where @param array<string, mixed> $params */
    private function applyPublicFilters(array &$where, array &$params, CatalogFilter $filter): void
    {
        if ($filter->q !== '') {
            $where[] = '(e.title ILIKE :keyword OR e.description ILIKE :keyword
                OR p.organization_name ILIKE :keyword OR p.campus_name ILIKE :keyword)';
            $params['keyword'] = '%' . $filter->q . '%';
        }

        if ($filter->category !== '') {
            $where[] = 'c.slug = :category';
            $params['category'] = $filter->category;
        }

        if (in_array($filter->mode, ['online', 'offline', 'hybrid'], true)) {
            $where[] = 'e.mode = :mode';
            $params['mode'] = $filter->mode;
        }

        if (in_array($filter->type, \App\Enums\EventType::values(), true)) {
            $where[] = 'e.event_type = :event_type';
            $params['event_type'] = $filter->type;
        }

        if ($filter->month !== '') {
            $start = new \DateTimeImmutable($filter->month . '-01 00:00:00', new \DateTimeZone('Asia/Jakarta'));
            $where[] = 'e.event_start_at >= :month_start AND e.event_start_at < :month_end';
            $params['month_start'] = $start->format('Y-m-d H:i:sP');
            $params['month_end'] = $start->modify('+1 month')->format('Y-m-d H:i:sP');
        }
    }

    private function order(string $sort): string
    {
        return match ($sort) {
            'deadline' => 'e.registration_deadline ASC, e.id ASC',
            'popular' => 'e.bookmark_count DESC, e.published_at DESC, e.id DESC',
            default => 'e.published_at DESC, e.id DESC',
        };
    }
}
