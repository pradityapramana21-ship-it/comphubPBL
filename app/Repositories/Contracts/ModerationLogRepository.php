<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ModerationAction;
use App\Models\ModerationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ModerationLogRepository
{
    /**
     * Tulis satu entri. `metadata` harus sudah disaring — tidak boleh memuat
     * password, token, atau isi form sensitif (Panduan §8).
     */
    public function record(
        int $eventId,
        ModerationAction $action,
        ?string $ruleKey = null,
        ?string $reason = null,
        ?int $riskScore = null,
        ?int $actorUserId = null,
        array $metadata = [],
    ): ModerationLog;

    /** Riwayat satu kegiatan, terbaru di atas. */
    public function forEvent(int $eventId, int $limit = 50): Collection;

    public function latestForEvent(int $eventId): ?ModerationLog;

    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /** Berapa kali satu provider sudah mengajukan, untuk aturan rate limit. */
    public function countSubmissionsSince(int $providerProfileId, \DateTimeInterface $since): int;

    public function countByRuleKey(?string $ruleKey = null): array;

    public function countAll(): int;
}
