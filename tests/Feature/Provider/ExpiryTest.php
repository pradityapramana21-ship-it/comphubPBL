<?php

declare(strict_types=1);

namespace Tests\Feature\Provider;

use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use App\Models\Event;
use App\Models\User;
use App\Services\EventExpiryService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tugas terjadwal dan lapisan penguncian kedaluwarsa.
 *
 * Ada dua lapis: command terjadwal yang mengubah status, dan query daftar
 * publik yang menyaring berdasarkan tenggat. Test ini menutup keduanya —
 * kalau hanya satu yang diuji, perubahan pada yang satu lagi bisa
 * membuat kegiatan yang sudah tutup masih tampil di katalog.
 */
class ExpiryTest extends TestCase
{
    use DatabaseTruncation;

    #[Test]
    public function kegiatan_yang_tenggatnya_lewat_ditandai_kedaluwarsa(): void
    {
        $event = Event::factory()->published()->create([
            'registration_deadline' => now()->subHour(),
        ]);

        $count = app(EventExpiryService::class)->markExpired();

        $this->assertSame(1, $count);
        $this->assertSame(EventStatus::Expired, $event->fresh()->status);

        $log = $event->moderationLogs()->where('action', ModerationAction::Expire)->first();
        $this->assertNotNull($log, 'Kedaluwarsa harus tercatat di jejak moderasi.');
        $this->assertNull($log->actor_user_id, 'Kedaluwarsa dilakukan sistem, bukan admin.');
    }

    #[Test]
    public function kegiatan_yang_tenggatnya_masih_lama_tidak_diubah(): void
    {
        $event = Event::factory()->published()->create();

        $this->assertSame(0, app(EventExpiryService::class)->markExpired());
        $this->assertSame(EventStatus::Approved, $event->fresh()->status);
    }

    /** Command tidak boleh dijalankan dua kali untuk kegiatan yang sama. */
    #[Test]
    public function menjalankan_command_dua_kali_tidak_menggandakan_jejak(): void
    {
        $event = Event::factory()->published()->create([
            'registration_deadline' => now()->subHour(),
        ]);

        $service = app(EventExpiryService::class);
        $service->markExpired();
        $this->assertSame(0, $service->markExpired());

        $this->assertSame(
            1,
            $event->moderationLogs()->where('action', ModerationAction::Expire)->count(),
        );
    }

    #[Test]
    public function penyimpanan_kegiatan_kedaluwarsa_dibuang(): void
    {
        $user = User::factory()->create();
        $expired = Event::factory()->published()->create([
            'registration_deadline' => now()->subHour(),
        ]);
        $active = Event::factory()->published()->create();

        DB::table('bookmarks')->insert([
            ['user_id' => $user->id, 'event_id' => $expired->id, 'created_at' => now()],
            ['user_id' => $user->id, 'event_id' => $active->id, 'created_at' => now()],
        ]);

        $service = app(EventExpiryService::class);
        $service->markExpired();

        $this->assertSame(1, $service->purgeBookmarksOfExpired());
        $this->assertSame(1, DB::table('bookmarks')->count());
        $this->assertSame(0, $expired->fresh()->bookmark_count);
        $this->assertSame(0, $active->fresh()->bookmark_count);
    }

    /**
     * Lapisan kedua: walau status masih "approved", katalog tidak boleh
     * menampilkannya kalau tenggatnya lewat. Inilah yang membuat command
     * yang terlambat tetap tidak berbahaya.
     */
    #[Test]
    public function katalog_tidak_menampilkan_kegiatan_yang_tenggatnya_lewat(): void
    {
        Event::factory()->published()->create(['registration_deadline' => now()->subHour()]);
        $active = Event::factory()->published()->create();

        $this->get('/kegiatan')
            ->assertOk()
            ->assertSee($active->title)
            ->assertDontSee(Event::query()->where('registration_deadline', '<', now())->value('title'));
    }

    /** Jadwal yang terdaftar harus benar-benar ada dan tidak tumpang tindih. */
    #[Test]
    public function tiga_tugas_terjadwal_terdaftar(): void
    {
        $events = Schedule::events();

        $this->assertCount(3, $events);

        foreach ($events as $event) {
            $this->assertTrue(
                $event->withoutOverlapping,
                "Tugas {$event->description} bisa berjalan tumpang tindih.",
            );
        }

        $this->assertSame(
            ['tandai-kedaluwarsa', 'bersihkan-bookmark', 'segarkan-cache'],
            array_map(static fn ($event): string => $event->description, $events),
        );
    }
}
