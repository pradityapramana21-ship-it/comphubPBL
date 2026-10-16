<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Services\Moderation\Rules\DateSanityRule;
use PHPUnit\Framework\Attributes\Test;

class DateSanityRuleTest extends ModerationRuleTestCase
{
    protected function rule(): DateSanityRule
    {
        return $this->app->make(DateSanityRule::class);
    }

    #[Test]
    public function tanggal_wajar_tidak_menyala(): void
    {
        $this->assertFalse($this->fires());
    }

    #[Test]
    public function kegiatan_yang_sudah_selesai_menyala(): void
    {
        $this->assertTrue($this->fires([
            'eventStartAt' => now()->subMonth()->toDateTimeImmutable(),
            'eventEndAt' => now()->subMonth()->addDay()->toDateTimeImmutable(),
        ]));
    }

    #[Test]
    public function tenggat_yang_hanya_sejam_sebelum_acara_menyala(): void
    {
        $this->assertTrue($this->fires([
            'eventStartAt' => now()->addHour()->toDateTimeImmutable(),
            'eventEndAt' => now()->addDay()->toDateTimeImmutable(),
        ]), 'Tenggat 1 jam sebelum acara harus ditandai.');
    }

    #[Test]
    public function tenggat_sehari_sebelum_acara_boleh(): void
    {
        $this->assertFalse($this->fires([
            'eventStartAt' => now()->addDays(2)->toDateTimeImmutable(),
            'eventEndAt' => now()->addDays(3)->toDateTimeImmutable(),
        ]));
    }

    #[Test]
    public function durasi_berlebihan_menyala(): void
    {
        $this->assertTrue($this->fires([
            'eventStartAt' => now()->addMonth()->toDateTimeImmutable(),
            'eventEndAt' => now()->addMonths(4)->toDateTimeImmutable(),
        ]));
    }

    #[Test]
    public function tanggal_lewat_justru_boleh_untuk_kegiatan_lama(): void
    {
        config()->set('moderation.date_sanity.allow_past_events', true);

        $this->assertFalse($this->fires([
            'eventStartAt' => now()->subDay()->toDateTimeImmutable(),
            'eventEndAt' => now()->subDay()->addHour()->toDateTimeImmutable(),
        ]), 'Arsip kegiatan lama tidak boleh ditandai.');
    }

    #[Test]
    public function metadata_menyimpan_angka_perhitungan(): void
    {
        $result = $this->evaluate([
            'eventStartAt' => now()->addHour()->toDateTimeImmutable(),
            'eventEndAt' => now()->addDay()->toDateTimeImmutable(),
        ]);

        $this->assertArrayHasKey('lead_hours', $result->metadata);
        $this->assertArrayHasKey('duration_days', $result->metadata);
        $this->assertLessThan((float) config('moderation.date_sanity.min_lead_hours'), $result->metadata['lead_hours']);
    }
}
