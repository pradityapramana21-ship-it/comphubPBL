<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Services\Moderation\RuleResult;
use App\Services\Moderation\Rules\BlockedKeywordRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class BlockedKeywordRuleTest extends ModerationRuleTestCase
{
    protected function rule(): BlockedKeywordRule
    {
        return $this->app->make(BlockedKeywordRule::class);
    }

    #[Test]
    public function pengajuan_bersih_tidak_menyala(): void
    {
        $this->assertFalse($this->fires(), 'Kegiatan yang wajar tidak boleh ditandai.');
    }

    /** @return array<string, array{string}> */
    public static function blockedWordProvider(): array
    {
        return [
            'kata utuh' => ['Lomba coding dengan judol tiap malam'],
            'huruf besar' => ['Pendaftaran LOMBA CODING JUDOL'],
            'leetspeak' => ['Lomba dengan jud0l dan t0g3l'],
            'huruf berulang' => ['juuddolll di deskripsi'],
            'frasa terlarang' => ['Info casino di judul'],
            'pinjol' => ['Program bantuan pinjol untuk mahasiswa'],
        ];
    }

    #[Test]
    #[DataProvider('blockedWordProvider')]
    public function kata_terlarang_menyalakan_aturan(string $description): void
    {
        $result = $this->evaluate(['description' => $description]);

        $this->assertInstanceOf(RuleResult::class, $result, 'Kata terlarang harus terdeteksi.');
        $this->assertSame('keyword_blacklist', $result->ruleKey);
    }

    #[Test]
    public function pola_berbasis_kata_terpicu(): void
    {
        $result = $this->evaluate([
            'description' => 'Silakan transfer ke rekening kami sebelum daftar, jangan kirim sekarang via wa.',
        ]);

        $this->assertInstanceOf(RuleResult::class, $result);
        $this->assertNotEmpty($result->metadata['matches']);
    }

    #[Test]
    public function satu_kata_dari_pola_belum_cukup(): void
    {
        // "bayar" saja muncul di pengumuman yang sah. Pola baru menyala bila
        // semua kata di dalamnya ada — inilah pembatas false positive.
        $this->assertFalse($this->fires([
            'description' => 'Lomba ini gratis, tidak ada biaya yang perlu dibayar oleh peserta.',
        ]), 'Satu kata dari pola tidak boleh cukup.');
    }

    #[Test]
    public function bobot_melebihi_ambang_tolak(): void
    {
        $result = $this->evaluate(['description' => 'Lomba judol']);

        $this->assertGreaterThanOrEqual(
            (int) config('moderation.thresholds.reject'),
            $result->score,
            'Satu kata terlarang harus langsung melewati ambang tolak.',
        );
        $this->assertSame('critical', $result->severity->value);
    }

    #[Test]
    public function nomor_kontak_tidak_disimpan_ke_log(): void
    {
        // Ini aturan contact_leak, tapi dijaga di sini bahwa kata terlarang
        // juga tidak menyalin isi mentah ke metadata.
        $result = $this->evaluate(['description' => 'judol di 081234567890']);

        $encoded = json_encode($result->metadata);
        $this->assertStringNotContainsString('081234567890', $encoded, 'Metadata tidak boleh menyimpan data mentah yang mencurigakan.');
    }
}
