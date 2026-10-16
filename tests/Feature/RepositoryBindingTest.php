<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ReportReason;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Policies\EventReportPolicy;
use App\Policies\ProviderProfilePolicy;
use App\Policies\UserPolicy;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membuktikan setiap kontrak benar-benar terikat ke implementasi, dan
 * gerbang otorisasi memberi jawaban yang benar untuk tiap role.
 */
class RepositoryBindingTest extends TestCase
{
    use DatabaseTruncation;

    /** @return array<string, array{class-string}> */
    public static function contractProvider(): array
    {
        return [
            'user' => [UserRepository::class],
            'event' => [EventRepository::class],
            'provider' => [ProviderProfileRepository::class],
            'bookmark' => [BookmarkRepository::class],
            'category' => [CategoryRepository::class],
            'moderation log' => [ModerationLogRepository::class],
            'report' => [ReportRepository::class],
            'audit log' => [AuditLogRepository::class],
        ];
    }

    #[Test]
    #[DataProvider('contractProvider')]
    public function kontrak_repository_menghasilkan_implementasi_yang_benar(string $contract): void
    {
        $resolved = app($contract);

        $this->assertInstanceOf($contract, $resolved, "Kontrak {$contract} tidak terikat.");
        // bind() (bukan singleton) -> setiap resolusi mengembalikan instance
        // baru, supaya tidak ada state yang bocor antar permintaan.
        $this->assertNotSame($resolved, app()->make($contract));
    }

    #[Test]
    public function seluruh_policy_terpetakan_ke_modelnya(): void
    {
        $expected = [
            Event::class => EventPolicy::class,
            ProviderProfile::class => ProviderProfilePolicy::class,
            EventReport::class => EventReportPolicy::class,
            User::class => UserPolicy::class,
        ];

        foreach ($expected as $model => $policy) {
            $this->assertInstanceOf($policy, Gate::getPolicyFor($model), "Policy untuk {$model} belum terpetakan.");
        }
    }

