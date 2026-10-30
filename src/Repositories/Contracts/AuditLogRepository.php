<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;

interface AuditLogRepository
{
    /** @param array<string, mixed>|null $metadata @return array<string, mixed> */
    public function record(
        ?int $actorUserId,
        ?string $actorRole,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array;

    /** @param array<string, mixed> $filters */
    public function paginateForAdmin(array $filters = [], int $perPage = 25): Paginator;

    /** @return list<array<string, mixed>> */
    public function forTarget(string $targetType, int $targetId, int $limit = 50): array;

    /** @return list<array<string, mixed>> */
    public function forActor(int $actorUserId, int $limit = 50): array;

    public function countAll(): int;
}
