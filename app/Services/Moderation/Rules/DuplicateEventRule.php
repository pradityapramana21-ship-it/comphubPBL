<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Enums\RuleSeverity;
use App\Repositories\Contracts\EventRepository;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;
use App\Support\TextNormalizer;

/**
 * Menandai pengajuan yang mirip sekali dengan kegiatan milik penyedia yang
 * sama.
 *
 * Judul mirip tidak otomatis berarti spam: seorang organisator boleh
 * menjalankan competitions yang sama untuk beberapa wave. Karena itu aturan
 * ini hanya menambah skor, tidak pernah menolak sendiri, dan hanya menyala
 * bila tiga syarat sekaligus terpenuhi: penyedia sama, kemiripan judul di atas
 * ambang, dan tanggal acara berdekatan (Panduan §7.2).
 */
class DuplicateEventRule implements ModerationRule
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly TextNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'duplicate';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::Medium;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        $candidates = $this->events->findPossibleDuplicates(
            providerProfileId: $context->provider->id,
            title: $context->title,
            startDate: $context->eventStartAt,
        );

        // findPossibleDuplicates sudah menyaring lewat ambang kemiripan dan
        // jendela tanggal dari config, termasuk mengecualikan kegiatan yang
        // sedang direvisi (eventId di bawah).
        $duplicates = collect($candidates)
            ->reject(fn ($event): bool => $context->eventId !== null && $event->id === $context->eventId)
            ->filter(fn ($event): bool => $this->similarity($context->title, (string) $event->title)
                >= (float) config('moderation.duplicate.title_similarity_threshold', 0.85));

        if ($duplicates->isEmpty()) {
            return null;
        }

        $first = $duplicates->first();
        $titles = $duplicates->take(3)->pluck('title')->all();

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.duplicate', 30),
            reason: sprintf(
                'Mirip dengan kegiatan milik organisasi ini sendiri: "%s". Periksa apakah memang-duplikat atau wave yang berbeda.',
                $titles[0],
            ),
            metadata: [
                'duplicate_count' => $duplicates->count(),
                'existing_event_ids' => $duplicates->pluck('id')->all(),
                'existing_titles' => $titles,
                'best_similarity' => round($this->similarity($context->title, (string) $first->title), 3),
            ],
        );
    }

    /**
     * Kemiripan dua judul, 0..1.
     *
     * Gabungan kemiripan karakter dan kesamaan kata. Kemiripan kata diberi
     * bobot lebih besar karena "Lomba C" dan "C Lomba" jelas hal yang sama
     * walau urutan karakternya beda.
     */
    private function similarity(string $a, string $b): float
    {
        $a = $this->normalizer->normalise($a);
        $b = $this->normalizer->normalise($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $maxLength = max(mb_strlen($a), mb_strlen($b));
        $characterScore = 1.0 - (levenshtein($a, $b) / $maxLength);

        $wordsA = $this->normalizer->tokens($a);
        $wordsB = $this->normalizer->tokens($b);
        $union = array_unique(array_merge($wordsA, $wordsB));
        $wordScore = $union === [] ? 0.0 : (count(array_intersect($wordsA, $wordsB)) / count($union));

        return ($characterScore * 0.4) + ($wordScore * 0.6);
    }
}
