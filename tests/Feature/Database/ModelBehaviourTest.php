<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\BookmarkService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membuktikan casting enum, relasi, dan scope model bekerja.
 *
 * Tanpa test ini, salah ketik nama kolom atau nama enum baru ketahuan saat
 * halaman dibuka pengguna — bukan saat test suite berjalan.
 */
class ModelBehaviourTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    private User $providerUser;

    private User $otherUser;

    private ProviderProfile $provider;

    private ProviderProfile $otherProvider;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('Admin', 'admin@t.test', UserRole::Admin);
        $this->providerUser = $this->makeUser('BEM Satu', 'bem1@t.test', UserRole::Provider);
        $this->otherUser = $this->makeUser('BEM Dua', 'bem2@t.test', UserRole::Provider);
        $this->normalUser = $this->makeUser('User', 'user@t.test', UserRole::User);

        $this->category = Category::create(['name' => 'Lomba', 'slug' => 'lomba']);

        $this->provider = ProviderProfile::create([
            'user_id' => $this->providerUser->id,
            'organization_name' => 'BEM Kampus A',
            'campus_name' => 'Kampus A',
            'official_email' => 'bem@a.test',
            'website_url' => 'https://bem.poltek.ac.id',
        ]);

        // Status verifikasi TIDAK bisa di-mass-assign. Satu-satunya jalan
        // resmi adalah method mark*() yang mencatat siapa yang memutuskan.
        $this->provider->markVerified($this->admin);

        $this->otherProvider = ProviderProfile::create([
            'user_id' => $this->otherUser->id,
            'organization_name' => 'BEM Kampus B',
            'campus_name' => 'Kampus B',
            'official_email' => 'bem@b.test',
            'verification_status' => VerificationStatus::Pending,
        ]);
    }

    private User $normalUser;

    private function makeUser(string $name, string $email, UserRole $role): User
    {
        return User::create([
            'name' => $name, 'email' => $email,
            'password' => 'rahasia-kuat-123', 'role' => $role,
        ]);
    }

    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'provider_profile_id' => $this->provider->id,
            'category_id' => $this->category->id,
            'title' => 'Lomba Profesional',
            'slug' => 'lomba-profesional-' . uniqid(),
            'description' => 'Deskripsi sufficiently panjang untuk lolos validasi.',
            'registration_url' => 'https://daftar.test/lomba',
            'mode' => EventMode::Offline,
            'location_text' => 'Gedung A',
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addDay(),
            'registration_deadline' => now()->addWeek(),
            'status' => EventStatus::Approved,
            'published_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------- casting

    #[Test]
    public function kolom_enum_diubah_menjadi_objek_enum(): void
    {
        $this->assertInstanceOf(UserRole::class, $this->admin->role);
        $this->assertSame(UserRole::Admin, $this->admin->role);
        $this->assertSame('admin', $this->admin->role->value);

        $event = $this->makeEvent();
        $this->assertInstanceOf(EventMode::class, $event->mode);
        $this->assertInstanceOf(EventStatus::class, $event->status);
        $this->assertInstanceOf(VerificationStatus::class, $this->provider->verification_status);

        $this->assertIsBool($this->category->is_active);
    }

    #[Test]
    public function password_di_hash_otomatis(): void
    {
        $fresh = User::find($this->admin->id);

        $this->assertNotSame('rahasia-kuat-123', $fresh->password);
        $this->assertStringStartsWith('$2y$', $fresh->password);
        $this->assertTrue(password_verify('rahasia-kuat-123', $fresh->password));
    }

    #[Test]
    public function password_tidak_pernah_ikut_serialisasi(): void
    {
        $array = $this->admin->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    // --------------------------------------------------------------- relasi

    #[Test]
    public function relasi_pengguna_provider_dan_kegiatan_terhubung(): void
    {
        $event = $this->makeEvent();

        $this->assertTrue($this->providerUser->providerProfile->is($this->provider));
        $this->assertTrue($this->providerUser->fresh()->providerProfile->is($this->provider));
        $this->assertTrue($event->providerProfile->is($this->provider));
        $this->assertTrue($event->category->is($this->category));
    }

    #[Test]
    public function scope_owned_by_membatasi_kegiatan_per_penyedia(): void
    {
        $mine = $this->makeEvent(['title' => 'Milik Saya']);
        $theirs = Event::create([
            'provider_profile_id' => $this->otherProvider->id,
            'category_id' => $this->category->id,
            'title' => 'Milik Orang Lain',
            'slug' => 'milik-orang-lain',
            'description' => 'Deskripsi.',
            'registration_url' => 'https://daftar.test/lain',
            'mode' => EventMode::Online,
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addHour(),
            'registration_deadline' => now()->addWeek(),
            'status' => EventStatus::Draft,
        ]);

        $found = Event::ownedBy($this->provider)->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($mine));
        $this->assertFalse($found->contains($theirs));
    }

    #[Test]
    public function scope_in_review_queue_hanya_menampilkan_yang_menunggu(): void
    {
        $pending = $this->makeEvent(['status' => EventStatus::Pending, 'published_at' => null, 'slug' => 'antri-1']);
        $changes = $this->makeEvent(['status' => EventStatus::ChangesRequested, 'published_at' => null, 'slug' => 'antri-2']);
        $this->makeEvent(['status' => EventStatus::Approved, 'slug' => 'tayang']);

        $queue = Event::inReviewQueue()->pluck('slug')->all();

        $this->assertCount(2, $queue);
        $this->assertContains('antri-1', $queue);
        $this->assertContains('antri-2', $queue);
        $this->assertNotContains('tayang', $queue);
    }

    #[Test]
    public function scope_published_tidak_pernah_membocorkan_draft(): void
    {
        $this->makeEvent(['status' => EventStatus::Draft, 'published_at' => null, 'slug' => 'rahasia-1']);
        $this->makeEvent(['status' => EventStatus::Pending, 'published_at' => null, 'slug' => 'rahasia-2']);
        $this->makeEvent(['status' => EventStatus::Rejected, 'published_at' => null, 'slug' => 'rahasia-3']);
        $this->makeEvent(['status' => EventStatus::Approved, 'slug' => 'publik']);

        $slugs = Event::published()->pluck('slug')->all();

        $this->assertSame(['publik'], $slugs);
    }

    #[Test]
    public function scope_active_membuang_kegiatan_yang_tenggatnya_lewat(): void
    {
        $this->makeEvent(['slug' => 'masih-buka', 'registration_deadline' => now()->addDays(2)]);
        $this->makeEvent(['slug' => 'sudah-lewat', 'registration_deadline' => now()->subDay()]);

        $active = Event::active()->pluck('slug')->all();

        $this->assertContains('masih-buka', $active);
        $this->assertNotContains('sudah-lewat', $active);
    }

    #[Test]
    public function pencarian_mengabaikan_huruf_besar_kecil(): void
    {
        $this->makeEvent(['title' => 'Lomba Nacional', 'slug' => 'lomba-nasional']);
        $this->makeEvent(['title' => 'Seminar careers', 'slug' => 'seminar-careers']);

        $found = Event::published()->search('lomba')->pluck('slug')->all();

        $this->assertSame(['lomba-nasional'], $found);
    }

    #[Test]
    public function scope_sorted_menolak_kolom_asing_dari_input_pengguna(): void
    {
        $this->makeEvent(['slug' => 'a']);
        $this->makeEvent(['slug' => 'b']);

        // Nilai string yang biasa dipakai untuk mencoba SQL injection
        // lewat query string ORDER BY. Harus diabaikan, bukan dieksekusi.
        $dangerous = 'tayang; DROP TABLE events; --';

        $rows = Event::published()->sorted($dangerous)->get();

        $this->assertCount(2, $rows);
        $this->assertTrue(Schema::hasTable('events'));
    }

    // -------------------------------------------------------------- helper

    #[Test]
    public function status_mesin_menolak_transisi_yang_tidak_wajar(): void
    {
        $this->assertTrue(EventStatus::Pending->canTransitionTo(EventStatus::Approved));
        $this->assertTrue(EventStatus::Rejected->canTransitionTo(EventStatus::Pending));
        $this->assertFalse(EventStatus::Archived->canTransitionTo(EventStatus::Pending));
        $this->assertFalse(EventStatus::Draft->canTransitionTo(EventStatus::Approved));
    }

    #[Test]
    public function label_tenggat_menyesuaikan_dengan_sisa_hari(): void
    {
        $besok = $this->makeEvent(['registration_deadline' => now()->addDay()]);
        $this->assertSame('Ditutup besok', $besok->deadlineLabel());

        $hariIni = $this->makeEvent(['registration_deadline' => now()]);
        $this->assertSame('Ditutup hari ini', $hariIni->deadlineLabel());

        $lewat = $this->makeEvent(['registration_deadline' => now()->subDays(3)]);
        $this->assertSame('Sudah lewat 3 hari', $lewat->deadlineLabel());
        $this->assertFalse($lewat->isRegistrationOpen());
    }

    #[Test]
    public function tone_tenggat_naik_ketika_mendekat(): void
    {
        $jauh = $this->makeEvent(['registration_deadline' => now()->addDays(30)]);
        $dekat = $this->makeEvent(['registration_deadline' => now()->addDays(3)]);
        $besok = $this->makeEvent(['registration_deadline' => now()->addDay()]);

        $this->assertSame('success', $jauh->deadlineTone());
        $this->assertSame('warning', $dekat->deadlineTone());
        $this->assertSame('danger', $besok->deadlineTone());
    }

    #[Test]
    public function lokasi_ditampilkan_sesuai_mode(): void
    {
        $online = $this->makeEvent(['mode' => EventMode::Online, 'location_text' => null]);
        $offline = $this->makeEvent(['mode' => EventMode::Offline, 'location_text' => 'Gedung B']);
        $hybrid = $this->makeEvent(['mode' => EventMode::Hybrid, 'location_text' => 'Gedung C']);

        $this->assertSame('Daring', $online->locationLabel());
        $this->assertSame('Gedung B', $offline->locationLabel());
        $this->assertSame('Daring + Gedung C', $hybrid->locationLabel());
    }

    #[Test]
    public function activity_boleh_diedit_provider_berdasarkan_status(): void
    {
        $this->assertTrue($this->makeEvent(['status' => EventStatus::Draft, 'published_at' => null, 'slug' => 's1'])->isEditableByProvider());
        $this->assertTrue($this->makeEvent(['status' => EventStatus::Rejected, 'published_at' => null, 'slug' => 's2'])->isEditableByProvider());
        $this->assertFalse($this->makeEvent(['status' => EventStatus::Approved, 'slug' => 's3'])->isEditableByProvider());
        $this->assertFalse($this->makeEvent(['status' => EventStatus::Pending, 'published_at' => null, 'slug' => 's4'])->isEditableByProvider());
    }

    #[Test]
    public function event_bisa_disimpan_lewat_untuk_user(): void
    {
        $event = $this->makeEvent();

        // Lewat BookmarkService, bukan query builder mentah: itu jalur
        // produksi yang sekaligus-maintain kolom cache bookmark_count.
        // Menyisipkan langsung ke tabel hanya mengisi tabelnya, sehingga
        // savedCount() yang membaca cache akan tetap melaporkan 0.
        $bookmarks = $this->app->make(BookmarkService::class);

        $this->assertTrue($bookmarks->add($this->normalUser, $event));
        $this->assertTrue($this->normalUser->bookmarkedEvents()->where('events.id', $event->id)->exists());
        $this->assertSame(1, $event->fresh()->savedCount());

        $this->assertFalse($bookmarks->add($this->normalUser, $event), 'Klik kedua tidak menambah.');
        $this->assertSame(1, $event->fresh()->savedCount());
    }

    #[Test]
    public function provider_menunggu_verifikasi_tidak_bisa_mengajukan(): void
    {
        $this->assertTrue($this->provider->isVerified());
        $this->assertFalse($this->otherProvider->isVerified());
        $this->assertTrue($this->providerUser->hasVerifiedProvider());
        $this->assertFalse($this->otherUser->hasVerifiedProvider());
    }

    #[Test]
    public function host_situs_organisasi_dibersihkan(): void
    {
        $this->assertSame('bem.poltek.ac.id', $this->provider->websiteHost());

        $tanpaSitus = ProviderProfile::create([
            'user_id' => $this->normalUser->id,
            'organization_name' => 'Tanpa Situs',
            'campus_name' => 'Kampus D',
            'official_email' => 'd@d.test',
        ]);
        $this->assertNull($tanpaSitus->websiteHost());
    }

    #[Test]
    public function inisial_nama_dihitung_dari_nama_lengkap(): void
    {
        $this->assertSame('BS', $this->providerUser->initials());
        $this->assertSame('A', $this->admin->initials());
        $this->assertSame('RT', User::create(['name' => 'R Tunggal', 'email' => 'rt@t.test', 'password' => 'x'])->initials());
    }

    #[Test]
    public function enum_lain_terpasang_dengan_benar(): void
    {
        $report = $this->normalUser->reports()->create([
            'event_id' => $this->makeEvent()->id,
            'reason_code' => ReportReason::Scam,
            'status' => ReportStatus::Open,
        ]);

        $this->assertInstanceOf(ReportReason::class, $report->reason_code);
        $this->assertSame(ReportReason::Scam, $report->reason_code);
        $this->assertTrue($report->reason_code->isUrgent());
        $this->assertTrue($report->isPending());
    }
}
