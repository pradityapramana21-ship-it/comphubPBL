<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Enums\RuleSeverity;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Memeriksa logika tanggal yang TIDAK bisa ditulis sebagai CHECK constraint.
 *
 * Database sudah menegakkan tiga hal: deadline tidak melewati mulai,
 * kegiatan tidak berakhir sebelum mulai, dan kegiatan tayang wajib punya
 * published_at. Yang diperiksa di sini adalah hal yang butuh perhitungan
 * relatif terhadap waktu sekarang:
 *
 *   - tenggat yang hanya beberapa jam sebelum acara dimulai
 *   - kegiatan yang tanggalnya sudah lewat saat pertama kali diajukan
 *   - durasi yang tidak masuk akal (lomba 60 hari)
 */
class DateSanityRule implements ModerationRule
{
    public function key(): string
    {
        return 'date_sanity';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::High;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        $now = now();
        $problems = [];

        $allowPast = (bool) config('moderation.date_sanity.allow_past_events', false);

        // Hanya satu kondisi untuk masa lalu. Versi sebelumnya punya dua:
        // "ditolak kalau sudah lewat" DAN "ditolak kalau dimulai sebelum
        // sekarang" yang kedua berjalan justru saat allow_past_events =
        // true — dua-duanya bertentangan, dan menyalakan aturan untuk
        // kegiatan lama yang seharusnya boleh.
        if (! $allowPast && $context->eventEndAt < $now) {
            $problems[] = 'kegiatan sudah selesai sebelum diajukan';
        }

        // Tenggat yang hanya beberapa jam sebelum acara: secara teknis sah
        // (CHECK constraint lolos), tapi jarang disengaja dan membuat
        // pengumuman tidak berguna.
        $minLeadHours = (int) config('moderation.date_sanity.min_lead_hours', 2);
        $leadHours = ($context->eventStartAt->getTimestamp() - $now->getTimestamp()) / 3600;

        if ($leadHours >= 0 && $leadHours < $minLeadHours) {
            $problems[] = sprintf(
                'tenggat pendaftaran hanya %.1f jam sebelum acara dimulai (minimum %d jam)',
                $leadHours,
                $minLeadHours,
            );
        }

        $maxDuration = (int) config('moderation.date_sanity.max_duration_days', 30);
        $durationDays = ($context->eventEndAt->getTimestamp() - $context->eventStartAt->getTimestamp()) / 86400;

        if ($durationDays > $maxDuration) {
            $problems[] = sprintf('durasi kegiatan %.0f hari (maksimum %d hari)', $durationDays, $maxDuration);
        }

        if ($problems === []) {
            return null;
        }

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.date_sanity', 30),
            reason: 'Tanggal kegiatan tidak masuk akal: '.implode('; ', $problems).'.',
            metadata: [
                'event_start_at' => $context->eventStartAt->format(DATE_ATOM),
                'event_end_at' => $context->eventEndAt->format(DATE_ATOM),
                'registration_deadline' => $context->registrationDeadline->format(DATE_ATOM),
                'lead_hours' => round($leadHours, 2),
                'duration_days' => round($durationDays, 2),
            ],
        );
    }
}
