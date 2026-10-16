<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membuktikan alias middleware dari bootstrap/app.php benar-benar aktif.
 *
 * Route dibuat di dalam test supaya file ini tidak bergantung pada
 * routes/web.php yang belum ada.
 */
class MiddlewareTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    private User $provider;

    private User $plain;

    private ProviderProfile $verifiedProfile;

    protected function setUp(): void
    {
        // WAJIB didaftarkan SEBELUM parent::setUp(). Callback ini baru
        // dijalankan di dalam parent::setUp(), jadi mendaftarkannya
        // sesudahnya tidak berefek sama sekali.
        $this->afterApplicationCreated(function (): void {
            Route::middleware('web')->group(function (): void {
                // Route yang dirujuk middleware, agar test tidak
                // bergantung pada routes/web.php (Batch 7).
                Route::get('/login', fn () => 'form-login')->name('auth.login');
                Route::get('/provider/profil', fn () => 'edit-profil')->name('provider.profile.edit');
                Route::get('/dasbor', fn () => 'dasbor')->name('user.dashboard');

                Route::get('/uji/admin', fn () => 'ok-admin')
                    ->middleware(['auth', 'role:admin'])->name('uji.admin');

                Route::get('/uji/admin-atau-penyedia', fn () => 'ok-keduanya')
                    ->middleware(['auth', 'role:admin,provider'])->name('uji.keduanya');

                Route::get('/uji/terverifikasi', fn () => 'ok-verified')
                    ->middleware(['auth', 'role:provider', 'verified'])->name('uji.verified');

                Route::get('/uji/publik', fn () => 'ok-publik')
                    ->middleware('web')->name('uji.publik');
            });

            // RouteCollection membangun daftar nama sekali, saat
            // routes/web.php dimuat. Route yang ditambahkan setelahnya
            // bisa DICOCOKKAN berdasarkan URI, tapi tidak ditemukan lewat
            // route() sampai daftar nama dibangun ulang. Tanpa baris ini,
            // redirect()->route('auth.login') di dalam middleware akan
            // melempar RouteNotFoundException.
            Route::getRoutes()->refreshNameLookups();
        });

        parent::setUp();

        $this->admin = $this->makeUser('admin@test', UserRole::Admin);
        $this->provider = $this->makeUser('provider@test', UserRole::Provider);
        $this->plain = $this->makeUser('user@test', UserRole::User);

        $this->verifiedProfile = ProviderProfile::create([
            'user_id' => $this->provider->id,
            'organization_name' => 'BEM Terverifikasi',
            'campus_name' => 'Kampus A',
            'official_email' => 'bem@a.test',
        ]);
        $this->verifiedProfile->markVerified($this->admin);
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

    #[Test]
    public function alias_admin_menolak_pengguna_biasa(): void
    {
        $this->actingAs($this->plain)->get('/uji/admin')->assertForbidden();
    }

    #[Test]
    public function alias_admin_menerima_admin(): void
    {
        $this->actingAs($this->admin)->get('/uji/admin')->assertOk();
    }

    #[Test]
    public function alias_admin_atau_penyedia_menerima_keduanya(): void
    {
        $this->actingAs($this->admin)->get('/uji/admin-atau-penyedia')->assertOk();
        $this->actingAs($this->provider)->get('/uji/admin-atau-penyedia')->assertOk();
        $this->actingAs($this->plain)->get('/uji/admin-atau-penyedia')->assertForbidden();
    }

    #[Test]
    public function halaman_admin_mengarah_ke_login_bila_belum_masuk(): void
    {
        $this->get('/uji/admin')->assertRedirect(route('auth.login'));
    }

    #[Test]
    public function alias_verified_menerima_penyedia_terverifikasi(): void
    {
        $this->actingAs($this->provider)->get('/uji/terverifikasi')->assertOk();
    }

    #[Test]
    public function alias_verified_menolak_penyedia_yang_belum_verifikasi(): void
    {
        $belum = $this->makeUser('baru@test', UserRole::Provider);

        ProviderProfile::create([
            'user_id' => $belum->id,
            'organization_name' => 'Organisasi Baru',
            'campus_name' => 'Kampus B',
            'official_email' => 'baru@b.test',
        ]);

        $this->actingAs($belum)->get('/uji/terverifikasi')->assertRedirect(route('provider.profile.edit'));
    }

    #[Test]
    public function middleware_global_menolak_akun_ditangguhkan(): void
    {
        $this->admin->forceFill([
            'account_status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $this->actingAs($this->admin->fresh())
            ->get('/uji/admin')
            ->assertRedirect(route('auth.login'));

        $this->assertGuest();
    }

    #[Test]
    public function rute_publik_tidak_terkena_middleware_role(): void
    {
        $this->get('/uji/publik')->assertOk();
    }

    #[Test]
    public function peran_tak_dikenal_tidak_bisa_disimpan(): void
    {
        // Peran "superadmin" tidak ada di enum UserRole. Karena kolom role
        // memakai enum cast DAN CHECK constraint di database, nilai itu
        // ditolak di dua tempat sekaligus — inilah default deny yang
        // disebut Panduan §2, bukan sekadar disembunyikan tombolnya.
        $this->expectException(\ValueError::class);

        $this->admin->forceFill(['role' => 'superadmin'])->save();
    }
}
