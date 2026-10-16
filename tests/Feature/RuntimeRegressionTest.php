<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Enums\UserRole;
use App\Support\CategoryCache;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dua jebakan yang hanya muncul di server sungguhan.
 *
 * Keduanya lolos di test biasa karena test memakai cache array, dan
 * ArrayStore menyimpan objek apa adanya di memori — tidak pernah
 * serialize. Di server, cache yang dipakai adalah driver database, dan
 * driver itu BENERAN melakukan serialize.
 */
class RuntimeRegressionTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        // Sengaja memakai driver yang sama dengan produksi. Dengan
        // CACHE_STORE=array, semua test di kelas ini jadi tidak berarti.
        config(['cache.default' => 'database']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        // Rate limiter ikut memakai cache, dan batas per alamat IP
        // dipakai bersama oleh semua test di kelas ini. Tanpa pembersihan,
        // test percobaan masuk bisa mengunci test berikutnya lewat 429.
        RateLimiter::clear('login-ip:127.0.0.1');
    }

    /**
     * Laravel 13 memakai config `serializable_classes => false`: cache
     * tidak boleh mengembalikan objek PHP apa pun.
     *
     * Kalau daftar kategori disimpan sebagai Eloquent Collection, permintaan
     * pertama masih berjalan (data baru di-cache), tetapi permintaan
     * berikutnya menerima `__PHP_Incomplete_Class` dan membalas 500.
     * Terjadi sendiri di katalog publik.
     */
    #[Test]
    public function katalog_publik_tetap_normal_saat_dibaca_dari_cache(): void
    {
        Category::create(['name' => 'Lomba', 'slug' => 'lomba']);

        $this->get('/kegiatan')->assertOk();
        $this->get('/kegiatan')->assertOk();
        $this->get('/')->assertOk();
    }

    /** Isi cache harus berupa baris data biasa, bukan objek. */
    #[Test]
    public function cache_kategori_tidak_menyimpan_objek_eloquent(): void
    {
        Category::create(['name' => 'Webinar', 'slug' => 'webinar']);

        app(CategoryCache::class)->all();

        $cached = $this->app->make('cache')->store()->get('catalog.categories');

        $this->assertIsArray($cached, 'Cache kategori harus berisi array, bukan objek.');
        $this->assertIsArray($cached[0]);
        $this->assertSame('Webinar', $cached[0]['name']);
    }

    /**
     * Kategori baru harus langsung terlihat, tanpa menunggu cache basi.
     *
     * Diuji lewat endpoint admin, karena di situ penginvalidannya
     * terjadi. Kalau hanya Category::create() dari test, tidak ada yang
     * memanggil CategoryCache::forget() — persis seperti di produksi.
     */
    #[Test]
    public function kategori_baru_langsung_terlihat_setelah_admin_menambahkannya(): void
    {
        $admin = User::factory()->admin()->create();
        $cache = app(CategoryCache::class);

        $this->actingAs($admin)->get('/admin/kategori')->assertOk();
        $this->assertTrue($cache->all()->isEmpty());

        $this->actingAs($admin)->post('/admin/kategori', [
            'name' => 'Seminar',
            'description' => 'Seminar bertema yang terbuka untuk umum.',
        ])->assertRedirect();

        $this->assertCount(1, $cache->all());
        $this->assertSame('Seminar', $cache->all()->first()->name);
    }

    /**
     * URL dengan id non-angka harus 404, bukan 500.
     *
     * Tanpa batasan whereNumber, PostgreSQL menerima "abc" sebagai pencarian
     * bigint dan membalas error `invalid input syntax for type bigint`.
     */
    #[Test]
    public function id_bukan_angka_di_url_memberi_404_bukan_500(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/kegiatan/bukan-angka')->assertNotFound();
        $this->actingAs($admin)->get('/admin/pengguna/bukan-angka')->assertNotFound();
        $this->actingAs($admin)->get('/admin/laporan/bukan-angka')->assertNotFound();
        $this->get('/kegiatan/bukan-angka/laporkan')->assertNotFound();

        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->verified()->forUser($provider)->create();

        $this->actingAs($provider)
            ->get('/dasbor/penyelenggara/kegiatan/bukan-angka')
            ->assertNotFound();
    }

    /**
     * Login yang berhasil harus menghapus jejak percobaan.
     *
     * Kalau tidak dihapus, orang yang salah ketik password dua kali lalu
     * berhasil masuk akan langsung terkunci pada percobaan berikutnya.
     */
    #[Test]
    public function login_berhasil_mengosongkan_jejak_percobaan(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia-kuat-123']);

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'salah'])->assertRedirect();
        $this->post('/masuk', ['email' => $admin->email, 'password' => 'salah'])->assertRedirect();

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'rahasia-kuat-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->post('/keluar')->assertRedirect();

        // Jejak percobaan harus kosong: dua percobaan yang tersisa masih
        // boleh, bukan langsung terkunci karena sebelumnya sempat salah.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/masuk', ['email' => $admin->email, 'password' => 'salah'])
                ->assertRedirect();
        }

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'rahasia-kuat-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /** Regression: halaman daftar kegiatan penyedia pernah tertangkap rute publik. */
    #[Test]
    public function halaman_penyedia_tidak_tertangkap_rute_publik(): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->verified()->forUser($provider)->create();

        $this->actingAs($provider)->get('/dasbor/penyelenggara/kegiatan')->assertOk();
        $this->actingAs($provider)->get('/dasbor/penyelenggara/profil-organisasi')->assertOk();

        $this->assertNotNull($profile);
    }

    /** Regression: POST /masuk pernah 500 karena rate limiter login bertipe salah. */
    #[Test]
    public function halaman_masuk_menerima_percobaan_gagal_dan_berhasil(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia-kuat-123']);

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'salah-sekali'])
            ->assertRedirect();

        $this->assertGuest();

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'rahasia-kuat-123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * Lima kegagalan berturut-turut harus mengunci percobaan berikutnya —
     * dengan pesan Bahasa Indonesia, bukan 429 kosong dari middleware.
     *
     * Bentuk jawaban ini penting: kalau suatu saat penghitung kegagalan
     * ini dihapus, test ini harus gagal, bukan diam saja.
     */
    #[Test]
    public function percobaan_gagal_yang_berulang_di_kunci(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia-kuat-123']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/masuk', ['email' => $admin->email, 'password' => 'salah'])
                ->assertRedirect()
                ->assertSessionHasErrors('email');
        }

        // Percobaan keenam: kata sandi yang benar pun tidak diterima.
        $response = $this->post('/masuk', [
            'email' => $admin->email,
            'password' => 'rahasia-kuat-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            (string) $response->getSession()->get('errors')->first('email'),
        );

        // Halaman pendukung tetap bisa dibuka, dan 404 tetap normal.
        $this->get('/masuk')->assertOk();
        $this->get('/halaman-yang-tidak-ada')->assertNotFound();
    }

    /**
     * Batas volume oleh middleware masih berlaku untuk yang mengirim
     * banyak permintaan tanpa email sama sekali.
     */
    #[Test]
    public function permintaan_masif_tanpa_email_ditolak(): void
    {
        $statuses = [];

        for ($request = 0; $request < 12; $request++) {
            $statuses[] = $this->post('/masuk', [])->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'Batas volume tidak berlaku untuk permintaan tanpa email.');
    }
}

