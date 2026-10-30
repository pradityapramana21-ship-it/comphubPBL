<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;
use App\Enums\ModerationAction;

interface ModerationLogRepository
{
    /** @param array<string, mixed>|null $metadata @return array<string, mixed> */
    public function record(
        int $eventId,
        ?int $actorUserId,
        ModerationAction $action,
        ?string $ruleKey = null,
        ?string $reason = null,
        ?int $riskScore = null,
        ?array $metadata = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
    ): array;

    /** @return list<array<string, mixed>> */
    public function forEvent(int $eventId, int $limit = 50): array;

    /** @return array<string, mixed>|null */
    public function latestForEvent(int $eventId): ?array;

    /** @param array<string, mixed> $filters */
    public function paginateForAdmin(array $filters = [], int $perPage = 25): Paginator;

    /** @return array<string, int> */
    public function countByRuleKey(?string $ruleKey = null): array;

    public function countAll(): int;
}
