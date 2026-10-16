<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;
use App\Enums\RuleSeverity;

/**
 * Menandai nomor telepon, WhatsApp, Telegram, atau email yang disalin ke
 * deskripsi kegiatan.
 *
 * Ini bukan larangan mutlak. Platform menyediakan tombol pendaftaran dan
 * tempat melapor; menyalin kontak ke deskripsi membuat admin kehilangan
 * kendali dan pengguna tidak punya jalur yang aman untuk melapor.
 *
 * Karena itu bobotnya rendah dan aturan ini hanya menambah skor.
 */
class ContactLeakRule implements ModerationRule
{
    public function key(): string
    {
        return 'contact_leak';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::Low;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        $findings = [];

        // Teks bebas: SEMUA pola berlaku, termasuk email. Pengecualian
        // hanya berlaku pada field registration_url, di mana alamat email
        // resmi memang wajar (tautan mailto). Versi sebelumnya kelewat
        // dan ikut melewati email di deskripsi.
        $freeText = $context->allFreeText();

        foreach (config('moderation.contact_leak.patterns', []) as $label => $pattern) {
            $this->collect($findings, $freeText, (string) $pattern, (string) $label);
        }

        // Tautan pendaftaran diperiksa untuk pola non-email, karena di sana
        // nomor telepon tetap tidak pantas.
        $checkUrlForEmail = (bool) config('moderation.contact_leak.check_registration_url_for_email', false);

        foreach ($context->allUrls() as $url) {
            foreach (config('moderation.contact_leak.patterns', []) as $label => $pattern) {
                if ($label === 'email' && ! $checkUrlForEmail) {
                    continue;
                }

                $this->collect($findings, $url, (string) $pattern, (string) $label, $url);
            }
        }

        if ($findings === []) {
            return null;
        }

        $labels = [
            'whatsapp' => 'nomor WhatsApp',
            'telegram' => 'tautan Telegram',
            'phone_generic' => 'nomor telepon',
            'email' => 'alamat email',
        ];

        $parts = array_map(
            static fn (array $f): string => ($labels[$f['type']] ?? $f['type']).' di "'.$f['source'].'"',
            array_slice($findings, 0, 3),
        );

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.contact_leak', 20),
            reason: 'Kontak langsung ditulis manual di deskripsi ('
                .implode('; ', $parts).'). Gunakan tombol pendaftaran agar aktivitas bisa dimoderasi.',
            metadata: ['findings' => $findings],
        );
    }

    /**
     * @param  list<array<string, string>>  $findings
     */
    private function collect(array &$findings, string $subject, string $pattern, string $label, ?string $source = null): void
    {
        if ($pattern === '' || @preg_match($pattern, $subject, $matches) !== 1) {
            return;
        }

        // Nilai aslinya TIDAK ikut disimpan. Admin perlu tahu ada kontak,
        // bukan menyalin nomor itu sendiri ke dalam log yang bisa bocor.
        $findings[] = [
            'type' => $label,
            'source' => $source ?? 'deskripsi kegiatan',
        ];
    }
}
