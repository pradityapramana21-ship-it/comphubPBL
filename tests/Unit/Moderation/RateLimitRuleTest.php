<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Enums\ModerationAction;
use App\Models\Event;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Services\Moderation\Rules\RateLimitRule;
use PHPUnit\Framework\Attributes\Test;

class RateLimitRuleTest extends ModerationRuleTestCase
{
    protected function rule(): RateLimitRule
    {
        return $this->app->make(RateLimitRule::class);
    }

    /**
     * Menggagalkan riwayat pengajuan dengan sengaja membuat entri log.
     * Tidak memakai Event::create karena yang dihitung adalah entri
     * moderation_logs dengan aksi 'submit', bukan jumlah baris kegiatan.
     */
    private function fakeSubmissions(int $count, int $minutesAgo = 5): void
    {
        $logs = $this->app->make(ModerationLogRepository::class);
        $category = \App\Models\Category::first();

        $event = Event::create([
            'provider_profile_id' => $this->provider->id,
            'category_id' => $category->id,
            'title' => 'Kegiatan batching',
            'slug' => 'batching-'.uniqid(),
            'description' => str_repeat('Deskripsi kegiatan untuk keperluan pengujian batas. ', 3),
            'registration_url' => 'https://pendaftaran.uji.ac.id/batch',
            'mode' => \App\Enums\EventMode::Online,
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addDay(),
            'registration_deadline' => now()->addWeek(),
            'status' => \App\Enums\EventStatus::Pending,
            'submitted_at' => now()->subMinutes($minutesAgo),
        ]);

        for ($i = 0; $i < $count; $i++) {
            $logs->record(
                eventId: $event->id,
                action: ModerationAction::Submit,
                ruleKey: null,
                reason: 'Pengajuan untuk pengujian batas.',
                riskScore: 0,
                actorUserId: null,
            );
        }

        // created_at moderation_logs memakai DEFAULT NOW(), jadi waktunya harus
        // ditulis ulang supaya simulasi "pengajuan lama" benar-benar terlihat
        // oleh jendela waktu di dalam aturan.
        if ($minutesAgo > 0) {
            \Illuminate\Support\Facades\DB::table('moderation_logs')
                ->where('event_id', $event->id)
                ->update(['created_at' => now()->subMinutes($minutesAgo)]);
        }
    }

    #[Test]
    public function pengajuan_pertama_tidak_menyala(): void
    {
        $this->assertFalse($this->fires());
    }

    #[Test]
    public function jumlah_normal_tidak_menyala(): void
    {
        $this->fakeSubmissions((int) config('moderation.rate_limit.per_hour') - 1);

        $this->assertFalse($this->fires(), 'Satu di bawah batas masih boleh.');
    }

    #[Test]
    public function lewat_batas_per_jam_menyala(): void
    {
        $this->fakeSubmissions((int) config('moderation.rate_limit.per_hour'));

        $result = $this->evaluate();

        $this->assertNotNull($result);
        $this->assertStringContainsString('1 jam', $result->reason);
        $this->assertSame('high', $result->severity->value);
    }

    #[Test]
    public function pengajuan_lama_tidak_dihitung(): void
    {
        // 3 jam lalu, bukan dalam jendela 1 jam.
        $this->fakeSubmissions((int) config('moderation.rate_limit.per_hour') + 5, minutesAgo: 180);

        $this->assertFalse($this->fires(), 'Pengajuan lama tidak boleh ikut menghitung.');
    }

    #[Test]
    public function metadata_menyebut_kedua_jendela(): void
    {
        $this->fakeSubmissions((int) config('moderation.rate_limit.per_hour'));

        $result = $this->evaluate();

        $this->assertSame(
            (int) config('moderation.rate_limit.per_hour'),
            $result->metadata['submissions_last_hour'],
        );
        $this->assertArrayHasKey('limit_per_day', $result->metadata);
        $this->assertArrayHasKey('cooldown_minutes', $result->metadata);
    }
}
