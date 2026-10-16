<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\EventStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Factory harus benar-benar bisa dipakai.
 *
 * Test ini memeriksa setiap factory satu per satu, bukan lewat fitur.
 * Alasannya: factory yang rusak baru ketahuan saat test fitur memakainya,
 * dan gejalanya jauh dari penyebabnya. Contoh nyatanya adalah
 * "Maximum retries of 10000 reached without finding a unique value" —
 * yang rusak bukan moderasi, melainkan cara factory meminta email unik
 * dari Faker.
 *
 * Yang diperiksa di sini adalah kesesuaiannya dengan skema: kolom yang
 * punya CHECK constraint, kolom NOT NULL, dan kolom yang sengaja TIDAK
 * masuk daftar fillable.
 */
class FactoryTest extends TestCase
{
    use DatabaseTruncation;

    #[Test]
    public function akun_unggul_dan_bisa_dibuat_dalam_segala_keadaan(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = User::factory()->provider()->create();
        $plain = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $suspended = User::factory()->suspended()->create();

        $this->assertSame('admin', $admin->role->value);
        $this->assertSame('provider', $provider->role->value);
        $this->assertSame('user', $plain->role->value);

        // Akun hasil factory dianggap sudah verifikasi; state unverified()
        // harus benar-benar menimpanya.
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNull($unverified->fresh()->email_verified_at);

        // suspended_at dan suspension_note tidak fillable, jadi harus
        // benar-benar tertulis di database, bukan sekadar diisi di memori.
        $this->assertSame('suspended', $suspended->fresh()->account_status->value);
        $this->assertNotNull($suspended->fresh()->suspended_at);
        $this->assertNotNull($suspended->fresh()->suspension_note);
    }

    #[Test]
    public function email_hasil_factory_selalu_berbeda(): void
    {
        $emails = User::factory(25)->create()->pluck('email')->unique();

        $this->assertCount(25, $emails, 'Ada email factory yang kembar.');
    }

    #[Test]
    public function profil_organisasi_dapat_berbagi_status_verifikasi(): void
    {
        $user = User::factory()->provider()->create();

        $pending = ProviderProfile::factory()->create();
        $verified = ProviderProfile::factory()->verified()->forUser($user)->create();
        $rejected = ProviderProfile::factory()->rejected()->create();

        $this->assertSame('pending', $pending->verification_status->value);
        $this->assertSame('verified', $verified->verification_status->value);
        $this->assertNotNull($verified->verified_at);
        $this->assertSame('rejected', $rejected->verification_status->value);
        $this->assertNotNull($rejected->verification_note);

        $this->assertSame($user->id, $verified->user_id);
        $this->assertSame($user->email, $verified->official_email);
    }

    #[Test]
    public function kategori_boleh_aktif_maupun_nonaktif(): void
    {
        $active = Category::factory()->create();
        $inactive = Category::factory()->inactive()->create();

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);
    }

    /**
     * Setiap status punya state-nya sendiri, dan semua harus bisa lolos
     * CHECK constraint di PostgreSQL.
     */
    #[Test]
    public function kegiatan_dapat_dibuat_di_semua_status(): void
    {
        $states = [
            Event::factory(),
            Event::factory()->pending(),
            Event::factory()->published(),
            Event::factory()->rejected(),
            Event::factory()->changesRequested(),
            Event::factory()->archived(),
            Event::factory()->deadlinePassed(),
        ];

        foreach ($states as $index => $factory) {
            $event = $factory->create();

            $this->assertSame(
                $event->fresh()->status->value,
                $event->status->value,
                'Status kegiatan hasil factory tidak tersimpan utuh.',
            );
            $this->assertGreaterThan(0, $index === 0 ? 1 : $event->id);
        }

        $this->assertSame(EventStatus::Approved->value, Event::factory()->published()->create()->status->value);
        $this->assertTrue(Event::factory()->deadlinePassed()->create()->registration_deadline->isPast());
    }

    #[Test]
    public function kegiatan_bisa_ditempelkan_ke_pemilik_dan_kategori_tertentu(): void
    {
        $profile = ProviderProfile::factory()->verified()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->forProvider($profile)->inCategory($category)->create();

        $this->assertSame($profile->id, $event->provider_profile_id);
        $this->assertSame($category->id, $event->category_id);
        $this->assertSame($event->id, $profile->events()->value('id'));
    }

    #[Test]
    public function slug_kegiatan_tidak_pernah_kembar(): void
    {
        $slugs = Event::factory(20)->create()->pluck('slug')->unique();

        $this->assertCount(20, $slugs, 'Ada slug kegiatan hasil factory yang kembar.');
    }

    #[Test]
    public function laporan_dan_jejak_audit_dapat_dibuat(): void
    {
        $admin = User::factory()->admin()->create();
        $reporter = User::factory()->create();
        $event = Event::factory()->published()->create();

        $open = EventReport::factory()->forEvent($event, $reporter)->create();
        $resolved = EventReport::factory()->resolved($admin)->create();

        $this->assertSame('open', $open->status->value);
        $this->assertSame($event->id, $open->event_id);
        $this->assertSame($reporter->id, $open->reporter_user_id);

        $this->assertSame('resolved', $resolved->status->value);
        $this->assertSame($admin->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);

        $log = AuditLog::factory()->create();
        $system = AuditLog::factory()->bySystem()->create();

        $this->assertNotNull($log->id);
        $this->assertSame('admin', $log->actor_role);
        $this->assertNull($system->actor_user_id);
        $this->assertNull($system->actor_role);
    }
}
