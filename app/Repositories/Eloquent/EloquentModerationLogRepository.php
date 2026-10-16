<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ModerationAction;
use App\Models\ModerationLog;
use App\Repositories\Contracts\ModerationLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentModerationLogRepository implements ModerationLogRepository
{
    /**
     * Kunci yang tidak boleh pernah masuk ke metadata, apa pun nilainya.
     *
     * Lapisan pengaman terakhir sebelum log ditulis permanen. Pemanggil
     * seharusnya sudah menyaring sendiri; daftar ini menangkap kelalaian
     * (Panduan §8: jangan log password/token).
     *
     * @var list<string>
     */
    private const FORBIDDEN_KEYS = [
        'password', 'password_hash', 'password_confirmation',
        'token', 'remember_token', 'access_token', 'api_key', 'secret',
        'authorization', 'dsn', 'credential', 'verification_document_path',
    ];

    public function record(
        int $eventId,
        ModerationAction $action,
        ?string $ruleKey = null,
        ?string $reason = null,
        ?int $riskScore = null,
        ?int $actorUserId = null,
        array $metadata = [],
    ): ModerationLog {
        return ModerationLog::create([
            'event_id' => $eventId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'rule_key' => $ruleKey,
            'reason' => $reason,
            'risk_score' => $riskScore,
            'metadata' => $this->sanitise($metadata),
        ]);
    }

    public function forEvent(int $eventId, int $limit = 50): Collection
    {
        return ModerationLog::query()
            ->where('event_id', $eventId)
            ->with('actor')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function latestForEvent(int $eventId): ?ModerationLog
    {
        return ModerationLog::query()
            ->where('event_id', $eventId)
            ->with('actor')
            ->latest('created_at')
            ->first();
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ModerationLog::query()
            ->with(['event', 'actor'])
            ->when($filters['rule_key'] ?? null, fn (Builder $q, string $k) => $q->where('rule_key', $k))
            ->when($filters['action'] ?? null, fn (Builder $q, string $a) => $q->where('action', $a))
            ->when($filters['event_id'] ?? null, fn (Builder $q, int $id) => $q->where('event_id', $id))
            ->latest('created_at')
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    public function countSubmissionsSince(int $providerProfileId, \DateTimeInterface $since): int
    {
        return ModerationLog::query()
            ->where('action', ModerationAction::Submit->value)
            ->whereHas('event', fn (Builder $q) => $q->where('provider_profile_id', $providerProfileId))
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function countByRuleKey(?string $ruleKey = null): array
    {
        return ModerationLog::query()
            ->when($ruleKey !== null, fn (Builder $q) => $q->where('rule_key', $ruleKey))
            ->whereNotNull('rule_key')
            ->selectRaw('rule_key, count(*) as total')
            ->groupBy('rule_key')
            ->pluck('total', 'rule_key')
            ->all();
    }

    public function countAll(): int
    {
        return ModerationLog::query()->count();
    }

    /**
     * Buang kunci sensitif, rekursif. Nilai yang sesuai diganti penanda agar
     * jelas bahwa ada sesuatu yang sengaja tidak direkam.
     *
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
