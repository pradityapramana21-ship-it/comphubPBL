<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membuktikan setiap rute ada, punya nama, dan middleware-nya menahan.
 *
 * Ini test yang paling murah untuk mendeteksi kesalahan konfigurasi rute:
 * satu rute yang lupa `role:admin` akan langsung terlihat di sini, bukan
 * saat Presentasi di depan dosen.
 */
class RoutingTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    private User $providerUser;

    private User $plainUser;

    private ProviderProfile $profile;

    private Event $published;

    private Event $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@rute.test', UserRole::Admin);
        $this->providerUser = $this->makeUser('bem@rute.test', UserRole::Provider);
        $this->plainUser = $this->makeUser('user@rute.test', UserRole::User);

        $this->profile = ProviderProfile::create([
            'user_id' => $this->providerUser->id,
            'organization_name' => 'BEM Universitas Rute',
            'campus_name' => 'Universitas Rute',
            'official_email' => 'bem@rute.test',
        ]);
        $this->profile->markVerified($this->admin);

        $category = Category::create(['name' => 'Lomba', 'slug' => 'lomba']);

        $this->published = $this->makeEvent($category, 'lomba-terbit', EventStatus::Approved, true);
        $this->draft = $this->makeEvent($category, 'lomba-draf', EventStatus::Draft, false);
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

    private function makeEvent(Category $category, string $slug, EventStatus $status, bool $publish): Event
    {
        return Event::create([
            'provider_profile_id' => $this->profile->id,
            'category_id' => $category->id,
            'title' => 'Lomba Tim Routed'.$slug,
            'slug' => $slug,
            'description' => str_repeat('Deskripsi kegiatan yang panjang untuk lolos validasi. ', 4),
            'registration_url' => 'https://pendaftaran.test/'.$slug,
            'mode' => EventMode::Online,
            'platform_url' => 'https://meet.test/'.$slug,
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addDay(),
            'registration_deadline' => now()->addWeek(),
            'status' => $status,
            'published_at' => $publish ? now() : null,
        ]);
    }

    // ------------------------------------------------------ keberadaan rute

    /** @return array<string, array{string}> */
    public static function namedRouteProvider(): array
    {
        return [
            'home' => ['home'],
            'daftar kegiatan' => ['events.index'],
            'detail kegiatan' => ['events.show'],
            'daftar penyelenggara' => ['providers.index'],
            'profil penyelenggara' => ['providers.show'],
            'tentang' => ['pages.about'],
            'kebijakan moderasi' => ['pages.moderation'],
            'panduan penyelenggara' => ['pages.providerGuide'],
            'kebijakan privasi' => ['pages.privacy'],
            'kontak' => ['pages.contact'],
            'masuk' => ['auth.login'],
            'keluar' => ['auth.logout'],
            'daftar pengguna' => ['auth.register'],
            'daftar penyedia' => ['auth.registerProvider'],
            'lupa sandi' => ['auth.password.request'],
            'atur sandi' => ['auth.password.reset'],
            'dasbor user' => ['user.dashboard'],
            'tersimpan' => ['user.bookmarks.index'],
            'profil user' => ['user.profile.edit'],
            'sandi user' => ['user.profile.password.edit'],
            'dasbor penyedia' => ['provider.dashboard'],
            'profil penyedia' => ['provider.profile.edit'],
            'daftar kegiatan penyedia' => ['provider.events.index'],
            'buat kegiatan' => ['provider.events.create'],
            'dasbor admin' => ['admin.dashboard'],
            'antrean tinjauan' => ['admin.events.index'],
            'detail tinjauan' => ['admin.events.show'],
            'keputusan moderasi' => ['admin.events.moderate'],
            'antrean verifikasi' => ['admin.providers.index'],
            'keputusan verifikasi' => ['admin.providers.decide'],
            'antrean laporan' => ['admin.reports.index'],
            'pengguna' => ['admin.users.index'],
            'kategori' => ['admin.categories.index'],
            'audit' => ['admin.audit.index'],
        ];
    }

    #[Test]
    #[DataProvider('namedRouteProvider')]
    public function route_bernama_terdaftar(string $name): void
    {
        $this->assertTrue(
            app('router')->has($name),
            "Route bernama [{$name}] tidak terdaftar.",
        );
    }

    // -------------------------------------------------------- akses publik

    #[Test]
    public function halaman_publik_terbuka_tanpa_masuk(): void
    {
        foreach (['/', '/kegiatan', '/kegiatan/lomba-terbit', '/penyelenggara', '/tentang', '/kontak'] as $uri) {
            $this->get($uri)->assertSuccessful();
        }
    }

    // --------------------------------------------------------- penghalang

    /** @return array<string, array{string, string}> */
    public static function guestGuardProvider(): array
    {
        return [
            'dasbor user' => ['/dasbor', 'auth.login'],
            'tersimpan' => ['/tersimpan', 'auth.login'],
            'dasbor penyedia' => ['/dasbor/penyelenggara', 'auth.login'],
            'dasbor admin' => ['/admin', 'auth.login'],
            'kelola kegiatan' => ['/admin/kegiatan', 'auth.login'],
        ];
    }

    #[Test]
    #[DataProvider('guestGuardProvider')]
    public function halaman_privat_meng_arahkan_tamu_ke_halaman_masuk(string $uri, string $loginRoute): void
    {
        $this->get($uri)->assertRedirect(route($loginRoute));
    }

    #[Test]
    public function admin_ditolak_bagi_pengguna_biasa(): void
    {
        $this->actingAs($this->plainUser)->get('/admin')->assertForbidden();
        $this->actingAs($this->plainUser)->get('/admin/kegiatan')->assertForbidden();
        $this->actingAs($this->plainUser)->get('/admin/pengguna')->assertForbidden();
    }

    #[Test]
    public function pengguna_biasa_tidak_bisa_membuka_dashboard_penyedia(): void
    {
        $this->actingAs($this->plainUser)->get('/dasbor/penyelenggara')->assertForbidden();
    }

    #[Test]
    public function penyedia_tidak_bisa_membuka_dasbor_admin(): void
    {
        $this->actingAs($this->providerUser)->get('/admin')->assertForbidden();
    }

    // ------------------------------------------------- kerahasiaan draf

    #[Test]
    public function draf_tidak_bisa_diakses_lewat_url_publik(): void
    {
        $this->get('/kegiatan/'.$this->draft->slug)->assertNotFound();
    }

    #[Test]
    public function draf_milik_sendiri_bisa_disunting(): void
    {
        // Draf sendiri boleh disunting. Kegiatan yang sudah tayang TIDAK,
        // dan itu diperiksa EventPolicy::update().
        $this->actingAs($this->providerUser)
            ->get('/dasbor/penyelenggara/kegiatan/'.$this->draft->id.'/sunting')
            ->assertSuccessful();

        $this->actingAs($this->providerUser)
            ->get('/dasbor/penyelenggara/kegiatan/'.$this->published->id.'/sunting')
            ->assertForbidden();

        // ID yang tidak ada milik siapa pun -> 404, bukan 403.
        $this->actingAs($this->providerUser)
            ->get('/dasbor/penyelenggara/kegiatan/999999/sunting')
            ->assertNotFound();
    }

    #[Test]
    public function rute_privat_tidak_mengonfirmasi_kegiatan_milik_orang_lain(): void
    {
        // Draf provider lain harus 404. Kalau 403, jawabannya membocarkan
        // bahwa kegiatan itu ADA.
        $another = $this->makeUser('bem2@rute.test', UserRole::Provider);
        $otherProfile = ProviderProfile::create([
            'user_id' => $another->id,
            'organization_name' => 'Organisasi Lain',
            'campus_name' => 'Kampus B',
            'official_email' => 'lain@rute.test',
        ]);
        $otherProfile->markVerified($this->admin);

        $category = Category::first();
        $rahasia = $this->makeEvent($category, 'rahasia-'.uniqid(), EventStatus::Draft, false);
        $rahasia->forceFill(['provider_profile_id' => $otherProfile->id])->save();

        $this->actingAs($this->providerUser)
            ->get('/dasbor/penyelenggara/kegiatan/'.$rahasia->id.'/sunting')
            ->assertNotFound();
    }

    #[Test]
    public function audit_log_tidak_bisa_diubah_oleh_siapa_pun(): void
    {
        // Tidak ada rute POST/PUT/DELETE untuk audit sama sekali.
        $auditRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin/audit'))
            ->map(fn ($route): string => implode('|', $route->methods()));

        $this->assertCount(1, $auditRoutes, 'Audit log harus punya tepat satu rute.');
        $this->assertSame('GET|HEAD', $auditRoutes->first(), 'Audit log harusnya hanya bisa dibaca.');
    }

    #[Test]
    public function menyimpan_kegiatan_hanya_bisa_lewat_post_atau_delete(): void
    {
        $methods = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'bookmark'))
            ->flatMap(fn ($route): array => $route->methods())
            ->unique()
            ->values()
            ->all();

        $this->assertNotContains('GET', $methods, 'GET tidak boleh mengubah state.');
        $this->assertContains('POST', $methods);
        $this->assertContains('DELETE', $methods);
    }

    // ------------------------------------------------- nagiat penghitung

    #[Test]
    public function membuka_halaman_kegiatan_menambah_penghitung_tayangan(): void
    {
        $this->resetViewLimiter();

        $before = $this->published->view_count;

        $this->get('/kegiatan/'.$this->published->slug)->assertOk();

        $this->assertSame($before + 1, $this->published->fresh()->view_count);
    }

    /**
     * Orang yang memuat ulang halaman sepuluh kali tetap satu orang.
     *
     * Rate limiter memakai cache, dan cache tidak ikut terpotong oleh
     * DatabaseTruncation — jadi tanpa pembersihan eksplisit, test lain
     * yang membuka halaman yang sama bisa membuat test ini gagal bukan
     * karena kodenya salah.
     */
    #[Test]
    public function memuat_ulang_berulang_hanya_dihitung_satu_kali(): void
    {
        $this->resetViewLimiter();

        $before = $this->published->view_count;

        $this->get('/kegiatan/'.$this->published->slug)->assertOk();
        $this->get('/kegiatan/'.$this->published->slug)->assertOk();
        $this->get('/kegiatan/'.$this->published->slug)->assertOk();

        $this->assertSame($before + 1, $this->published->fresh()->view_count);
    }

    private function resetViewLimiter(): void
    {
        RateLimiter::clear('event-view:127.0.0.1:'.$this->published->id);
    }

    #[Test]
    public function membuka_halaman_yang_salah_tidak_menambah_penghitung(): void
    {
        $before = $this->published->view_count;

        $this->get('/kegiatan/tidak-ada-sama-sekali')->assertNotFound();

        $this->assertSame($before, $this->published->fresh()->view_count);
    }
}
