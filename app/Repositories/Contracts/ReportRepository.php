<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\EventReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReportRepository
{
    /** Identitas pelapor hanya boleh dibaca lewat interface ini, untuk keperluan audit admin. */
    public function create(int $eventId, int $reporterUserId, ReportReason $reason, ?string $description): EventReport;

    public function findById(int $id): ?EventReport;

    /** Kalau pelapor sudah pernah melapor kegiatan ini, kembalikan laporan lama. */
    public function findExisting(int $eventId, int $reporterUserId): ?EventReport;

    public function paginateOpenQueue(int $perPage = 20): LengthAwarePaginator;

    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function updateStatus(
        int $id,
        ReportStatus $status,
        ?string $note,
        ?int $adminUserId,
    ): EventReport;

    /** Jumlah laporan terbuka untuk sebuah kegiatan —badge di panel admin. */
    public function countOpenForEvent(int $eventId): int;

    public function countOpen(): int;
}
