<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Enums\RuleSeverity;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;
use App\Support\TextNormalizer;

/**
 * Menangkap kata dan pola yang tidak pantas muncul di pengumuman kegiatan
 * mahasiswa.
 *
 * Bobotnya 100, di atas ambang tolak 60 — jadi satu temuan dari aturan ini
 * sudah cukup untuk menolak pengajuan. Ini disengaja: kata seperti
 * "judol" atau "legalisir ijazah" tidak punya versi yang sah dalam pengumuman
 * kegiatan kampus.
 *
 * Teks diperiksa dalam bentuk normalisasi AGRESIF, sehingga "Gr4t1s",
 * "JUDOL", dan "j u d o l" ikut tertangkap.
 */
class BlockedKeywordRule implements ModerationRule
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'keyword_blacklist';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::Critical;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        $haystack = $context->allFreeText();

        if (trim($haystack) === '') {
            return null;
        }

        $matches = [];

        foreach (config('moderation.keywords.blocked', []) as $keyword) {
            if ($this->normalizer->contains($haystack, (string) $keyword)) {
                $matches[] = ['type' => 'kata', 'value' => $keyword];
            }
        }

        $minWords = (int) config('moderation.keywords.min_pattern_words', 2);

        foreach (config('moderation.keywords.patterns', []) as $label => $words) {
            $words = array_values(array_filter((array) $words));

            // Pola yang hanya satu kata terlalu longgar: satu kata umum
            // akan menandai ribuan kegiatan yang sah. Buang saat konfigurasi
            // tidak sengaja punya entri seperti itu.
            if (count($words) < $minWords) {
                continue;
            }

            if ($this->normalizer->containsAllWords($haystack, $words)) {
                $matches[] = ['type' => 'pola', 'label' => $label, 'kata' => $words];
            }
        }

        if ($matches === []) {
            return null;
        }

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.keyword_blacklist', 100),
            reason: $this->describe($matches),
            metadata: ['matches' => $matches],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     */
    private function describe(array $matches): string
    {
        $parts = array_map(static function (array $match): string {
            return $match['type'] === 'kata'
                ? 'kata terlarang "'.$match['value'].'"'
                : 'pola "'.$match['label'].'"';
        }, $matches);

        // Admin hanya perlu tiga, sisanya cukup tahu jumlahnya.
        $shown = array_slice($parts, 0, 3);
        $extra = count($parts) - count($shown);

        return 'Terdeteksi '.implode(', ', $shown)
            .($extra > 0 ? " (dan {$extra} lagi)" : '')
            .'. Pengumuman kegiatan kampus tidak boleh memuat ini.';
    }
}
