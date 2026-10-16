<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ReportReason;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Merender setiap halaman GET dengan data yang realistis.
 *
 * RoutingTest membuktikan middleware menahan. Test ini membuktikan
 * sebaliknya: bahwa halaman yang lolos middleware benar-benar bisa
 * dirender. Kesalahan seperti "view punya variabel yang tidak pernah
 * dikirim controller" atau "enum tidak bisa jadi string" tidak pernah
 * muncul di RoutingTest — keduanya baru muncul ketika Blade dieksekusi.
 *
 * Karena itu di sini tidak ada yang diasersikan selain status 200.
 * Isi halaman diuji di tempat lain; tugas test ini cuma memastikan
 * tidak ada halaman yang meledak saat dibuka.
 */
class ViewSmokeTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    private User $providerUser;

    private User $plainUser;

    private ProviderProfile $verifiedProfile;

    private ProviderProfile $pendingProfile;

    private Category $category;

    private Event $published;

    private Event $pendingEvent;

    private Event $draftEvent;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@smoke.test', UserRole::Admin);
        $this->providerUser = $this->makeUser('bem@smoke.test', UserRole::Provider);
        $this->plainUser = $this->makeUser('mahasiswa@smoke.test', UserRole::User);

        $this->verifiedProfile = $this->makeProfile($this->providerUser, 'bem-terverifikasi@smoke.test', 'BEM Smoke Terverifikasi');
        $this->verifiedProfile->markVerified($this->admin);

        // Organisasi yang masih menunggu keputusan, supaya antrean
        // verifikasi di dasbor admin tidak kosong. Pengguna kedua
        // karena satu akun hanya boleh punya satu profil.
        $waitingUser = $this->makeUser('himpunan@smoke.test', UserRole::Provider);
        $this->pendingProfile = $this->makeProfile($waitingUser, 'himpunan@smoke.test', 'Himpunan Smoke Menunggu');

        // Pelapor terpisah dari $plainUser, supaya halaman formulir
        // laporan tidak bentrok dengan laporan yang sudah dibuat.
        $this->reporter = $this->makeUser('pelapor@smoke.test', UserRole::User);

        $this->category = Category::create(['name' => 'Lomba', 'slug' => 'lomba']);

        $this->published = $this->makeEvent('lomba-smoke-terbit', EventStatus::Approved, true);
        $this->pendingEvent = $this->makeEvent('lomba-smoke-antre', EventStatus::Pending, false);

        // Draf milik penyedia sendiri: hanya status ini yang boleh disunting,
        // jadi halaman sunting hanya bisa diuji dengan draf sungguhan.
        $this->draftEvent = $this->makeEvent('lomba-smoke-draf', EventStatus::Draft, false);

        // Bookmark sengaja tidak mass-assignable karena primary key-nya
        // komposit, jadi fixture ini menulis lewat query builder — sama
        // seperti yang BookmarkService lakukan di produksi.
        DB::table('bookmarks')->insert([
            'user_id' => $this->plainUser->id,
            'event_id' => $this->published->id,
            'created_at' => now(),
        ]);

        EventReport::create([
            'event_id' => $this->published->id,
            'reporter_user_id' => $this->reporter->id,
            'reason_code' => ReportReason::BrokenLink,
            'description' => 'Tautan pendaftaran tidak bisa dibuka.',
        ]);

        $this->pendingEvent->moderationLogs()->create([
            'actor_user_id' => null,
            'action' => 'auto_flagged',
            'rule_key' => 'keyword_density',
            'reason' => 'Kata kunci "gratis" muncul berlebihan.',
            'risk_score' => 35,
        ]);

        $this->pendingEvent->moderationLogs()->create([
            'actor_user_id' => $this->admin->id,
            'action' => 'submit',
            'reason' => 'Diajukan oleh penyedia.',
            'risk_score' => 35,
        ]);
    }

    // ------------------------------------------------------- halaman publik

    /** @return array<string, array{string}> */
    public static function publicPageProvider(): array
    {
        return [
            'beranda' => ['/'],
            'daftar kegiatan' => ['/kegiatan'],
            'daftar kegiatan dengan filter' => ['/kegiatan?category=1&mode=online&sort=terlama&q=lomba'],
            'daftar penyelenggara' => ['/penyelenggara'],
            'tentang' => ['/tentang'],
            'kebijakan moderasi' => ['/kebijakan-moderasi'],
            'panduan penyelenggara' => ['/panduan-penyelenggara'],
            'kebijakan privasi' => ['/kebijakan-privasi'],
            'kontak' => ['/kontak'],
        ];
    }

    #[DataProvider('publicPageProvider')]
    #[Test]
    public function halaman_publik_selalu_bisa_dirender(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    #[Test]
    public function detail_kegiatan_bisa_dirender(): void
    {
        $this->get('/kegiatan/'.$this->published->slug)->assertOk();
        $this->actingAs($this->plainUser)->get('/kegiatan/'.$this->published->slug)->assertOk();
    }

    #[Test]
    public function profil_penyelenggara_bisa_dirender(): void
    {
        $this->get('/penyelenggara/'.$this->verifiedProfile->routeSlug())->assertOk();
    }

    // --------------------------------------------------- halamanotentitas

    /** @return array<string, array{string}> */
    public static function guestPageProvider(): array
    {
        return [
            'masuk' => ['/masuk'],
            'daftar' => ['/daftar'],
            'daftar penyelenggara' => ['/daftar/penyelenggara'],
            'lupa kata sandi' => ['/lupa-sandi'],
        ];
    }

    #[DataProvider('guestPageProvider')]
    #[Test]
    public function halaman_tamu_bisa_dirender(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function userPageProvider(): array
    {
        return [
            'dasbor' => ['/dasbor'],
            'tersimpan' => ['/tersimpan'],
            'profil' => ['/profil'],
            'ganti kata sandi' => ['/profil/sandi'],
            'formulir laporkan' => ['/kegiatan/{event}/laporkan'],
        ];
    }

    #[DataProvider('userPageProvider')]
    #[Test]
    public function halaman_pengguna_bisa_dirender(string $uri): void
    {
        $this->actingAs($this->plainUser)
            ->get(str_replace('{event}', (string) $this->published->id, $uri))
            ->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function providerPageProvider(): array
    {
        return [
            'dasbor' => ['/dasbor/penyelenggara'],
            'profil organisasi' => ['/dasbor/penyelenggara/profil-organisasi'],
            'daftar kegiatan' => ['/dasbor/penyelenggara/kegiatan'],
            'buat kegiatan' => ['/dasbor/penyelenggara/kegiatan/baru'],
            'sunting draf' => ['/dasbor/penyelenggara/kegiatan/{event}/sunting'],
            'detail kegiatan' => ['/dasbor/penyelenggara/kegiatan/{event}'],
        ];
    }

    #[DataProvider('providerPageProvider')]
    #[Test]
    public function halaman_penyedia_bisa_dirender(string $uri): void
    {
        $this->actingAs($this->providerUser)
            ->get(str_replace('{event}', (string) $this->draftEvent->id, $uri))
            ->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function adminPageProvider(): array
    {
        return [
            'dasbor' => ['/admin'],
            'antrean kegiatan' => ['/admin/kegiatan'],
            'tinjau kegiatan' => ['/admin/kegiatan/{event}'],
            'verifikasi organisasi' => ['/admin/penyelenggara'],
            'laporan' => ['/admin/laporan'],
            'pengguna' => ['/admin/pengguna'],
            'kategori' => ['/admin/kategori'],
            'jejak audit' => ['/admin/audit'],
        ];
    }

    #[DataProvider('adminPageProvider')]
    #[Test]
    public function halaman_admin_bisa_dirender(string $uri): void
    {
        $this->actingAs($this->admin)
            ->get(str_replace('{event}', (string) $this->pendingEvent->id, $uri))
            ->assertOk();
    }

    // ------------------------------------------------------------- utilitas

    private function makeUser(string $email, UserRole $role): User
    {
        return User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => 'rahasia-kuat-123',
            'role' => $role,
        ]);
    }

    private function makeProfile(User $owner, string $email, string $organization): ProviderProfile
    {
        return ProviderProfile::create([
            'user_id' => $owner->id,
            'organization_name' => $organization,
            'campus_name' => 'Universitas Smoke',
            'official_email' => $email,
        ]);
    }

    private function makeEvent(string $slug, EventStatus $status, bool $publish): Event
    {
        return Event::create([
            'provider_profile_id' => $this->verifiedProfile->id,
            'category_id' => $this->category->id,
            'title' => 'Lomba Smoke '.$slug,
            'slug' => $slug,
            'description' => str_repeat('Deskripsi kegiatan yang panjang untuk lolos validasi. ', 4),
            'registration_url' => 'https://pendaftaran.test/'.$slug,
            'mode' => EventMode::Online,
            'platform_url' => 'https://meet.test/'.$slug,
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addDay(),
            'registration_deadline' => now()->addWeek(),
            'status' => $status,
            'risk_score' => $status === EventStatus::Pending ? 35 : 0,
            'published_at' => $publish ? now() : null,
        ]);
    }
}