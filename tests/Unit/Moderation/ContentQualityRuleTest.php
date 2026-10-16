<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Services\Moderation\Rules\ContentQualityRule;
use PHPUnit\Framework\Attributes\Test;

class ContentQualityRuleTest extends ModerationRuleTestCase
{
    protected function rule(): ContentQualityRule
    {
        return $this->app->make(ContentQualityRule::class);
    }

    #[Test]
    public function kegiatan_lengkap_tidak_menyala(): void
    {
        $this->assertFalse($this->fires());
    }

    #[Test]
    public function deskripsi_pendek_menyala(): void
    {
        $this->assertTrue($this->fires(['description' => 'kontak aja ya']));
    }

    #[Test]
    public function judul_terlalu_pendek_menyala(): void
    {
        $this->assertTrue($this->fires(['title' => 'Lomba']));
    }

    #[Test]
    public function semua_pelanggaran_disebutkan_bersamaan(): void
    {
        $result = $this->evaluate(['title' => 'Lomba', 'description' => 'kontak aja']);

        $this->assertStringContainsString('judul', $result->reason);
        $this->assertStringContainsString('deskripsi', $result->reason);
        $this->assertSame(1, $result->metadata['title_words'], '"Lomba" hanya satu kata.');
    }

    #[Test]
    public function bobotnya_paling_ringan(): void
    {
        $result = $this->evaluate(['title' => 'Lomba']);

        $this->assertSame('low', $result->severity->value);
        $this->assertLessThan(
            (int) config('moderation.thresholds.flag'),
            $result->score,
            'Aturan ini tidak boleh sendiri membuat pengajuan jadi prioritas.',
        );
    }

    #[Test]
    public function penyedia_terverifikasi_dengan_riwayat_bersih_dilewati(): void
    {
        // Langkah pertama: tanpa riwayat, penyedia meski sudah terverifikasi
        // tetap diperiksa. Ini yang membuat pengecualian terasa seperti hadiah,
        // bukan pembenaran otomatis.
        $this->assertTrue(
            $this->fires(['description' => 'pendek']),
            'Penyedia terverifikasi tanpa riwayat tetap diperiksa.',
        );

        $category = Category::first();
        $needed = (int) config('moderation.trust.min_clean_submissions');

        // Riwayat harus menyentuh angka minimum, jadi n-1 masih tidak cukup.
        for ($i = 0; $i < $needed - 1; $i++) {
            $this->makeApprovedEvent($category, $i);
        }

        $this->assertTrue(
            $this->fires(['description' => 'pendek']),
            "Dengan riwayat {$needed} - 1, aturan masih menyala.",
        );

        $this->makeApprovedEvent($category, $needed);

        $this->assertFalse(
            $this->fires(['description' => 'pendek']),
            "Dengan riwayat {$needed} kegiatan tayang, aturan harus dilewati.",
        );
    }

    private function makeApprovedEvent(Category $category, int $index): void
    {
        Event::create([
            'provider_profile_id' => $this->provider->id,
            'category_id' => $category->id,
            'title' => "Kegiatan Lampau {$index}",
            'slug' => "lampau-{$index}",
            'description' => str_repeat('Deskripsi kegiatan lama yang sudah pernah tayang. ', 3),
            'registration_url' => 'https://pendaftaran.uji.ac.id/lama',
            'mode' => EventMode::Online,
            'event_start_at' => now()->addDay(),
            'event_end_at' => now()->addDay()->addHour(),
            'registration_deadline' => now()->addHours(2),
            'status' => EventStatus::Approved,
            'published_at' => now(),
        ]);
    }

    #[Test]
    public function penyedia_belum_terverifikasi_tidak_dilewati(): void
    {
        $unverified = $this->unverifiedProvider();

        $this->assertTrue(
            $this->fires(['description' => 'pendek', 'provider' => $unverified]),
            'Penyedia belum verifikasi tidak punya reputasi untuk dilonggarkan.',
        );
    }
}
