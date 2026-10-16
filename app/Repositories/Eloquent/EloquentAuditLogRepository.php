<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Penulisan jejak audit.
 *
 * Metode publik hanya `record` dan pembacaan — tidak ada cara menghapus atau
 * mengubah lewat antarmuka ini. Penghapusan log harus dilakukan langsung di
 * database oleh orang yang benar-benar bertanggung jawab, bukan dari kode
 * aplikasi (Panduan §2: admin "mengubah data produksi tanpa alasan dan
 * pencatatan" adalah pelanggaran).
 */
class EloquentAuditLogRepository implements AuditLogRepository
{
    /** @var list<string> */
    private const FORBIDDEN_KEYS = [
        'password', 'password_hash', 'password_confirmation', 'current_password',
        'token', 'remember_token', 'access_token', 'api_key', 'secret',
        'authorization', 'dsn', 'credentials', 'verification_document_path',
        // Email ikut disaring: jejak audit cukup menyimpan ID, bukan alamat.
        'email',
    ];

    public function record(
        string $action,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $this->sanitise($metadata),
        ]);
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return AuditLog::query()
            ->when($filters['action'] ?? null, fn (Builder $q, string $a) => $q->where('action', $a))
            ->when($filters['actor_user_id'] ?? null, fn (Builder $q, int $id) => $q->where('actor_user_id', $id))
            ->when($filters['target_type'] ?? null, fn (Builder $q, string $t) => $q->where('target_type', $t))
            ->when($filters['target_id'] ?? null, fn (Builder $q, int $id) => $q->where('target_id', $id))
            ->latest('created_at')
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    public function forTarget(string $targetType, int $targetId, int $limit = 50): Collection
    {
        return AuditLog::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function forActor(int $actorUserId, int $limit = 50): Collection
    {
        return AuditLog::query()
            ->where('actor_user_id', $actorUserId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function countAll(): int
    {
        return AuditLog::query()->count();
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<array-key, mixed>
     */
    private function sanitise(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                $clean[$key] = '[disembunyikan]';
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitise($value) : $value;
        }

        return $clean;
    }
}
