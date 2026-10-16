<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Services\Moderation\Rules\SuspiciousUrlRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SuspiciousUrlRuleTest extends ModerationRuleTestCase
{
    protected function rule(): SuspiciousUrlRule
    {
        return $this->app->make(SuspiciousUrlRule::class);
    }

    #[Test]
    public function tautan_resmi_https_tidak_menyala(): void
    {
        $this->assertFalse($this->fires([
            'registrationUrl' => 'https://pendaftaran.kampus.ac.id/lomba?utm_source=instagram',
        ]));
    }

    /** @return array<string, array{string, string}> */
    public static function suspiciousUrlProvider(): array
    {
        return [
            'pemendek tautan' => ['https://bit.ly/xyz123', 'shortener'],
            'situs tempel' => ['https://pastebin.com/raw/abc', 'paste_site'],
            'berkas eksekusi' => ['https://kampus.ac.id/installer.exe', 'file_extension'],
            'tanpa https' => ['http://kampus.ac.id/daftar', 'no_https'],
            'bukan tautan' => ['bukan-tautan-sama-sekali', 'invalid'],
        ];
    }

    #[Test]
    #[DataProvider('suspiciousUrlProvider')]
    public function pola_bermasalah_menyalakan_aturan(string $url, string $expectedType): void
    {
        $result = $this->evaluate(['registrationUrl' => $url]);

        $this->assertNotNull($result, "URL {$url} seharusnya ditandai.");
        $this->assertSame('suspicious_url', $result->ruleKey);

        $types = array_column($result->metadata['findings'], 'type');
        $this->assertContains($expectedType, $types);
    }

    #[Test]
    public function www_tidak_menggagalkan_pencocokan_domain(): void
    {
        // bit.ly dan www.bit.ly harus sama-sama tertangkap.
        $result = $this->evaluate(['registrationUrl' => 'https://www.bit.ly/abc']);

        $types = array_column($result->metadata['findings'], 'type');
        $this->assertContains('shortener', $types);
    }

    #[Test]
    public function domain_yang_belum_dikenal_tidak_ditandai(): void
    {
        // Domain baru yang sah tidak boleh diblokir hanya karena belum
        // pernah muncul sebelumnya (Panduan §7.2).
        $this->assertFalse($this->fires([
            'registrationUrl' => 'https://organisasi-baru-2026.example.com/daftar',
        ]));
    }

    #[Test]
    public function terlalu_banyak_tautan_menyala(): void
    {
        $limit = (int) config('moderation.suspicious_url.max_links');

        // Tautan harus BERBEDA: allUrls() memakai array_unique, jadi
        // mengulang URL yang sama hanya menghasilkan satu tautan.
        $links = array_map(
            static fn (int $i): string => "https://kampus.ac.id/info-{$i}",
            range(1, $limit + 1),
        );

        $description = str_repeat('Kunjungi kami. ', 4) . implode(' ', $links);

        $result = $this->evaluate(['description' => $description]);

        $this->assertNotNull($result);
        $types = array_column($result->metadata['findings'], 'type');
        $this->assertContains('too_many_links', $types);
    }

    #[Test]
    public function tautan_di_alamat_lokasi_ikut_diperiksa(): void
    {
        $this->assertTrue($this->fires([
            'locationText' => 'Gedung A, daftar di https://bit.ly/lokasi',
        ]));
    }
}
