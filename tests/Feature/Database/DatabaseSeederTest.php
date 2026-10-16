<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Repositories\Contracts\EventRepository;
use App\Services\EventService;
use App\Services\ModerationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Data demo harus benar-benar bisa dipakai (Panduan TC-ENV-01).
 *
 * Dua hal yang diuji di sini, dan keduanya sering luput:
 *
 *  1. SEEDER DETERMINISTIK. Dijalankan dua kali dari database kosong,
 *     hasilnya harus sama. Kalau tidak, anggota lain tidak bisa
 *     memverifikasi lewat nomor yang tertulis di README, dan tangkapan
 *     layar presentasi tidak bisa dipercaya.
 *
 *  2. DATA DEMO SENGAJA BERBEDA. Reviewer harus bisa menemukan
 *     pengajuan yang tertunda dan yang ditolak di dashboard admin.
 *     Kalau semua seed berstatus published, fitur moderasi tidak bisa
 *     didemokan tanpa membuat data baru.
 */
class DatabaseSeederTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function akun_demo_sesuai_dengan_yang_tertulis_di_readme(): void
    {
        $this->assertNotNull(User::where('email', 'admin@lomba.test')->first());
        $this->assertNotNull(User::where('email', 'bem@lomba.test')->first());
        $this->assertNotNull(User::where('email', 'andi@lomba.test')->first());

        $this->assertSame(UserRole::Admin, User::where('email', 'admin@lomba.test')->firstOrFail()->role);
        $this->assertSame(UserRole::Provider, User::where('email', 'bem@lomba.test')->firstOrFail()->role);
    }

    /**
     * Password demo harus bisa dipakai untuk masuk.
     *
     * Kalau ini gagal, anggota lain tidak bisa menjalankan demo sama
     * sekali, dan gejalanya "email atau kata sandi salah" menyesatkan.
     */
    #[Test]
    public function kata_sandi_demo_terdaftar(): void
    {
        $admin = User::where('email', 'admin@lomba.test')->firstOrFail();

        $this->assertTrue(Hash::check(DatabaseSeeder::DEMO_PASSWORD, $admin->password));
    }

    #[Test]
    public function kategori_mencakup_seluruh_jenis_kegiatan(): void
    {
        $names = Category::pluck('name')->all();

        $this->assertContains('Lomba', $names);
        $this->assertContains('Webinar', $names);
        $this->assertContains('Seminar', $names);
    }

    #[Test]
    public function ketiga_status_verifikasi_punya_contoh(): void
    {
        foreach ([VerificationStatus::Verified, VerificationStatus::Pending, VerificationStatus::Rejected] as $status) {
            $this->assertGreaterThan(
                0,
                ProviderProfile::where('verification_status', $status->value)->count(),
                "Tidak ada contoh profil berstatus {$status->value}.",
            );
        }
    }

    #[Test]
    public function setiap_status_kegiatan_punya_contoh(): void
    {
        foreach (EventStatus::cases() as $status) {
            $this->assertGreaterThan(
                0,
                Event::where('status', $status->value)->count(),
                "Tidak ada contoh kegiatan berstatus {$status->value}.",
            );
        }
    }

    /**
     * Contoh yang ditolak otomatis harus tetap ditolak kalau diperiksa
     * ulang.
     *
     * Inilah gunanya data spam: bukan sekadar mengisi database, tapi
     * membuktikan aturan moderasi yang berjalan di produksi memang
     * menangkap pola itu.
     */
    #[Test]
    public function contoh_kegiatan_spam_masih_ditolak_oleh_moderasi(): void
    {
        $spam = Event::where('status', EventStatus::Rejected->value)->firstOrFail();

        $verdict = $this->app->make(ModerationService::class)->inspect(
            $this->app->make(EventService::class)->contextFor($spam),
        );

        $this->assertSame(
            EventStatus::Rejected,
            $verdict->resultingStatus(),
            'Contoh spam lolos moderasi; data demo tidak lagi sesuai dengan perilaku sistem.',
        );
    }

    /**
     * Draf dan pengajuan yang tertunda tidak boleh bocor ke daftar
     * publik. Ini yang paling sering merusak demo: satu baris keliru
     * dan peserta Presentasi bisa melihat kegiatan yang belum
     * disetujui siapa pun.
     */
    #[Test]
    public function hanya_kegiatan_diterima_yang_tayang_di_katalog_publik(): void
    {
        $public = $this->app->make(EventRepository::class)
            ->paginatePublicCatalog(CatalogFilter::fromQuery([])->withLimit(100));

        $this->assertGreaterThan(0, $public->total());

        foreach ($public as $event) {
            $this->assertSame(EventStatus::Approved, $event->status);
        }
    }

    /**
     * Menjalankan seeder lagi di atas data yang sudah ada harus gagal
     * keras, bukan diam-diam menggandakan baris.
     *
     * Keunikan slug, email, dan nama kategori memang penjaga
     * terakhirnya. Test ini memastikan penjaga itu masih hidup.
     */
    #[Test]
    public function menjalankan_seeder_lagi_ditolak_karena_data_kembar(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Hasil seed harus sama setiap kali dijalankan.
     */
    #[Test]
    public function seeder_menghasilkan_data_yang_sama_setiap_kali(): void
    {
        $first = $this->snapshot();

        $this->truncate();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->snapshot());
    }

    /**
     * Ringkasan data yang dibandingkan antar dua kali seed.
     *
     * @return array<string, int>
     */
    private function snapshot(): array
    {
        return [
            'pengguna' => User::count(),
            'kategori' => Category::count(),
            'organisasi' => ProviderProfile::count(),
            'kegiatan' => Event::count(),
            'bookmark' => DB::table('bookmarks')->count(),
        ];
    }

    private function truncate(): void
    {
        DB::statement('TRUNCATE TABLE users, categories, provider_profiles, events, bookmarks, event_reports, moderation_logs, audit_logs RESTART IDENTITY CASCADE');
    }
}