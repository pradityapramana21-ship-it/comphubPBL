<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\CategoryCache;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Alur yang tidak termasuk dua berkas sebelumnya.
 *
 * Registrasi, keluar, atur ulang kata sandi, sunting profil, penarikan
 * laporan, dan pengubahan peran admin tidak pernah dikirim formulirnya
 * oleh test lain. Semuanya dikumpulkan di sini supaya tidak ada rute
 * POST/PUT/DELETE yang lolos tanpa pernah diuji sekali pun.
 */
class AccountFlowTest extends TestCase
{
    use DatabaseTruncation;

    // -------------------------------------------------------- registrasi

    #[Test]
    public function mahasiswa_bisa_mendaftar(): void
    {
        $this->post('/daftar', [
            'name' => 'Andi Saputra',
            'email' => 'andi.baru@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect();

        $user = User::where('email', 'andi.baru@example.test')->firstOrFail();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function pendaftaran_dengan_email_yang_sudah_terpakai_ditolak(): void
    {
        User::factory()->create(['email' => 'bukan@sama.test']);

        $this->post('/daftar', [
            'name' => 'Orang Kedua',
            'email' => 'BUKAN@SAMA.TEST',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'bukan@sama.test')->count());
    }

    #[Test]
    public function password_pendek_ditolak_dengan_pesan_yang_jelas(): void
    {
        $this->post('/daftar', [
            'name' => 'Pendek',
            'email' => 'pendek@example.test',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::where('email', 'pendek@example.test')->count());
    }

    #[Test]
    public function penyedia_mendaftarkan_organisasi_lengkap_dengan_bukti(): void
    {
        Storage::fake('local');

        $this->post('/daftar/penyelenggara', [
            'name' => 'Ketua BEM',
            'email' => 'ketua@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'organization_name' => 'Badan Eksekutif Mahasiswa Demo',
            'campus_name' => 'Universitas Demo',
            'official_email' => 'bem@example.test',
            'website_url' => 'https://bem.example.test',
            'description' => 'Lembaga mahasiswa yang mengelola kegiatan tahunan Demo.',
            'verification_document' => UploadedFile::fake()->create('surat.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $user = User::where('email', 'ketua@example.test')->firstOrFail();
        $profile = ProviderProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(UserRole::Provider, $user->role);
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertNotNull($profile->verification_document_path);

        // Bukti organisasi harus tersimpan DI LUAR webroot. Yang diperiksa
        // adalah akar disk-nya, karena path yang tersimpan di database
        // relatif terhadap disk tersebut.
        $this->assertStringStartsWith('uploads/verification/', $profile->verification_document_path);
        Storage::disk('local')->assertExists($profile->verification_document_path);

        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR,
            Storage::disk('local')->path(''),
            'Disk bukti organisasi tidak boleh berada di dalam folder publik.',
        );
    }

    /**
     * Dokumen pendukung sengaja OPSIONAL (label di form juga menulis
     * "opsional"). Verifikasi tetap bisa dilakukan dari kontak resmi, jadi
     * memaksa berkas hanya akan menutupi organisasi yang sah.
     */
    #[Test]
    public function pendaftaran_penyedia_tanpa_bukti_tetap_berhasil(): void
    {
        $this->post('/daftar/penyelenggara', [
            'name' => 'Tanpa Bukti',
            'email' => 'tanpa@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'organization_name' => 'Organisasi Tanpa Bukti',
            'campus_name' => 'Universitas Demo',
            'official_email' => 'tanpa@contoh.test',
        ])->assertRedirect();

        $profile = ProviderProfile::whereHas(
            'user',
            fn ($query) => $query->where('email', 'tanpa@example.test'),
        )->firstOrFail();

        $this->assertNull($profile->verification_document_path);
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
    }

    /** Tapi berkas dengan tipe yang salah tetap ditolak. */
    #[Test]
    public function dokumen_verifikasi_tipe_salah_ditolak(): void
    {
        $this->post('/daftar/penyelenggara', [
            'name' => 'Berkas Salah',
            'email' => 'salah@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'organization_name' => 'Organisasi Berkas Salah',
            'campus_name' => 'Universitas Demo',
            'official_email' => 'salah@contoh.test',
            'verification_document' => UploadedFile::fake()->create('berkas.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('verification_document');
    }

    // -------------------------------------------------------------- keluar

    #[Test]
    public function keluar_menghapus_sesi_dan_mengalihkan_ke_beranda(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/keluar')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    // --------------------------------------------------- atur ulang sandi

    #[Test]
    public function tautan_atur_sandi_dikirim_dan_baru_dapat_dipakai(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'lupa@example.test']);

        $this->post('/lupa-sandi', ['email' => $user->email])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post('/atur-sandi', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'sandi-baru-456',
            'password_confirmation' => 'sandi-baru-456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('sandi-baru-456', $user->fresh()->password));
    }

    /**
     * Halaman lupa kata sandi tidak boleh membocorkan email mana yang
     * terdaftar — balasannya harus sama untuk keduanya.
     */
    #[Test]
    public function halaman_lupa_sandi_memberi_jawaban_yang_sama_untuk_email_terdaftar_maupun_tidak(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'terdaftar@example.test']);

        $this->post('/lupa-sandi', ['email' => 'terdaftar@example.test'])->assertRedirect();
        $terdaftar = session('status');

        $this->post('/lupa-sandi', ['email' => 'tidak-terdaftar@example.test'])->assertRedirect();
        $tidakTerdaftar = session('status');

        $this->assertSame($terdaftar, $tidakTerdaftar);
        $this->assertNotNull($terdaftar);
    }

    // ---------------------------------------------------------- profil

    #[Test]
    public function mahasiswa_memperbarui_profilnya(): void
    {
        $user = User::factory()->create(['name' => 'Nama Lama', 'email' => 'lama@example.test']);

        $this->actingAs($user)
            ->put('/profil', ['name' => 'Nama Baru', 'email' => 'baru@example.test'])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.test', $user->email);
    }

    #[Test]
    public function email_yang_dipakai_orang_lain_tidak_bisa_diambil(): void
    {
        User::factory()->create(['email' => 'dipakai@example.test']);
        $user = User::factory()->create(['email' => 'saya@example.test']);

        $this->actingAs($user)
            ->put('/profil', ['name' => 'Nama', 'email' => 'dipakai@example.test'])
            ->assertSessionHasErrors('email');

        $this->assertSame('saya@example.test', $user->fresh()->email);
    }

    /**
     * Password tidak boleh ikut berubah lewat form profil — harus lewat
     * form khusus yang menanyakan password lama.
     */
    #[Test]
    public function kata_sandi_tidak_bisa_diubah_lewat_form_profil(): void
    {
        $user = User::factory()->create(['password' => 'sandi-lama-123']);

        $this->actingAs($user)
            ->put('/profil', [
                'name' => 'Nama Baru',
                'email' => $user->email,
                'password' => 'sandi-baru-999',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('sandi-lama-123', $user->fresh()->password));
    }

    #[Test]
    public function ganti_kata_sandi_menuntut_password_lama_yang_benar(): void
    {
        $user = User::factory()->create(['password' => 'sandi-lama-123']);

        $this->actingAs($user)
            ->put('/profil/sandi', [
                'current_password' => 'sandi-lama-123',
                'password' => 'sandi-baru-456',
                'password_confirmation' => 'sandi-baru-456',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('sandi-baru-456', $user->fresh()->password));

        $this->actingAs($user->fresh())
            ->put('/profil/sandi', [
                'current_password' => 'sandi-salah',
                'password' => 'sandi-ketiga-456',
                'password_confirmation' => 'sandi-ketiga-456',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('sandi-baru-456', $user->fresh()->password));
    }

    #[Test]
    public function penyedia_memperbarui_profil_organisasinya(): void
    {
        $user = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->verified()->forUser($user)->create([
            'organization_name' => 'Nama Lama',
        ]);

        $this->actingAs($user)
            ->put('/dasbor/penyelenggara/profil-organisasi', [
                'organization_name' => 'Nama Baru Organisasi',
                'campus_name' => $profile->campus_name,
                'official_email' => $profile->official_email,
                'website_url' => 'https://organisasi-baru.example.test',
                'description' => 'Deskripsi organisasi yang sudah diperbarui.',
            ])
            ->assertRedirect();

        $this->assertSame('Nama Baru Organisasi', $profile->fresh()->organization_name);
    }

    // ------------------------------------------------- provider kegiatan

    #[Test]
    public function penyedia_bisa_memperbarui_drafnya(): void
    {
        $user = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->verified()->forUser($user)->create();
        $category = Category::factory()->create();
        $draft = Event::factory()->forProvider($profile)->inCategory($category)->create([
            'title' => 'Judul Lama',
        ]);

        $this->actingAs($user)
            ->put("/dasbor/penyelenggara/kegiatan/{$draft->id}", [
                'category_id' => $category->id,
                'title' => 'Judul Baru yang Jauh Lebih Panjang',
                'description' => str_repeat('Deskripsi kegiatan yang sudah diperbarui. ', 6),
                'registration_url' => 'https://daftar.example.test/baru',
                'mode' => 'online',
                'platform_url' => 'https://meet.example.test/baru',
                'event_start_at' => now()->addMonth()->toDateTimeString(),
                'event_end_at' => now()->addMonth()->addDay()->toDateTimeString(),
                'registration_deadline' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertRedirect();

        $draft->refresh();

        $this->assertSame('Judul Baru yang Jauh Lebih Panjang', $draft->title);
        $this->assertNotSame('judul-lama', $draft->slug, 'Slug harus ikut berubah kalau judul berubah.');
        $this->assertSame(EventStatus::Draft, $draft->status);
    }

    #[Test]
    public function penyedia_bisa_menarik_drafnya(): void
    {
        $user = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->verified()->forUser($user)->create();
        $draft = Event::factory()->forProvider($profile)->create();

        $this->actingAs($user)
            ->delete("/dasbor/penyelenggara/kegiatan/{$draft->id}")
            ->assertRedirect();

        $draft->refresh();

        // Draf yang ditarik tidak hilang dari database: ia pindah ke status
        // lain supaya jejaknya tetap bisa dibaca.
        $this->assertNotSame(EventStatus::Draft, $draft->status);
        $this->assertDatabaseHas('events', ['id' => $draft->id]);
    }

    // ------------------------------------------------------------ laporan

    #[Test]
    public function pelapor_bisa_menarik_laporannya_sendiri(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->published()->create();
        $report = EventReport::factory()->forEvent($event, $user)->create();

        $this->actingAs($user)
            ->delete("/laporan/{$report->id}")
            ->assertRedirect();

        $report->refresh();

        $this->assertNotSame(ReportStatus::Open, $report->status);
    }

    #[Test]
    public function orang_lain_tidak_bisa_menarik_laporan_bukan_miliknya(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->published()->create();
        $report = EventReport::factory()->forEvent($event, $owner)->create();

        $this->actingAs($other)
            ->delete("/laporan/{$report->id}")
            ->assertForbidden();

        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }

    // --------------------------------------------------------------- admin

    #[Test]
    public function admin_menandai_laporan_ditinjau_lalu_menyelesaikan(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->published()->create();
        $report = EventReport::factory()->forEvent($event, User::factory()->create())->create();

        $this->actingAs($admin)
            ->post("/admin/laporan/{$report->id}/tinjau")
            ->assertRedirect();

        $this->assertSame(ReportStatus::Reviewing, $report->fresh()->status);

        $this->actingAs($admin)
            ->post("/admin/laporan/{$report->id}/tidak-diteruskan", ['note' => 'Tidak ada pelanggaran.'])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame(ReportStatus::Dismissed, $report->status);
        $this->assertSame($admin->id, $report->resolved_by);
    }

    #[Test]
    public function admin_memperbarui_nama_kategori(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Lomba Lama', 'slug' => 'lomba-lama']);

        $this->actingAs($admin)
            ->put("/admin/kategori/{$category->slug}", [
                'name' => 'Lomba Baru',
                'description' => 'Deskripsi yang diperbarui.',
            ])
            ->assertRedirect();

        $category->refresh();

        $this->assertSame('Lomba Baru', $category->name);
    }

    #[Test]
    public function admin_bisa_menaikkan_peran_akun(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/pengguna/{$user->id}", ['role' => UserRole::Provider->value])
            ->assertRedirect();

        $this->assertSame(UserRole::Provider, $user->fresh()->role);
    }

    #[Test]
    public function admin_tidak_bisa_menurunkan_perannya_sendiri(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch("/admin/pengguna/{$admin->id}", ['role' => UserRole::User->value])
            ->assertForbidden();

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    #[Test]
    public function kategori_baru_ikut_muncul_di_filter_katalog(): void
    {
        $admin = User::factory()->admin()->create();
        $cache = app(CategoryCache::class);

        $this->assertTrue($cache->all()->isEmpty());

        $this->actingAs($admin)
            ->post('/admin/kategori', ['name' => 'Webinar'])
            ->assertRedirect();

        $this->assertCount(1, $cache->all());

        // Dan halaman filter harus benar-benar memakainya.
        $this->get('/kegiatan')->assertOk()->assertSee('Webinar');
    }
}
