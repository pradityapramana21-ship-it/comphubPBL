<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Services\Moderation\Rules\DuplicateEventRule;
use PHPUnit\Framework\Attributes\Test;

class DuplicateEventRuleTest extends ModerationRuleTestCase
{
    protected function rule(): DuplicateEventRule
    {
        return $this->app->make(DuplicateEventRule::class);
    }

    private function seedExistingEvent(string $title, int $dayOffset = 30): Event
    {
        $start = now()->addDays($dayOffset)->setTime(9, 0);

        return Event::create([
            'provider_profile_id' => $this->provider->id,
            'category_id' => Category::first()->id,
            'title' => $title,
            'slug' => 'k-' . md5($title),
            'description' => str_repeat('Deskripsi kegiatan yang sudah pernah diumumkan sebelumnya. ', 3),
            'registration_url' => 'https://pendaftaran.uji.ac.id/lama',
            'mode' => EventMode::Offline,
            'location_text' => 'Gedung A',
            'event_start_at' => $start,
            'event_end_at' => $start->copy()->addDay(),
            'registration_deadline' => $start->copy()->subWeek(),
            'status' => EventStatus::Approved,
            'published_at' => now(),
        ]);
    }

    #[Test]
    public function pengajuan_baru_tidak_menyala(): void
    {
        $this->assertFalse($this->fires());
    }

    #[Test]
    public function judul_identik_pada_tanggal_dekat_menyala(): void
    {
        $this->seedExistingEvent('Lomba Nasional Pemrograman 2026');

        $this->assertTrue($this->fires([
            'title' => 'Lomba Nasional Pemrograman 2026',
            'eventStartAt' => now()->addDays(31)->toDateTimeImmutable(),
            'eventEndAt' => now()->addDays(32)->toDateTimeImmutable(),
        ]));
    }

    #[Test]
    public function judul_berbeda_tidak_menyala(): void
    {
        $this->seedExistingEvent('Lomba Nasional Pemrograman 2026');

        $this->assertFalse($this->fires([
            'title' => 'Webinar Karier리즈_graduasi 2026',
            'eventStartAt' => now()->addDays(31)->toDateTimeImmutable(),
            'eventEndAt' => now()->addDays(32)->toDateTimeImmutable(),
        ]));
    }

    #[Test]
    public function tanggal_jauh_tidak_dianggap_duplikat(): void
    {
        $this->seedExistingEvent('Lomba Nasional Pemrograman 2026');

        // Judul sama, tapi dua bulan kemudian: itu wave berbeda, bukan duplikat.
        $this->assertFalse($this->fires([
            'title' => 'Lomba Nasional Pemrograman 2026',
            'eventStartAt' => now()->addDays(120)->toDateTimeImmutable(),
            'eventEndAt' => now()->addDays(121)->toDateTimeImmutable(),
        ]));
    }

    #[Test]
    public function miliknya_sendiri_yang_direvisi_tidak_menandai_dirinya(): void
    {
        $existing = $this->seedExistingEvent('Lomba Nasional Pemrograman 2026');

        $this->assertFalse($this->fires([
            'title' => 'Lomba Nasional Pemrograman 2026',
            'eventStartAt' => now()->addDays(31)->toDateTimeImmutable(),
            'eventEndAt' => now()->addDays(32)->toDateTimeImmutable(),
            'eventId' => $existing->id,
        ]), 'Mengevaluasi kegiatan yang sedang direvisi tidak boleh menandai dirinya sendiri.');
    }

    #[Test]
    public function metadata_menyebut_kandidat_duplikat(): void
    {
        $existing = $this->seedExistingEvent('Lomba Nasional Pemrograman 2026');

        $result = $this->evaluate([
            'title' => 'Lomba Nasional Pemrograman 2026',
            'eventStartAt' => now()->addDays(31)->toDateTimeImmutable(),
            'eventEndAt' => now()->addDays(32)->toDateTimeImmutable(),
        ]);

        $this->assertContains($existing->id, $result->metadata['existing_event_ids']);
        $this->assertGreaterThanOrEqual(
            (float) config('moderation.duplicate.title_similarity_threshold'),
            $result->metadata['best_similarity'],
        );
    }

    #[Test]
    public function organisasi_lain_tidak_ikut_terhitung(): void
    {
        $other = $this->unverifiedProvider();
        $start = now()->addDays(30)->setTime(9, 0);

        Event::create([
            'provider_profile_id' => $other->id,
            'category_id' => Category::first()->id,
            'title' => 'Lomba Nasional Pemrograman 2026',
            'slug' => 'k-orang-lain',
            'description' => str_repeat('Deskripsi kegiatan milik organisasi yang berbeda. ', 3),
            'registration_url' => 'https://lain.test/x',
            'mode' => EventMode::Offline,
            'location_text' => 'Gedung B',
            'event_start_at' => $start,
            'event_end_at' => $start->copy()->addDay(),
            'registration_deadline' => $start->copy()->subWeek(),
            'status' => EventStatus::Approved,
            'published_at' => now(),
        ]);

        $this->assertFalse(
            $this->fires([
                'title' => 'Lomba Nasional Pemrograman 2026',
                'eventStartAt' => now()->addDays(31)->toDateTimeImmutable(),
                'eventEndAt' => now()->addDays(32)->toDateTimeImmutable(),
            ]),
            'Kegiatan dengan judul sama milik organisasi lain bukan duplikat.',
        );
    }
}
