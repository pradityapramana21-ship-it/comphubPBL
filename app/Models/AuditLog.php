<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Jejak aksi sensitif: persetujuan, penolakan, perubahan role, penangguhan.
 *
 * Sengaja TIDAK punya relasi ke User. Kalau akun admin dihapus, jejaknya
 * harus tetap bisa dibaca — itulah bedanya tabel ini dengan `moderation_logs`.
 * Karena itu `actor_user_id` hanya bigint biasa, bukan foreign key.
 *
 * Aturan isi (Panduan §8): JANGAN pernah menyimpan password, token sesi,
 * kunci API, atau isi form sensitif di `metadata`. Penyaringan dilakukan
 * oleh AuditLogService, bukan oleh pemanggil.
 */
#[Fillable(['actor_user_id', 'actor_role', 'action', 'target_type', 'target_id', 'metadata'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeForTarget(Builder $query, string $type, int $id): Builder
    {
        return $query->where('target_type', $type)->where('target_id', $id);
    }

    /**
     * Nama target yang enak dibaca, tanpa memuat objek User (yang mungkin
     * sudah tidak ada lagi).
     */
    public function targetLabel(): string
    {
        $type = class_basename((string) $this->target_type);

        return $this->target_id === null
            ? ($type !== '' ? $type : '—')
            : $type.' #'.$this->target_id;
    }
}
