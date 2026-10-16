<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\EventStatus;
use App\Enums\RuleSeverity;

/**
 * Hasil lengkap pemeriksaan satu pengajuan.
 *
 * Yang penting: tidak ada keputusan "approved" di sini. Verdict hanya bisa
 * punya dua kemungkinan — masuk antrean biasa, masuk antrean prioritas, atau
 * ditolak otomatis. Menyetujui kegiatan tetap keputusan manusia
 * (Panduan §7.4).
 */
final readonly class ModerationVerdict
{
    /**
     * @param  list<RuleResult>  $results
     */
    public function __construct(
        public int $totalScore,
        public array $results,
        public bool $autoRejected,
        public bool $flagged,
    ) {}

    /** @return list<RuleResult> */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Status yang mungkin dihasilkan mesin. Selalu Pending atau Rejected.
     */
    public function resultingStatus(): EventStatus
    {
        return $this->autoRejected ? EventStatus::Rejected : EventStatus::Pending;
    }

    /**
     * Ringkasan satu kalimat untuk ditampilkan ke admin di antrean.
     */
    public function summary(): string
    {
        if ($this->results === []) {
            return 'Tidak ada tanda mencurigakan. Menunggu pemeriksaan admin.';
        }

        if ($this->autoRejected) {
            return sprintf(
                'Ditolak otomatis (skor %d): %s',
                $this->totalScore,
                $this->topReasons(),
            );
        }

        if ($this->flagged) {
            return sprintf(
                'Prioritas tinjauan (skor %d): %s',
                $this->totalScore,
                $this->topReasons(),
            );
        }

        return sprintf('Skor %d. Menunggu pemeriksaan admin.', $this->totalScore);
    }

    /**
     * Alasan yang layak ditampilkan, diurutkan dari yang paling serius.
     * Dibatasi lima supaya admin tidak-optional diberi tembok teks.
     */
    public function topReasons(int $limit = 5): string
    {
        $sorted = $this->sortedBySeverity();

        if ($sorted === []) {
            return 'nihil';
        }

        return implode('; ', array_map(
            static fn (RuleResult $r): string => $r->reason,
            array_slice($sorted, 0, $limit),
        ));
    }

    /** @return list<RuleResult> */
    public function sortedBySeverity(): array
    {
        $sorted = $this->results;

        usort($sorted, static function (RuleResult $a, RuleResult $b): int {
            return $b->severity->rank() <=> $a->severity->rank();
        });

        return array_values($sorted);
    }

    public function highestSeverity(): ?RuleSeverity
    {
        $sorted = $this->sortedBySeverity();

        return $sorted === [] ? null : $sorted[0]->severity;
    }

    /** Kunci aturan yang menyala, untuk disimpan ke log. @return list<string> */
    public function triggeredRuleKeys(): array
    {
        return array_map(static fn (RuleResult $r): string => $r->ruleKey, $this->results);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total_score' => $this->totalScore,
            'auto_rejected' => $this->autoRejected,
            'flagged' => $this->flagged,
            'triggers' => $this->triggeredRuleKeys(),
            'results' => array_map(static fn (RuleResult $r): array => $r->toArray(), $this->results),
        ];
    }
}
