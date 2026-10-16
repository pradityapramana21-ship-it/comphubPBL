<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\AccountStatus;
use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\AuthService;
use App\Services\BookmarkService;
use App\Services\EventExpiryService;
use App\Services\EventService;
use App\Services\ProviderVerificationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Uji alur bisnis yang memotong beberapa service sekaligus.
 *
 * Test di sini menjawab pertanyaan "kalau semua bagiannya disambung,
 * apakah alurnya benar?" — bukan "apakah tiap bagiannya benar sendiri".
 */
class ServiceIntegrationTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    private ProviderProfile $provider;

    private User $providerUser;

    private User $plainUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@uji.test', UserRole::Admin);
        $this->providerUser = $this->makeUser('bem@uji.test', UserRole::Provider);
        $this->plainUser = $this->makeUser('user@uji.test', UserRole::User);

        $this->provider = ProviderProfile::create([
            'user_id' => $this->providerUser->id,
            'organization_name' => 'BEM Universitas Uji',
            'campus_name' => 'Universitas Uji',
            'official_email' => 'bem@uji.test',
        ]);

        $this->category = Category::create(['name' => 'Lomba', 'slug' => 'lomba']);
    }

    private function makeUser(string $email, UserRole $role): User
    {
        return User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => 'rahasia-kuat-123',
            'role' => $role,
        ]);
    }

    private function makeDraft(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'provider_profile_id' => $this->provider->id,
            'category_id' => $this->category->id,
            'title' => 'Lomba Nasional Pemrograman 2026 untuk Mahasiswa',
            'slug' => 'lomba-'.uniqid(),
            'description' => str_repeat('Deskripsi kegiatan yang panjang dan jelas untuk lolos validasi. ', 4),
            'registration_url' => 'https://pendaftaran.uji.ac.id/lomba',
            'mode' => EventMode::Online,
            'platform_url' => 'https://meet.google.com/uji',
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addDay(),
            'registration_deadline' => now()->addWeek(),
            'status' => EventStatus::Draft,
        ], $overrides));
    }

    // -------------------------------------------------------- verifikasi

    #[Test]
    public function jalur_verifikasi_dari_tunggu_ke_terverifikasi(): void
    {
        $service = app(ProviderVerificationService::class);

        $this->assertSame('pending', $this->provider->verification_status->value);

        $service->verify($this->provider, $this->admin);

        $this->provider->refresh();

        $this->assertSame('verified', $this->provider->verification_status->value);
        $this->assertNotNull($this->provider->verified_at);
        $this->assertSame($this->admin->id, $this->provider->verified_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'provider.verified',
            'actor_user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function verifikasi_ganda_ditolak(): void
    {
        $service = app(ProviderVerificationService::class);
        $service->verify($this->provider, $this->admin);

        $this->expectException(ValidationException::class);
        $service->verify($this->provider->fresh(), $this->admin);
    }

    #[Test]
    public function menolak_verifikasi_menyimpan_alasan(): void
    {
        $service = app(ProviderVerificationService::class);

        $service->reject($this->provider, $this->admin, 'Dokumen organisasi tidak dapat dibaca.');
        $this->provider->refresh();

        $this->assertSame('rejected', $this->provider->verification_status->value);
        $this->assertSame('Dokumen organisasi tidak dapat dibaca.', $this->provider->verification_note);
        $this->assertNull($this->provider->verified_at, 'Penolakan harus menghapus jejak verifikasi.');
    }

    // ------------------------------------------------------------ pengajuan

    #[Test]
    public function pengajuan_bersih_masuk_antrean_bukan_diterbitkan(): void
    {
        $this->provider->markVerified($this->admin);
        $service = app(EventService::class);

        $draft = $this->makeDraft();
        $submitted = $service->submit($this->provider, $draft);

        $this->assertSame(EventStatus::Pending, $submitted->status);
        $this->assertSame(0, $submitted->risk_score);
        $this->assertNotNull($submitted->submitted_at);

        $this->assertDatabaseHas('moderation_logs', [
            'event_id' => $draft->id,
            'action' => ModerationAction::AutoFlagged->value,
        ]);
    }

    #[Test]
    public function pengajuan_bermasalah_ditolak_otomatis(): void
    {
        $this->provider->markVerified($this->admin);
        $service = app(EventService::class);

        $draft = $this->makeDraft(['description' => 'Lomba judol dengan deposit']);

        $result = $service->submit($this->provider, $draft);

        $this->assertSame(EventStatus::Rejected, $result->status);
        $this->assertGreaterThanOrEqual((int) config('moderation.thresholds.reject'), $result->risk_score);
        $this->assertNotNull($result->rejection_reason);

        $this->assertDatabaseHas('moderation_logs', [
            'event_id' => $draft->id,
            'rule_key' => 'keyword_blacklist',
        ]);
    }

    #[Test]
    public function pengajuan_tidak_pernah_jadi_approved_tanpa_admin(): void
    {
        $this->provider->markVerified($this->admin);
        $service = app(EventService::class);

        $draft = $this->makeDraft();
        $submitted = $service->submit($this->provider, $draft);

        $this->assertNotSame(EventStatus::Approved, $submitted->status);
        $this->assertNull($submitted->published_at);

        // Sampai test ini selesai, tidak boleh ada approved sama sekali.
        $this->assertDatabaseMissing('events', ['id' => $draft->id, 'status' => 'approved']);
    }

    #[Test]
    public function orang_lain_tidak_bisa_mengajukan_kegiatan_bukan_miliknya(): void
    {
        $this->provider->markVerified($this->admin);

        $lain = ProviderProfile::create([
            'user_id' => $this->plainUser->id,
            'organization_name' => 'Organisasi Lain',
            'campus_name' => 'Kampus B',
            'official_email' => 'lain@b.test',
        ]);
        $lain->markVerified($this->admin);

        $draft = $this->makeDraft();
        $service = app(EventService::class);

        $this->expectException(ValidationException::class);
        $service->submit($lain, $draft);
    }

    // ----------------------------------------------------------- keputusan

    #[Test]
    public function admin_menyetujui_dan_kegiatan_mulai_tayang(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);

        $draft = $this->makeDraft();
        $pending = $events->submit($this->provider, $draft);
        $approved = $events->approve($this->admin, $pending);

        $this->assertSame(EventStatus::Approved, $approved->status);
        $this->assertNotNull($approved->published_at);

        $this->assertDatabaseHas('moderation_logs', [
            'event_id' => $draft->id,
            'action' => ModerationAction::Approve->value,
            'actor_user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function menolak_tanpa_alasan_ditolak_oleh_service(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);

        $pending = $events->submit($this->provider, $this->makeDraft());

        $this->expectException(ValidationException::class);
        $events->reject($this->admin, $pending, '   ');
    }

    #[Test]
    public function transisi_tidak_sah_ditolak(): void
    {
        $events = app(EventService::class);
        $draft = $this->makeDraft();

        // Draf tidak bisa langsung diminta diperbaiki.
        $this->expectException(ValidationException::class);
        $events->requestChanges($this->admin, $draft, 'Tolong perbaiki deskripsinya.');
    }

    #[Test]
    public function keputusan_dari_bukan_admin_ditolak_meski_dipanggil_langsung(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);

        $pending = $events->submit($this->provider, $this->makeDraft());

        // Policy sudah menahan di controller; service adalah pengaman kedua.
        $this->expectException(ValidationException::class);
        $events->approve($this->plainUser, $pending, 'Saya admin kok');
    }

    // ------------------------------------------------------------ bookmark

    #[Test]
    public function bookmark_naik_turun_bersama_penghitungnya(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);
        $bookmarks = app(BookmarkService::class);

        $approved = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        $this->assertSame(0, $approved->savedCount());

        $this->assertTrue($bookmarks->add($this->plainUser, $approved));
        $this->assertSame(1, $approved->fresh()->savedCount());

        $this->assertFalse($bookmarks->add($this->plainUser, $approved), 'Klik kedua tidak menambah.');
        $this->assertSame(1, $approved->fresh()->savedCount());

        // toggle() mengembalikan keadaan AKHIR: true berarti sekarang
        // tersimpan, false berarti sekarang tidak tersimpan.
        $this->assertFalse($bookmarks->toggle($this->plainUser, $approved), 'Sudah tersimpan -> jadi dihapus.');
        $this->assertSame(0, $approved->fresh()->savedCount());

        $this->assertTrue($bookmarks->toggle($this->plainUser, $approved), 'Belum tersimpan -> jadi disimpan.');
        $this->assertSame(1, $approved->fresh()->savedCount());
    }

    #[Test]
    public function bookmark_untuk_kegiatan_yang_belum_tayang_ditolak(): void
    {
        $bookmarks = app(BookmarkService::class);
        $draft = $this->makeDraft();

        $this->assertFalse($bookmarks->add($this->plainUser, $draft));
        $this->assertDatabaseCount('bookmarks', 0);
    }

    #[Test]
    public function mengarsipkan_kegiatan_membuang_penyimpanannya(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);
        $bookmarks = app(BookmarkService::class);

        $approved = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        $bookmarks->add($this->plainUser, $approved);
        $this->assertDatabaseCount('bookmarks', 1);

        $events->archive($this->admin, $approved, 'Sponsor membatalkan');

        $this->assertDatabaseCount('bookmarks', 0);
        $this->assertSame(0, $approved->fresh()->savedCount());
    }

    // -------------------------------------------------------------- laporan

    #[Test]
    public function pelapor_tidak_bisa_melapor_kegiatannya_sendiri(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);
        $reports = app(ReportService::class);

        $approved = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        $this->expectException(ValidationException::class);
        $reports->report($this->providerUser, $approved, ReportReason::Spam, 'Tidak relevan');
    }

    #[Test]
    public function laporan_ganda_mengembalikan_laporan_yang_sama(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);
        $reports = app(ReportService::class);

        $approved = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        $first = $reports->report($this->plainUser, $approved, ReportReason::Spam, 'laporan pertama');
        $second = $reports->report($this->plainUser, $approved, ReportReason::Scam, 'laporan kedua');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('event_reports', 1);
        $this->assertSame('laporan pertama', $second->description, 'Laporan pertama tidak boleh tertimpa.');
    }

    #[Test]
    public function laporan_berjalan_dari_baru_ke_selesai(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);
        $reports = app(ReportService::class);

        $approved = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        $report = $reports->report($this->plainUser, $approved, ReportReason::BrokenLink, 'Tautan mati');

        $this->assertSame(ReportStatus::Open, $report->status);

        $reviewing = $reports->open($this->admin, $report);
        $this->assertSame(ReportStatus::Reviewing, $reviewing->status);

        $resolved = $reports->resolve($this->admin, $reviewing, 'Tautan sudah diperbaiki.');
        $this->assertSame(ReportStatus::Resolved, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame($this->admin->id, $resolved->resolved_by);
    }

    // ------------------------------------------------------------ kedaluwarsa

    #[Test]
    public function command_kedaluwarsa_menandai_yang_tenggatnya_lewat(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);

        $lewat = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft([
                'event_start_at' => now()->subDays(5),
                'event_end_at' => now()->subDays(4),
                'registration_deadline' => now()->subDays(7),
            ])),
        );

        $aktif = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        $count = app(EventExpiryService::class)->markExpired();

        $this->assertSame(1, $count);
        $this->assertSame(EventStatus::Expired, $lewat->fresh()->status);
        $this->assertSame(EventStatus::Approved, $aktif->fresh()->status);

        $this->assertDatabaseHas('moderation_logs', [
            'event_id' => $lewat->id,
            'action' => ModerationAction::Expire->value,
        ]);
    }

    #[Test]
    public function kegiatan_kedaluwarsa_hilang_dari_katalog_walau_status_belum_diubah(): void
    {
        $this->provider->markVerified($this->admin);
        $events = app(EventService::class);

        $approved = $events->approve(
            $this->admin,
            $events->submit($this->provider, $this->makeDraft()),
        );

        // Geser tenggat ke masa lalu TANPA menjalankan command.
        $approved->forceFill(['registration_deadline' => now()->subDay()])->save();

        $visible = app(\App\Repositories\Contracts\EventRepository::class)
            ->paginatePublicCatalog(\App\DataTransferObjects\CatalogFilter::fromQuery([]));

        $this->assertCount(0, $visible, 'Tenggat lewat harus hilang walau status masih approved.');
        $this->assertSame(
            EventStatus::Approved,
            $approved->fresh()->status,
            'Command yang menandai expired belum dijalankan, jadi status tidak berubah.',
        );
    }

    // ------------------------------------------------------------------ auth

    #[Test]
    public function masuk_dengan_kata_sandi_benar(): void
    {
        $auth = app(AuthService::class);

        $auth->login('user@uji.test', 'rahasia-kuat-123');

        $this->assertAuthenticatedAs($this->plainUser);
    }

    #[Test]
    public function pesan_gagal_masuk_sama_untuk_email_asing_dan_sandi_salah(): void
    {
        $auth = app(AuthService::class);

        try {
            $auth->login('tidak-ada@uji.test', 'apa-saja');
            $this->fail('Harus gagal.');
        } catch (ValidationException $e) {
            $asing = $e->errors()['email'][0];
        }

        try {
            $auth->login('user@uji.test', 'sandi-salah');
            $this->fail('Harus gagal.');
        } catch (ValidationException $e) {
            $salah = $e->errors()['email'][0];
        }

        $this->assertSame(
            $asing,
            $salah,
            'Pesan harus sama, kalau tidak halaman ini bisa mencari tahu email mana yang terdaftar.',
        );
    }

    #[Test]
    public function akun_ditangguhan_tidak_bisa_masuk(): void
    {
        $this->plainUser->forceFill([
            'account_status' => AccountStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        $this->expectException(ValidationException::class);
        app(AuthService::class)->login('user@uji.test', 'rahasia-kuat-123');
    }

    #[Test]
    public function ganti_kata_sandi_menggugurkan_sesi_lain(): void
    {
        $auth = app(AuthService::class);

        $this->plainUser->forceFill(['role' => UserRole::User])->save();
        $this->actingAs($this->plainUser);

        DB::table('sessions')->insert([
            'id' => 'sesi-orang-lain',
            'user_id' => $this->plainUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $auth->changePassword($this->plainUser, 'rahasia-kuat-123', 'sandi-baru-456');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-orang-lain']);
        $this->assertTrue(password_verify('sandi-baru-456', $this->plainUser->fresh()->password));
    }

    #[Test]
    public function audit_log_tidak_menyimpan_kata_sandi(): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $this->admin->id,
            'actor_role' => 'admin',
            'action' => 'uji',
            'target_type' => User::class,
            'target_id' => $this->plainUser->id,
            'metadata' => json_encode(['password' => 'rahasia', 'nama' => 'Budi']),
            'created_at' => now(),
        ]);

        // Pengaman berlapis: sanitiser ada di repository, tapi test ini
        // memastikan tidak ada jalan lain yang bisa menulis mentah.
        $log = app(\App\Repositories\Contracts\AuditLogRepository::class)
            ->record('uji.lain', metadata: ['password' => 'rahasia-2', 'nama' => 'Sari']);

        $this->assertSame('[disembunyikan]', $log->metadata['password']);
        $this->assertSame('Sari', $log->metadata['nama']);
    }
}
