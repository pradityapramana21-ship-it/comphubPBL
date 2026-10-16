<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat keputusan moderasi, otomatis maupun manual.
 *
 * Bersifat append-only: tidak ada updated_at, dan koreksi dilakukan dengan
 * baris baru. Ini yang membuat "kenapa kegiatan ini ditahan?" selalu bisa
 * dijawab (Panduan §7.2).
 */
#[Fillable(['event_id', 'actor_user_id', 'action', 'rule_key', 'reason', 'risk_score', 'metadata'])]
class ModerationLog extends Model
{
    use HasFactory;

    /** Tabel log tidak pernah diubah. */
    public const UPDATED_AT = null;

    protected $table = 'moderation_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
            'metadata' => 'array',
            'risk_score' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** NULL saat yang bertindak adalah sistem, bukan manusia. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isAutomatic(): bool
    {
        return $this->actor_user_id === null;
    }

    public function actorLabel(): string
    {
        return $this->isAutomatic()
            ? 'Sistem otomatis'
            : ($this->actor?->name ?? 'Akun tidak tersedia');
    }
}
