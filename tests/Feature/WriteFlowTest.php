<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Alur TULIS, dari ujung ke ujung lewat HTTP.
 *
 * ViewSmokeTest hanya membuka halaman; RoutingTest hanya memastikan
 * middleware menahan. Keduanya tidak pernah mengirim form. Padahal di
 * situlah kesalahan yang paling merusak muncul: Form Request yang
 * mengambil parameter rute salah, policy yang tidak pernah dipanggil,
 * atau kolom yang tidak pernah tersimpan.
 *
 * Yang diuji di sini adalah alur yang benar-benar dipakai orang:
 * Provider membuat kegiatan, mengajukan, ditinjau admin; mahasiswa menyimpan
 * dan melaporkan; admin menangani. Semua lewat HTTP, bukan lewat service.
 */
class WriteFlowTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    private User $providerUser;

    private ProviderProfile $profile;

    private User $plainUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->providerUser = User::factory()->provider()->create();
        $this->plainUser = User::factory()->create();
        $this->category = Category::factory()->create(['name' => 'Lomba', 'slug' => 'lomba']);

        $this->profile = ProviderProfile::factory()->verified()->forUser($this->providerUser)->create();
    }

    // ---------------------------------------------------------- penyedia

    #[Test]
    public function penyedia_membuat_draf_lewat_form(): void
    {
        $this->actingAs($this->providerUser)
            ->post('/dasbor/penyelenggara/kegiatan', $this->eventPayload())
            ->assertSessionHasNoErrors();

        $event = Event::where('title', 'Lomba Ketangkasan Membaca 2026')->firstOrFail();

        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertSame($this->profile->id, $event->provider_profile_id);
        $this->assertSame($this->category->id, $event->category_id);
        $this->assertSame(EventMode::Online, $event->mode);
        $this->assertNull($event->published_at, 'Draf tidak boleh punya tanggal tayang.');
    }

    #[Test]
    public function draf_yang_kurang_lengkap_ditolak_dengan_pesan_yang_membantu(): void
    {
        $response = $this->actingAs($this->providerUser)
            ->post('/dasbor/penyelenggara/kegiatan', [
                'title' => 'Lomba',
                'description' => 'Pendek.',
                'registration_url' => 'bukan-url',
                'mode' => 'online',
            ]);

        $response->assertSessionHasErrors(['title', 'description', 'registration_url', 'category_id']);

        $this->assertSame(0, Event::count());
    }

    #[Test]
    public function draf_bisa_diajukan_dan_masuk_antrean_admin(): void
    {
        $draft = $this->makeDraft();

        $this->actingAs($this->providerUser)
            ->post("/dasbor/penyelenggara/kegiatan/{$draft->id}/ajukan")
            ->assertRedirect();

        $draft->refresh();

        $this->assertSame(EventStatus::Pending, $draft->status);
        $this->assertNotNull($draft->submitted_at);

        // Pengajuan harus meninggalkan jejak: minimal satu entri
        // moderation_logs yang menunjuk ke kegiatan ini.
        $this->assertGreaterThan(
            0,
            $draft->moderationLogs()->count(),
            'Pengajuan tidak tercatat di moderation_logs.',
        );
    }

    #[Test]
    public function penyedia_yang_belum_terverifikasi_tidak_bisa_mengajukan(): void
    {
        $waiting = User::factory()->provider()->create();
        $waitingProfile = ProviderProfile::factory()->forUser($waiting)->create();
        $draft = $this->makeDraft($waitingProfile);

        // Middleware 'verified' mengarahkan OrganizeAgain dengan pesan,
        // bukan menjawab 403: organizationsnya memang belum diperiksa,
        // dan pesannya yang mengarahkan.
        $this->actingAs($waiting)
            ->post("/dasbor/penyelenggara/kegiatan/{$draft->id}/ajukan")
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(EventStatus::Draft, $draft->fresh()->status);
    }

    #[Test]
    public function penyedia_tidak_bisa_menyunting_kegiatan_yang_sudah_tayang(): void
    {
        $published = Event::factory()->published()->forProvider($this->profile)->create();
        $judulAwal = $published->title;

        // Jawabannya adalah "kembali ke form dengan pesan", bukan 403:
        // orang memang pemilik kegiatan ini, hanya isinya yang belum boleh
        // berubah tanpa lewat revisi.
        $this->actingAs($this->providerUser)
            ->put("/dasbor/penyelenggara/kegiatan/{$published->id}", $this->eventPayload())
            ->assertSessionHasErrors();

        $this->assertSame(
            $judulAwal,
            $published->fresh()->title,
            'Kegiatan yang sudah tayang tidak boleh ikut berubah.',
        );
        $this->assertNotNull($published->fresh()->published_at);
    }

    #[Test]
    public function penyedia_tidak_bisa_menyunting_kegiatan_milik_orang_lain(): void
    {
        $other = User::factory()->provider()->create();
        $otherProfile = ProviderProfile::factory()->verified()->forUser($other)->create();
        $draft = $this->makeDraft($otherProfile);

        $this->actingAs($this->providerUser)
            ->put("/dasbor/penyelenggara/kegiatan/{$draft->id}", $this->eventPayload())
            ->assertNotFound();
    }

    // ------------------------------------------------------------ admin

    #[Test]
    public function admin_menyetujui_kegiatan_dan_itunya_tayang(): void
    {
        $pending = $this->makeDraft();
        $this->submit($pending);

        $this->actingAs($this->admin)
            ->post("/admin/kegiatan/{$pending->id}/moderasi", ['decision' => 'approve'])
            ->assertRedirect();

        $pending->refresh();

        $this->assertSame(EventStatus::Approved, $pending->status);
        $this->assertNotNull($pending->published_at);

        $this->assertTrue(
            $pending->moderationLogs()->where('action', 'approve')->whereNotNull('actor_user_id')->exists(),
            'Keputusan admin tidak tercatat dengan pelaku yang benar.',
        );
    }

    #[Test]
    public function admin_menolak_kegiatan_dan_alasannya_tersimpan(): void
    {
        $pending = $this->makeDraft();
        $this->submit($pending);

        $this->actingAs($this->admin)
            ->post("/admin/kegiatan/{$pending->id}/moderasi", [
                'decision' => 'reject',
                'reason' => 'Deskripsi belum menjelaskan aturan peserta.',
            ])
            ->assertRedirect();

        $pending->refresh();

        $this->assertSame(EventStatus::Rejected, $pending->status);
        $this->assertSame('Deskripsi belum menjelaskan aturan peserta.', $pending->rejection_reason);
    }

    #[Test]
    public function penolakan_tanpa_alasan_ditolak(): void
    {
        $pending = $this->makeDraft();
        $this->submit($pending);

        $this->actingAs($this->admin)
            ->post("/admin/kegiatan/{$pending->id}/moderasi", ['decision' => 'reject', 'reason' => 'buruk'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(EventStatus::Pending, $pending->fresh()->status);
    }

    #[Test]
    public function penyedia_tidak_bisa_moderasi_karyanya_sendiri(): void
    {
        $pending = $this->makeDraft();
        $this->submit($pending);

        $this->actingAs($this->providerUser)
            ->post("/admin/kegiatan/{$pending->id}/moderasi", ['decision' => 'approve'])
            ->assertForbidden();
    }

    #[Test]
    public function admin_memverifikasi_organisasi(): void
    {
        $waiting = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($waiting)->create();

        $this->actingAs($this->admin)
            ->post("/admin/penyelenggara/{$profile->id}/keputusan", ['decision' => 'verify'])
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame(VerificationStatus::Verified, $profile->verification_status);
        $this->assertSame($this->admin->id, $profile->verified_by);
        $this->assertNotNull($profile->verified_at);
    }

    #[Test]
    public function penolakan_organisasi_wajib_punya_alasan(): void
    {
        $waiting = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($waiting)->create();

        $this->actingAs($this->admin)
            ->post("/admin/penyelenggara/{$profile->id}/keputusan", ['decision' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(VerificationStatus::Pending, $profile->fresh()->verification_status);
    }

    #[Test]
    public function kategori_dengan_nama_yang_sama_ditolak_dengan_pesan(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/kategori', ['name' => 'Lomba'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::where('name', 'Lomba')->count());
    }

    #[Test]
    public function admin_menambah_dan_menonaktifkan_kategori(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/kategori', ['name' => 'Webinar', 'description' => 'Pertemuan daring.'])
            ->assertRedirect();

        $created = Category::where('name', 'Webinar')->firstOrFail();
        $this->assertSame('webinar', $created->slug);

        $this->actingAs($this->admin)
            ->delete("/admin/kategori/{$created->getRouteKey()}")
            ->assertRedirect();

        $created->refresh();
        $this->assertFalse($created->is_active, 'Kategori harus dinonaktifkan, bukan dihapus.');
        $this->assertDatabaseHas('categories', ['id' => $created->id]);
    }

    #[Test]
    public function admin_menangguhkan_akun_mahasiswa(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/pengguna/{$this->plainUser->id}", [
                'account_status' => 'suspended',
                'suspension_note' => 'Melanggar ketentuan layanan.',
            ])
            ->assertRedirect();

        $this->plainUser->refresh();

        $this->assertSame('suspended', $this->plainUser->account_status->value);
        $this->assertNotNull($this->plainUser->suspended_at);
    }

    #[Test]
    public function admin_tidak_bisa_menangguhkan_akun_admin_lain(): void
    {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->patch("/admin/pengguna/{$other->id}", ['account_status' => 'suspended'])
            ->assertForbidden();

        $this->assertSame('active', $other->fresh()->account_status->value);
    }

    // ------------------------------------------------------------- user

    #[Test]
    public function mahasiswa_melaporkan_kegiatan(): void
    {
        $published = Event::factory()->published()->forProvider($this->profile)->create();

        $this->actingAs($this->plainUser)
            ->post("/kegiatan/{$published->id}/laporkan", [
                'reason_code' => ReportReason::BrokenLink->value,
                'description' => 'Tautan pendaftaran berhenti di halaman kosong.',
            ])
            ->assertRedirect();

        $report = EventReport::where('event_id', $published->id)->firstOrFail();

        $this->assertSame($this->plainUser->id, $report->reporter_user_id);
        $this->assertSame(ReportStatus::Open, $report->status);
    }

    #[Test]
    public function laporan_kedua_dari_orang_yang_sama_tidak_diterima(): void
    {
        $published = Event::factory()->published()->forProvider($this->profile)->create();

        $payload = [
            'reason_code' => ReportReason::Spam->value,
            'description' => 'Pengumuman yang sama di facebook.',
        ];

        $this->actingAs($this->plainUser)
            ->post("/kegiatan/{$published->id}/laporkan", $payload)
            ->assertRedirect();

        $this->actingAs($this->plainUser)
            ->post("/kegiatan/{$published->id}/laporkan", $payload)
            ->assertSessionHasErrors();

        $this->assertSame(1, EventReport::where('event_id', $published->id)->count());
    }

    #[Test]
    public function pemilik_kegiatan_tidak_bisa_melapor_karyanya_sendiri(): void
    {
        $published = Event::factory()->published()->forProvider($this->profile)->create();

        $this->actingAs($this->providerUser)
            ->post("/kegiatan/{$published->id}/laporkan", [
                'reason_code' => ReportReason::Other->value,
                'description' => 'Saya-Itu-organizer kegiatan ini sendiri.',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function admin_menangani_laporan(): void
    {
        $published = Event::factory()->published()->forProvider($this->profile)->create();
        $report = EventReport::factory()->forEvent($published, $this->plainUser)->create();

        $this->actingAs($this->admin)
            ->post("/admin/laporan/{$report->id}/selesai", ['note' => 'Tautan sudah diperbaiki.'])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame(ReportStatus::Resolved, $report->status);
        $this->assertSame($this->admin->id, $report->resolved_by);
        $this->assertNotNull($report->resolved_at);
    }

    #[Test]
    public function bookmark_bisa_disimpan_dan_dibatalkan_lewat_json(): void
    {
        $published = Event::factory()->published()->create();

        $save = $this->actingAs($this->plainUser)
            ->postJson("/kegiatan/{$published->id}/bookmark");
        $save->assertOk()->assertJson(['saved' => true]);
        $this->assertSame(1, DB::table('bookmarks')->where('event_id', $published->id)->count());
        $this->assertSame(1, $published->fresh()->bookmark_count);

        $remove = $this->actingAs($this->plainUser)
            ->deleteJson("/kegiatan/{$published->id}/bookmark");
        $remove->assertOk()->assertJson(['saved' => false]);
        $this->assertSame(0, DB::table('bookmarks')->where('event_id', $published->id)->count());
        $this->assertSame(0, $published->fresh()->bookmark_count);
    }

    #[Test]
    public function siapa_tidak_bisa_menyimpan_kegiatan_yang_sudah_ditutup(): void
    {
        $expired = Event::factory()->deadlinePassed()->create();

        $this->actingAs($this->plainUser)
            ->postJson("/kegiatan/{$expired->id}/bookmark")
            ->assertForbidden();
    }

    // ---------------------------------------------------------- privasi

    #[Test]
    public function identitas_pelapor_tidak_ditampilkan_di_halaman_publik(): void
    {
        $published = Event::factory()->published()->forProvider($this->profile)->create();
        EventReport::factory()->forEvent($published, $this->plainUser)->create();

        $this->get('/kegiatan/'.$published->slug)
            ->assertOk()
            ->assertDontSee($this->plainUser->email);
    }

    // ---------------------------------------------------------- helper

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Lomba Ketangkasan Membaca 2026',
            'description' => str_repeat('Lomba membaca untuk mahasiswa. ', 8),
            'registration_url' => 'https://daftar.example.test/lomba-membaca',
            'mode' => EventMode::Online->value,
            'platform_url' => 'https://meet.example.test/lomba-membaca',
            'event_start_at' => now()->addMonth()->toDateTimeString(),
            'event_end_at' => now()->addMonth()->addDay()->toDateTimeString(),
            'registration_deadline' => now()->addWeek()->toDateTimeString(),
        ], $overrides);
    }

    private function makeDraft(?ProviderProfile $owner = null): Event
    {
        return Event::factory()
            ->forProvider($owner ?? $this->profile)
            ->inCategory($this->category)
            ->create();
    }

    /** Ajukan draf lewat jalur resmi, supaya statusnya benar-benar pending. */
    private function submit(Event $event): Event
    {
        $this->actingAs($this->providerUser)
            ->post("/dasbor/penyelenggara/kegiatan/{$event->id}/ajukan");

        return $event->refresh();
    }
}