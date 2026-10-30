<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;

interface ReportRepository
{
    /** @return array<string, mixed> */
    public function create(int $eventId, int $reporterUserId, ReportReason $reason, ?string $description): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findExisting(int $eventId, int $reporterUserId): ?array;

    public function paginateOpenQueue(int $perPage = 20): Paginator;

    /** @param array<string, mixed> $filters */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): Paginator;

    /** @return array<string, mixed> */
    public function updateStatus(int $id, ReportStatus $status, int $actorUserId, ?string $note = null): array;

    /** @return list<array<string, mixed>> */
    public function findForUser(int $userId, int $limit = 50): array;

    public function countOpenForUser(int $userId): int;

    public function countOpenForEvent(int $eventId): int;

    public function countOpen(): int;
}
