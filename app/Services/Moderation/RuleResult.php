<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\RuleSeverity;

/**
 * Hasil pemeriksaan satu aturan.
 *
 * Immutable: kalau sebuah aturan tidak menemukan apa pun, dia mengembalikan
 * null — bukan RuleResult kosong. Dengan begitu tidak mungkin ada temuan
 * dengan skor nol yang tetap tampil di daftar alasan admin.
 */
final readonly class RuleResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $ruleKey,
        public RuleSeverity $severity,
        public int $score,
        public string $reason,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function make(
        string $ruleKey,
        RuleSeverity $severity,
        int $score,
        string $reason,
        array $metadata = [],
    ): self {
        return new self($ruleKey, $severity, $score, $reason, $metadata);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule_key' => $this->ruleKey,
            'severity' => $this->severity->value,
            'score' => $this->score,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }
}