    #[Test]
    public function gerbang_admin_hanya_terbuka_untuk_admin(): void
    {
        $admin = $this->user('admin@gate.test', UserRole::Admin);
        $provider = $this->user('provider@gate.test', UserRole::Provider);
        $user = $this->user('user@gate.test', UserRole::User);

        foreach (['moderate-events', 'manage-users', 'view-audit-log'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), "Admin harus boleh: {$ability}");
            $this->assertFalse(Gate::forUser($provider)->allows($ability), "Penyedia tidak boleh: {$ability}");
            $this->assertFalse(Gate::forUser($user)->allows($ability), "User tidak boleh: {$ability}");
        }
    }

    #[Test]
    public function akun_ditangguhkan_tidak_bisa_mengakses_aplikasi(): void
    {
        $suspended = $this->user('suspend@gate.test', UserRole::Admin);
        $this->assertTrue($suspended->isAdmin());

        // Peran boleh, tapi status tidak — dua pemeriksaan terpisah.
        $suspended->forceFill(['account_status' => 'suspended', 'suspended_at' => now()])->save();
        $suspended->refresh();

        $this->assertFalse($suspended->canAccessApplication());
    }

    // --------------------------------------------------- bookmark on conflict

    #[Test]
    public function bookmark_klik_ganda_tidak_menghasilkan_baris_ganda(): void
    {
        $repo = app(BookmarkRepository::class);
        $user = $this->user('bookmark@gate.test', UserRole::User);
        $event = $this->event();

        $this->assertTrue($repo->add($user->id, $event->id), 'Klik pertama harus membuat baris.');
        $this->assertFalse($repo->add($user->id, $event->id), 'Klik kedua harus ditolak, bukan menggandakan.');
        $this->assertSame(1, $repo->countForEvent($event->id));

        $this->assertTrue($repo->exists($user->id, $event->id));
        $this->assertTrue($repo->remove($user->id, $event->id));
        $this->assertFalse($repo->remove($user->id, $event->id), 'Penghapusan kedua harus mengembalikan false.');
        $this->assertSame(0, $repo->countForEvent($event->id));
    }

    #[Test]
    public function dua_pengguna_boleh_menyimpan_kegiatan_yang_sama(): void
    {
        $repo = app(BookmarkRepository::class);
        $event = $this->event();

        $a = $this->user('a@bm.test', UserRole::User);
        $b = $this->user('b@bm.test', UserRole::User);

        $this->assertTrue($repo->add($a->id, $event->id));
        $this->assertTrue($repo->add($b->id, $event->id));
        $this->assertSame(2, $repo->countForEvent($event->id));
    }

    // ------------------------------------------------- katalog tidak bocor

    #[Test]
    public function katalog_publik_tidak_pernah_mengembalikan_status_lain(): void
    {
        $repo = app(EventRepository::class);
        $admin = $this->user('catalog-admin@test', UserRole::Admin);
        $provider = $this->user('catalog-prov@test', UserRole::Provider);
        $profile = ProviderProfile::create([
            'user_id' => $provider->id,
            'organization_name' => 'BEM Katalog',
            'campus_name' => 'Kampus A',
            'official_email' => 'katalog@a.test',
        ]);
        $category = Category::create(['name' => 'Lomba', 'slug' => 'lomba']);

        foreach (['draft', 'pending', 'rejected', 'changes_requested', 'approved'] as $status) {
            $this->rawEvent($profile->id, $category->id, $status);
        }

        $filter = CatalogFilter::fromQuery([]);

        $visible = $repo->paginatePublicCatalog($filter)
            ->getCollection()
            ->map(fn (Event $e): string => $e->status->value)
            ->all();

        $this->assertSame(['approved'], $visible);
    }

    #[Test]
    public function parameter_aneh_dari_query_string_ditolak_dengan_tenang(): void
    {
        $filter = CatalogFilter::fromQuery([
            'sort' => 'published_at; DROP TABLE events; --',
            'per_page' => '99999',
            'category' => '-5',
            'from' => 'bukan-tanggal',
            'mode' => 'daring',
            'status' => 'ngawur',
            'q' => str_repeat('a', 500),
        ]);

        $this->assertSame('terbaru', $filter->sort, 'Sort tak dikenal harus jatuh ke bawaan.');
        $this->assertSame(48, $filter->perPage, 'Jumlah per halaman harus dibatasi.');
        $this->assertNull($filter->categoryId, 'Kategori negatif harus diabaikan.');
        $this->assertNull($filter->startsFrom, 'Tanggal rusak harus diabaikan, bukan error 500.');
        $this->assertNull($filter->mode, 'Mode tak dikenal harus diabaikan.');
        $this->assertNull($filter->status, 'Status tak dikenal harus diabaikan.');
        $this->assertSame(100, mb_strlen((string) $filter->keyword), 'Kata kunci harus dipotong.');
    }

    #[Test]
    public function metadata_audit_log_menyembunyikan_kunci_sensitif(): void
    {
        $audit = app(AuditLogRepository::class);
        $log = $audit->record(
            action: 'user.suspended',
            actorUserId: $this->user('audit@test', UserRole::Admin)->id,
            actorRole: 'admin',
            targetType: User::class,
            targetId: 1,
            metadata: [
                'nama' => 'Budi',
                'password' => 'rahasia',
                'nested' => ['api_key' => 'kunci', 'aman' => 'boleh'],
                'email' => 'budi@contoh.test',
            ],
        );

        $metadata = $log->metadata;

        $this->assertSame('Budi', $metadata['nama']);
        $this->assertSame('[disembunyikan]', $metadata['password']);
        $this->assertSame('[disembunyikan]', $metadata['nested']['api_key']);
        $this->assertSame('boleh', $metadata['nested']['aman']);
        $this->assertSame('[disembunyikan]', $metadata['email']);
    }

    #[Test]
    public function pelapor_sama_menemukan_laporannya_yang_sudah_ada(): void
    {
        $repo = app(ReportRepository::class);
        $reporter = $this->user('reporter@test', UserRole::User);
        $event = $this->event();

        $first = $repo->create($event->id, $reporter->id, ReportReason::Spam, ' Judul mencurigakan');

        $this->assertSame(
            $first->id,
            $repo->findExisting($event->id, $reporter->id)?->id,
            'Pelapor kedua atas kegiatan sama harus menemukan laporan yang sudah ada.',
        );
    }

    // --------------------------------------------------------------- helper

    private function user(string $email, UserRole $role): User
    {
        return User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => 'rahasia-kuat-123',
            'role' => $role,
        ]);
    }

    private function event(?EventStatus $status = null): Event
    {
        // WAJIB dihitung sekali di awal. Kalau ditulis inline di array,
        // $status masih null saat published_at dievaluasi, sehingga
        // kegiatan approved tersimpan tanpa published_at dan ditolak
        // oleh events_published_check.
        $status ??= EventStatus::Approved;

        $provider = $this->user('owner-' . uniqid() . '@test', UserRole::Provider);
        $profile = ProviderProfile::create([
            'user_id' => $provider->id,
            'organization_name' => 'BEM Repo',
            'campus_name' => 'Kampus A',
            'official_email' => 'repo@a.test',
        ]);
        $profile->markVerified(User::create(['name' => 'V', 'email' => 'v' . uniqid() . '@test', 'password' => 'x', 'role' => UserRole::Admin]));

        $category = Category::first() ?? Category::create(['name' => 'Lomba', 'slug' => 'lomba']);

        return Event::create([
            'provider_profile_id' => $profile->id,
            'category_id' => $category->id,
            'title' => 'Lomba Repo ' . uniqid(),
            'slug' => 'lomba-repo-' . uniqid(),
            'description' => 'Deskripsi yang cukup panjang untuk lolos constraint database.',
            'registration_url' => 'https://daftar.test/x',
            'mode' => EventMode::Online,
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addHour(),
            'registration_deadline' => now()->addWeek(),
            'status' => $status,
            'published_at' => $status === EventStatus::Approved ? now() : null,
        ]);
    }

    private function rawEvent(int $providerId, int $categoryId, string $status): void
    {
        Event::create([
            'provider_profile_id' => $providerId,
            'category_id' => $categoryId,
            'title' => 'Kegiatan Status ' . $status,
            'slug' => 'status-' . $status . '-' . uniqid(),
            'description' => 'Deskripsi yang cukup panjang untuk lolos constraint database.',
            'registration_url' => 'https://daftar.test/x',
            'mode' => EventMode::Online,
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addHour(),
            'registration_deadline' => now()->addWeek(),
            'status' => $status,
            'published_at' => $status === 'approved' ? now() : null,
        ]);
    }
}
