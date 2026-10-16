<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationRule;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Kaki yang dipakai setiap test aturan: menyiapkan satu organisasi penyedia
 * dan cara cepat membuat ModerationContext.
 *
 * Memakai DatabaseTruncation karena beberapa aturan (duplikat, rate limit)
 * benar-benar membaca database.
 */
abstract class ModerationRuleTestCase extends TestCase
{
    use DatabaseTruncation;

    protected ProviderProfile $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Admin Moderasi',
            'email' => 'admin-moderasi@test',
            'password' => 'rahasia-kuat-123',
            'role' => \App\Enums\UserRole::Admin,
        ]);

        $owner = User::create([
            'name' => 'BEM Uji',
            'email' => 'bem-uji@test',
            'password' => 'rahasia-kuat-123',
            'role' => \App\Enums\UserRole::Provider,
        ]);

        $this->provider = ProviderProfile::create([
            'user_id' => $owner->id,
            'organization_name' => 'BEM Universitas Uji',
            'campus_name' => 'Universitas Uji',
            'official_email' => 'bem@uji.test',
        ]);

        $this->provider->markVerified($admin);

        Category::first() ?? Category::create(['name' => 'Lomba', 'slug' => 'lomba']);
    }

    /**
     * Buat konteks dengan default yang sudah VALID, supaya setiap test hanya
     * mengubah satu field. Kalau defaultnya tidak valid, setiap aturan akan
     * menyala bersamaan dan test tidak bisa mengisolasi penyebabnya.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function context(array $overrides = []): ModerationContext
    {
        return new ModerationContext(
            title: (string) ($overrides['title'] ?? 'Lomba Nasional Pemrograman 2026 untuk Mahasiswa'),
            description: (string) ($overrides['description'] ?? str_repeat('Deskripsi kegiatan yang cukup panjang untuk lolos pemeriksaan kualitas isi. ', 3)),
            requirements: $overrides['requirements'] ?? null,
            registrationUrl: (string) ($overrides['registrationUrl'] ?? 'https://pendaftaran.uji.ac.id/lomba'),
            platformUrl: $overrides['platformUrl'] ?? null,
            locationText: $overrides['locationText'] ?? null,
            eventStartAt: $overrides['eventStartAt'] ?? now()->addMonth()->toDateTimeImmutable(),
            eventEndAt: $overrides['eventEndAt'] ?? now()->addMonth()->addDay()->toDateTimeImmutable(),
            registrationDeadline: $overrides['registrationDeadline'] ?? now()->addWeek()->toDateTimeImmutable(),
            provider: $overrides['provider'] ?? $this->provider,
            eventId: $overrides['eventId'] ?? null,
        );
    }

    /**
     * Aturan yang diuji. Hanya test per aturan yang perlu mengimplementasikan
     * ini — test tingkat mesin (ModerationServiceTest) memakai kelas dasar
     * yang sama tanpa sebuah aturan tunggal.
     */
    protected function rule(): ModerationRule
    {
        throw new \LogicException('Test ini tidak menguji satu aturan spesifik.');
    }

    /**
     * Helpers supaya setiap test bisa fokus pada "menyala atau tidak",
     * bukan pada bentuk objek hasilnya.
     */
    protected function fires(array $overrides = []): bool
    {
        return $this->rule()->evaluate($this->context($overrides)) !== null;
    }

    protected function evaluate(array $overrides = []): ?\App\Services\Moderation\RuleResult
    {
        return $this->rule()->evaluate($this->context($overrides));
    }

    /** Penyedia yang belum diverifikasi. */
    protected function unverifiedProvider(): ProviderProfile
    {
        $owner = User::create([
            'name' => 'Organisasi Baru',
            'email' => 'baru-'.uniqid().'@test',
            'password' => 'rahasia-kuat-123',
            'role' => \App\Enums\UserRole::Provider,
        ]);

        return ProviderProfile::create([
            'user_id' => $owner->id,
            'organization_name' => 'Organisasi Belum Terverifikasi',
            'campus_name' => 'Kampus lain',
            'official_email' => 'baru@uji.test',
        ]);
    }

    protected function assertQuietly(bool $condition, string $message): void
    {
        $this->assertTrue($condition, $message);
    }
}
