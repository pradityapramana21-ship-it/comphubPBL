<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membuktikan aturan yang ditegakkan oleh PostgreSQL itu sendiri, bukan
 * sekadar validasi form di aplikasi (Panduan §6.4).
 *
 * Test-case ID memakai pola yang sama dengan Test Plan di docs/03-testing.
 */
class SchemaConstraintTest extends TestCase
{
    use DatabaseTruncation;

    private const USERS_SQL = <<<'SQL'
        INSERT INTO users (id, name, email, password, role, account_status, created_at, updated_at)
        VALUES (:id, :name, :email, 'x', :role, 'active', NOW(), NOW())
        SQL;

    private const PROVIDERS_SQL = <<<'SQL'
        INSERT INTO provider_profiles
            (id, user_id, organization_name, campus_name, official_email, verification_status, verified_at, created_at, updated_at)
        VALUES (:id, :uid, :org, :campus, :email, :status, :verified_at, NOW(), NOW())
        SQL;

    private const EVENTS_SQL = <<<'SQL'
        INSERT INTO events
            (id, provider_profile_id, category_id, title, slug, description, registration_url, mode,
             event_start_at, event_end_at, registration_deadline, status, risk_score, published_at, created_at, updated_at)
        VALUES
            (:id, 1, 1, :title, :slug, 'deskripsi', 'https://contoh.test', :mode,
             '2026-12-01 10:00:00+07', '2026-12-01 12:00:00+07', '2026-11-20 10:00:00+07', :status, :risk,
             :published_at, NOW(), NOW())
        SQL;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@t.test', 'password' => 'x', 'role' => 'admin', 'account_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'User1', 'email' => 'u1@t.test', 'password' => 'x', 'role' => 'user', 'account_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Admin2', 'email' => 'a2@t.test', 'password' => 'x', 'role' => 'admin', 'account_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('categories')->insert([
            'id' => 1, 'name' => 'Lomba', 'slug' => 'lomba', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('provider_profiles')->insert([
            ['id' => 1, 'user_id' => 1, 'organization_name' => 'BEM', 'campus_name' => 'Kampus A', 'official_email' => 'bem@a.test', 'verification_status' => 'verified', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 2, 'organization_name' => 'BEM2', 'campus_name' => 'Kampus B', 'official_email' => 'b@b.test', 'verification_status' => 'pending', 'verified_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->insertEvent(id: 1, title: 'Lomba Sah', slug: 'lomba-sah', mode: 'online', status: 'approved', risk: 0, publishedAt: now());
        $this->insertEvent(id: 2, title: 'Lomba Kembar', slug: 'lomba-kembar', mode: 'online', status: 'draft', risk: 0, publishedAt: null);
    }

    private function insertEvent(
        int $id,
        string $title,
        string $slug,
        string $mode,
        string $status,
        int $risk,
        mixed $publishedAt,
        ?string $location = null,
    ): void {
        DB::table('events')->insert([
            'id' => $id,
            'provider_profile_id' => 1,
            'category_id' => 1,
            'title' => $title,
            'slug' => $slug,
            'description' => 'deskripsi',
            'registration_url' => 'https://contoh.test',
            'mode' => $mode,
            'location_text' => $location,
            'event_start_at' => '2026-12-01 10:00:00+07',
            'event_end_at' => '2026-12-01 12:00:00+07',
            'registration_deadline' => '2026-11-20 10:00:00+07',
            'status' => $status,
            'risk_score' => $risk,
            'published_at' => $publishedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- events

    #[Test]
    public function deadline_yang_melewati_tanggal_acara_ditolak_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('events')->insert([
            'id' => 99, 'provider_profile_id' => 1, 'category_id' => 1,
            'title' => 'Salah', 'slug' => 'salah-1', 'description' => 'd',
            'registration_url' => 'https://a.test', 'mode' => 'online',
            'event_start_at' => '2026-12-01 10:00:00+07',
            'event_end_at' => '2026-12-01 12:00:00+07',
            'registration_deadline' => '2026-12-05 10:00:00+07',
            'status' => 'draft', 'risk_score' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function kegiatan_offline_tanpa_lokasi_ditolak_database(): void
    {
        $this->expectException(QueryException::class);

        $this->insertEvent(99, 'Tanpa Lokasi', 'tanpa-lokasi', 'offline', 'draft', 0, null);
    }

    #[Test]
    public function status_kegiatan_tidak_dikenal_ditolak_database(): void
    {
        $this->expectException(QueryException::class);

        $this->insertEvent(99, 'Status Ngawur', 'status-ngawur', 'online', 'ngawur', 0, null);
    }

    #[Test]
    public function risk_score_di_luar_rentang_ditolak_database(): void
    {
        $this->expectException(QueryException::class);

        $this->insertEvent(99, 'Skor Gila', 'skor-gila', 'online', 'draft', 999, null);
    }

    #[Test]
    public function approved_tanpa_published_at_ditolak_database(): void
    {
        $this->expectException(QueryException::class);

        $this->insertEvent(99, 'Tanpa Timestamp Tayang', 'tanpa-tayang', 'online', 'approved', 0, null);
    }

    #[Test]
    public function kegiatan_online_tanpa_lokasi_berhasil_disimpan(): void
    {
        $this->insertEvent(99, 'Seminar Online', 'seminar-online', 'online', 'draft', 0, null);

        $this->assertDatabaseCount('events', 3);
        $this->assertDatabaseHas('events', ['slug' => 'seminar-online']);
    }

    #[Test]
    public function kegiatan_offline_dengan_lokasi_berhasil_disimpan(): void
    {
        $this->insertEvent(99, 'Lomba Offline', 'lomba-offline', 'offline', 'draft', 0, null, 'Gedung A Lantai 3');

        $this->assertDatabaseHas('events', [
            'slug' => 'lomba-offline',
            'location_text' => 'Gedung A Lantai 3',
        ]);
    }

    #[Test]
    public function slug_yang_sudah_dipakai_ditolak(): void
    {
        $this->expectException(QueryException::class);

        DB::table('events')->where('id', 2)->update(['slug' => 'lomba-sah']);
    }

    // ------------------------------------------------------------- bookmarks

    #[Test]
    public function bookmark_ganda_dari_user_yang_sama_ditolak(): void
    {
        DB::table('bookmarks')->insert(['user_id' => 2, 'event_id' => 1, 'created_at' => now()]);

        $this->expectException(QueryException::class);

        DB::table('bookmarks')->insert(['user_id' => 2, 'event_id' => 1, 'created_at' => now()]);
    }

    #[Test]
    public function dua_user_berbeda_bisa_menyimpan_kegiatan_yang_sama(): void
    {
        DB::table('bookmarks')->insert([
            ['user_id' => 2, 'event_id' => 1, 'created_at' => now()],
            ['user_id' => 3, 'event_id' => 1, 'created_at' => now()],
        ]);

        $this->assertDatabaseCount('bookmarks', 2);
    }

    // ---------------------------------------------------- provider_profiles

    #[Test]
    public function verifikasi_tanpa_verified_at_ditolak(): void
    {
        $this->expectException(QueryException::class);

        DB::table('provider_profiles')->where('id', 2)
            ->update(['verification_status' => 'verified']);
    }

    #[Test]
    public function verifikasi_dengan_verified_at_berhasil(): void
    {
        DB::table('provider_profiles')->where('id', 2)->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => 1,
        ]);

        $this->assertSame('verified', DB::table('provider_profiles')->where('id', 2)->value('verification_status'));
    }

    #[Test]
    public function satu_akun_hanya_boleh_punya_satu_profil_organisasi(): void
    {
        $this->expectException(QueryException::class);

        DB::table('provider_profiles')->insert([
            'user_id' => 1, 'organization_name' => 'Ganda', 'campus_name' => 'Kampus C',
            'official_email' => 'g@c.test', 'verification_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --------------------------------------------------------- event_reports

    #[Test]
    public function pelapor_tidak_boleh_melapor_kegiatan_yang_sama_duakali(): void
    {
        DB::table('event_reports')->insert([
            'event_id' => 1, 'reporter_user_id' => 2, 'reason_code' => 'spam',
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('event_reports')->insert([
            'event_id' => 1, 'reporter_user_id' => 2, 'reason_code' => 'scam',
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ audit_logs

    #[Test]
    public function audit_log_bertahan_setelah_aktor_dihapus(): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => 3, 'actor_role' => 'admin', 'action' => 'user.suspended',
            'target_type' => 'App\Models\User', 'target_id' => 2, 'created_at' => now(),
        ]);

        DB::table('users')->where('id', 3)->delete();

        // Jejak harus tetap ada walau pelakunya dihapus — inilah alasan
        // audit_logs sengaja tidak memakai foreign key.
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => 3]);
        $this->assertDatabaseMissing('users', ['id' => 3]);
    }

    // ----------------------------------------------------------------- users

    #[Test]
    public function akun_ditangguhkan_wajib_punya_catatan_waktu(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->where('id', 2)->update(['account_status' => 'suspended']);
    }

    #[Test]
    public function role_tak_dikenal_ditolak(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->where('id', 2)->update(['role' => 'superadmin']);
    }

    // ----------------------------------------------------------- kolom waktu

    #[Test]
    public function semua_kolom_waktu_pada_tabel_domain_memakai_timestamptz(): void
    {
        $domainTables = [
            'users', 'categories', 'provider_profiles', 'events',
            'bookmarks', 'moderation_logs', 'event_reports', 'audit_logs',
        ];

        $naive = DB::table('information_schema.columns')
            ->select('table_name', 'column_name')
            ->whereIn('table_name', $domainTables)
            ->where('table_schema', 'public')
            ->where('data_type', 'timestamp without time zone')
            ->get();

        $this->assertCount(
            0,
            $naive,
            'Kolom waktu berikut masih tanpa zona waktu (Panduan §6.4 mewajibkan timestamptz): '
                . $naive->map(fn ($c): string => "{$c->table_name}.{$c->column_name}")->implode(', ')
        );
    }
}
