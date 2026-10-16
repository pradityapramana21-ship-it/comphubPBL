<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\EventReport;
use App\Repositories\Contracts\ReportRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentReportRepository implements ReportRepository
{
    public function create(
        int $eventId,
        int $reporterUserId,
        ReportReason $reason,
        ?string $description,
    ): EventReport {
        return EventReport::create([
            'event_id' => $eventId,
            'reporter_user_id' => $reporterUserId,
            'reason_code' => $reason,
            'description' => $description,
            'status' => ReportStatus::Open,
        ]);
    }

    public function findById(int $id): ?EventReport
    {
        return EventReport::query()->with(['event', 'reporter'])->find($id);
    }

    public function findExisting(int $eventId, int $reporterUserId): ?EventReport
    {
        return EventReport::query()
            ->where('event_id', $eventId)
            ->where('reporter_user_id', $reporterUserId)
            ->first();
    }

    public function paginateOpenQueue(int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->open()
            ->with(['event.providerProfile', 'reporter'])
            ->oldest('created_at')
            ->paginate(min($perPage, 100));
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['reason'] ?? null, fn (Builder $q, string $r) => $q->where('reason_code', $r))
            ->when($filters['event_id'] ?? null, fn (Builder $q, int $id) => $q->where('event_id', $id))
            ->with(['event.providerProfile', 'reporter'])
            ->latest('created_at')
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    public function updateStatus(
        int $id,
        ReportStatus $status,
        ?string $note,
        ?int $adminUserId,
    ): EventReport {
        $report = EventReport::findOrFail($id);

        $report->forceFill([
            'status' => $status,
            'resolution_note' => $note,
            'resolved_by' => $status->isPending() ? null : $adminUserId,
            'resolved_at' => $status->isPending() ? null : now(),
        ])->save();

        return $report->refresh();
    }

    public function countOpenForEvent(int $eventId): int
    {
        return EventReport::query()
            ->where('event_id', $eventId)
            ->open()
            ->count();
    }

    public function countOpen(): int
    {
        return EventReport::query()->open()->count();
    }

    private function baseQuery(): Builder
    {
        return EventReport::query();
    }
}
