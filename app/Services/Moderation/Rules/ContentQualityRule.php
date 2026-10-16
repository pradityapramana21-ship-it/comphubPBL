<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Enums\RuleSeverity;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;
use App\Support\TextNormalizer;

/**
 * Memeriksa apakah isi kegiatan cukup jelas untuk ditayangkan.
 *
 * Bobot paling ringan (10): kegiatan yang sah tetap bisa tertahan kalau
 * deskripsinya cuma "kontak aja". Aturan ini menandai, bukan menolak.
 *
 * Satu-satunya aturan yang boleh dilonggarkan untuk penyedia terverifikasi
 * dengan riwayat bersih — lewat config('moderation.trust').
 */
class ContentQualityRule implements ModerationRule
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'content_quality';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::Low;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        if ($this->isTrusted($context)) {
            return null;
        }

        $titleChars = mb_strlen(trim($context->title));
        $titleWords = count($this->normalizer->tokens($context->title));
        $descriptionChars = mb_strlen(trim($context->description));

        $minTitleChars = (int) config('moderation.content_quality.min_title_chars', 15);
        $minTitleWords = (int) config('moderation.content_quality.min_title_words', 5);
        $minDescription = (int) config('moderation.content_quality.min_description_chars', 120);

        $problems = [];

        if ($titleChars < $minTitleChars) {
            $problems[] = "judul hanya {$titleChars} karakter (minimum {$minTitleChars})";
        }

        if ($titleWords < $minTitleWords) {
            $problems[] = "judul hanya {$titleWords} kata (minimum {$minTitleWords})";
        }

        if ($descriptionChars < $minDescription) {
            $problems[] = "deskripsi hanya {$descriptionChars} karakter (minimum {$minDescription})";
        }

        if ($problems === []) {
            return null;
        }

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.content_quality', 10),
            reason: 'Informasi kegiatan belum lengkap: ' . implode('; ', $problems) . '.',
            metadata: [
                'title_chars' => $titleChars,
                'title_words' => $titleWords,
                'description_chars' => $descriptionChars,
            ],
        );
    }

    /**
     * Hanya satu jenis kepercayaan yang dilonggarkan, dan hanya untuk satu
     * aturan. Aturan mutlak tidak pernah dikompensasi reputasi.
     */
    private function isTrusted(ModerationContext $context): bool
    {
        $skippable = (array) config('moderation.trust.verified_provider_skips', []);

        if (! in_array($this->key(), $skippable, true)) {
            return false;
        }

        if (! $context->providerIsTrusted()) {
            return false;
        }

        $clean = (int) config('moderation.trust.min_clean_submissions', 3);

        return $context->provider->events()
            ->where('status', 'approved')
            ->where('updated_at', '>=', now()->subDays((int) config('moderation.trust.clean_window_days', 90)))
            ->count() >= $clean;
    }
}
