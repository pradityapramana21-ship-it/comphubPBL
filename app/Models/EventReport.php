<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Database\Factories\EventReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelaporan kegiatan oleh pengguna (FR-11).
 *
 * PRIVASI: identitas pelapor (`reporter_user_id`) tidak boleh ditampilkan
 * ke penyedia kegiatan maupun ke publik. Hanya admin yang boleh melihat
 * kolom ini (Panduan §4.5).
 */
#[Fillable([
    'event_id', 'reporter_user_id', 'reason_code', 'description',
    'status', 'resolution_note', 'resolved_by', 'resolved_at',
])]
class EventReport extends Model
{
    /** @use HasFactory<EventReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason_code' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Open->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function statusTone(): string
    {
        return $this->status->tone();
    }
}
