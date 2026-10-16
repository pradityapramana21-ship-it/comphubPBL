<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Enums\RuleSeverity;
use App\Enums\ModerationAction;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Membatasi seberapa sering satu penyedia boleh mengajukan kegiatan.
 *
 * Dua jendela waktu: per jam menahan burst, per hari menahan volume total.
 * Setelah terlampaui, penyedia masuk masa tenggang (cooldown) dan tidak
 * bisa mengajukan selama beberapa menit.
 *
 * Angka batas berasal dari config, dan selalu longgar untuk pemakaian nyata.
 * Yang dicek di sini hanya pelampauan batas, bukan menolak pengajuan
 * pertama yang gagal.
 */
class RateLimitRule implements ModerationRule
{
    public function __construct(
        private readonly ModerationLogRepository $moderationLogs,
    ) {}

    public function key(): string
    {
        return 'rate_limit';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::High;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        $providerId = $context->provider->id;

        $lastHour = $this->moderationLogs->countSubmissionsSince($providerId, now()->subHour());
        $perHour = (int) config('moderation.rate_limit.per_hour', 5);

        $today = $this->moderationLogs->countSubmissionsSince($providerId, now()->subDay());
        $perDay = (int) config('moderation.rate_limit.per_day', 20);

        $violations = [];

        if ($lastHour >= $perHour) {
            $violations[] = "{$lastHour} pengajuan dalam 1 jam (batas {$perHour})";
        }

        if ($today >= $perDay) {
            $violations[] = "{$today} pengajuan dalam 24 jam (batas {$perDay})";
        }

        if ($violations === []) {
            return null;
        }

        $cooldown = (int) config('moderation.rate_limit.cooldown_minutes', 30);

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.rate_limit', 40),
            reason: sprintf(
                'Batas pengajuan terlampaui: %s. Tunggu %d menit sebelum mengajukan lagi.',
                implode('; ', $violations),
                $cooldown,
            ),
            metadata: [
                'submissions_last_hour' => $lastHour,
                'submissions_last_day' => $today,
                'limit_per_hour' => $perHour,
                'limit_per_day' => $perDay,
                'cooldown_minutes' => $cooldown,
            ],
        );
    }
}
