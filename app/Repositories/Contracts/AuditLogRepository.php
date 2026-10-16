<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Jejak aksi sensitif.
 *
 * Antarmuka ini SENGAJA tidak punya find/delete: audit log hanya boleh
 * ditambah dan dibaca. Menghapus jejaknya berarti menghapus bukti.
 */
interface AuditLogRepository
{
    public function record(
        string $action,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
    ): AuditLog;

    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function forTarget(string $targetType, int $targetId, int $limit = 50): Collection;

    public function forActor(int $actorUserId, int $limit = 50): Collection;

    public function countAll(): int;
}
