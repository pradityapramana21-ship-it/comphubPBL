<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModerationAction;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\ModerationVerdict;
use App\Services\Moderation\RuleResult;

/**
 * Otak mesin moderasi: menjalankan seluruh aturan, menjumlahkan skor, dan
 * memutuskan status awal sebuah pengajuan.
 *
 * ATURAN YANG PALING PENTING DI KELAS INI:
 *
 *   Tidak ada jalur yang menghasilkan status Approved.
 *
 * Satu-satunya hasil yang mungkin adalah Pending, Pending dengan penanda
 * prioritas, atau Rejected otomatis. Menyetujui kegiatan tetap keputusan
 * manusia (Panduan §1.4, §7.4). Kalau versi ini sampai bisa auto-approve,
 * maka seluruh pembahasan tentang gerbang persetujuan di laporan ini jadi
 * tidak bermakna.
 */
class ModerationService
{
    /** @var list<ModerationRule> */
    private readonly array $rules;

    public function __construct(
        private readonly ModerationLogRepository $moderationLogs,
        private readonly EventRepository $events,
        ModerationRule ...$rules,
    ) {
        $this->rules = $rules;
    }

    /**
     * Periksa sebuah pengajuan tanpa menyimpan apa pun.
     *
     * Dipakai form penyuntingan untuk menampilkan pratinjau risiko sebelum
     * penyedia menekan tombol kirim, dan dipakai test.
     */
    public function inspect(ModerationContext $context): ModerationVerdict
    {
        $results = [];

        foreach ($this->rules as $rule) {
            $result = $rule->evaluate($context);

            if ($result instanceof RuleResult) {
                $results[] = $result;
            }
        }

        return $this->verdictFrom($results);
    }

    /**
     * Periksa lalu catat hasilnya ke moderation_logs.
     *
     * Tidak pernah mengubah status kegiatan — pemanggil yang memutuskan,
     * supaya pemanggilan ini aman dari form penyuntingan.
     */
    public function inspectAndLog(ModerationContext $context, ?int $eventId = null): ModerationVerdict
    {
        $verdict = $this->inspect($context);

        $loggableId = $eventId ?? $context->eventId;

        if ($loggableId === null) {
            return $verdict;
        }

        foreach ($verdict->results() as $result) {
            $this->moderationLogs->record(
                eventId: $loggableId,
                action: $verdict->autoRejected ? ModerationAction::AutoRejected : ModerationAction::AutoFlagged,
                ruleKey: $result->ruleKey,
                reason: $result->reason,
                riskScore: $verdict->totalScore,
                actorUserId: null,
                metadata: $result->metadata,
            );
        }

        if ($verdict->results() === []) {
            // Selalu ada satu jejak, supaya riwayat pengajuan tidak pernah
            // kosong walau tidak ada temuan.
            $this->moderationLogs->record(
                eventId: $loggableId,
                action: ModerationAction::AutoFlagged,
                ruleKey: null,
                reason: 'Pemeriksaan otomatis: tidak ada tanda mencurigakan.',
                riskScore: $verdict->totalScore,
                actorUserId: null,
                metadata: ['clean' => true],
            );
        }

        return $verdict;
    }

    /**
     * Status awal untuk pengajuan baru.
     */
    public function statusForNewSubmission(ModerationContext $context): array
    {
        $verdict = $this->inspect($context);

        return [
            'status' => $verdict->resultingStatus(),
            'risk_score' => $verdict->totalScore,
            'verdict' => $verdict,
        ];
    }

    /**
     * @param  list<RuleResult>  $results
     */
    private function verdictFrom(array $results): ModerationVerdict
    {
        $score = 0;

        foreach ($results as $result) {
            $score += $result->score;
        }

        $cap = (int) config('moderation.thresholds.cap', 100);
        $score = max(0, min($score, $cap));

        $rejectAt = (int) config('moderation.thresholds.reject', 60);
        $flagAt = (int) config('moderation.thresholds.flag', 25);

        return new ModerationVerdict(
            totalScore: $score,
            results: $results,
            autoRejected: $score >= $rejectAt,
            flagged: $score >= $flagAt && $score < $rejectAt,
        );
    }

    /**
     * @return list<string> kunci aturan yang aktif, untuk dokumentasi
     */
    public function registeredRuleKeys(): array
    {
        return array_map(static fn (ModerationRule $rule): string => $rule->key(), $this->rules);
    }

    public function hasRule(string $key): bool
    {
        return in_array($key, $this->registeredRuleKeys(), true);
    }
}
