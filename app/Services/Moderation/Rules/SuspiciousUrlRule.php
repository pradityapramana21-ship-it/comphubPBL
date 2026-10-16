<?php

declare(strict_types=1);

namespace App\Services\Moderation\Rules;

use App\Enums\RuleSeverity;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Menandai tautan dengan pola yang khas dipakai penipuan.
 *
 * Domain yang sah TIDAK PERNAH diblokir hanya karena belum dikenal
 * (Panduan §7.2). Yang dicurigai hanya empat hal: pemendek tautan, situs
 * tempel, berkas executable, dan tautan tanpa HTTPS.
 *
 * Sementara itu satu kegiatan boleh punya banyak tautan; batasnya ada di config.
 */
class SuspiciousUrlRule implements ModerationRule
{
    public function key(): string
    {
        return 'suspicious_url';
    }

    public function severity(): RuleSeverity
    {
        return RuleSeverity::Medium;
    }

    public function evaluate(ModerationContext $context): ?RuleResult
    {
        $urls = $context->allUrls();

        if ($urls === []) {
            return null;
        }

        $shorteners = (array) config('moderation.suspicious_url.shorteners', []);
        $pasteSites = (array) config('moderation.suspicious_url.paste_sites', []);
        $badExtensions = (array) config('moderation.suspicious_url.file_extensions', []);
        $requireHttps = (bool) config('moderation.suspicious_url.require_https', true);
        $maxLinks = (int) config('moderation.suspicious_url.max_links', 3);

        $findings = [];

        foreach ($urls as $url) {
            $host = $this->host($url);
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $path = strtolower((string) parse_url($url, PHP_URL_PATH));

            if ($host !== '' && in_array($host, $shorteners, true)) {
                $findings[] = ['type' => 'shortener', 'url' => $url];

                continue;
            }

            if ($host !== '' && in_array($host, $pasteSites, true)) {
                $findings[] = ['type' => 'paste_site', 'url' => $url];

                continue;
            }

            if ($path !== '' && preg_match('/\.(' . implode('|', array_map('preg_quote', $badExtensions)) . ')$/i', $path) === 1) {
                $findings[] = ['type' => 'file_extension', 'url' => $url];

                continue;
            }

            // Kelayakan diperiksa lebih dulu. Versi sebelumnya mengecek
            // HTTPS dulu, sehingga string yang sama sekali bukan tautan
            // dilaporkan sebagai "tidak HTTPS" dan cek 'invalid' tidak
            // pernah terjangkau.
            if ($scheme === '' || $host === '') {
                $findings[] = ['type' => 'invalid', 'url' => $url];

                continue;
            }

            if ($requireHttps && $scheme !== 'https') {
                $findings[] = ['type' => 'no_https', 'url' => $url];
            }
        }

        $linkCount = count($urls);

        if ($linkCount > $maxLinks) {
            $findings[] = ['type' => 'too_many_links', 'count' => $linkCount, 'limit' => $maxLinks];
        }

        if ($findings === []) {
            return null;
        }

        return RuleResult::make(
            ruleKey: $this->key(),
            severity: $this->severity(),
            score: (int) config('moderation.weights.suspicious_url', 25),
            reason: $this->describe($findings),
            metadata: ['findings' => $findings, 'link_count' => $linkCount],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function describe(array $findings): string
    {
        $labels = [
            'shortener' => 'memakai pemendek tautan',
            'paste_site' => 'menunjuk situs tempel',
            'file_extension' => 'menunjuk berkas yang bisa dieksekusi',
            'no_https' => 'tidak memakai HTTPS',
            'invalid' => 'bukan tautan yang valid',
            'too_many_links' => 'jumlah tautan melebihi batas',
        ];

        $parts = array_map(function (array $finding): string {
            $label = $labels[$finding['type']] ?? $finding['type'];

            return $finding['type'] === 'too_many_links'
                ? sprintf('%s (%d tautan)', $label, (int) $finding['count'])
                : sprintf('%s: %s', $label, (string) $finding['url']);
        }, $findings);

        return 'Tautan bermasalah — ' . implode('; ', $parts) . '.';
    }

    /**
     * Host tanpa prefiks "www." supaya perbandingan dengan daftar putih
     * tidak gagal hanya karena satu titik.
     */
    private function host(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return strtolower(preg_replace('/^www\./', '', $host) ?? '');
    }
}
