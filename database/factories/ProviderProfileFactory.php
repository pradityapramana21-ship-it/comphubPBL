<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 *
 * Default-nya profil yang MASIH MENUNGGU verifikasi. Itu pilihan yang
 * sengaja: inilah kondisi kebanyakan penyedia saat pertama kali mendaftar,
 * jadi test yang membuat profil tanpa menyatakan keadaan harus berakhir
 * di antrean admin, bukan di daftar tayang.
 */
class ProviderProfileFactory extends Factory
{
    protected $model = ProviderProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = fake()->company().' Unit '.Str::upper(Str::random(5));

        return [
            'user_id' => User::factory()->provider(),
            'organization_name' => $organization,
            'campus_name' => fake()->randomElement([
                'Universitas Indonesia', 'Institut Teknologi Bandung',
                'Universitas Gadjah Mada', 'Universitas Airlangga',
            ]),
            'official_email' => Str::lower(Str::slug($organization, '-')).'@example.test',
            'website_url' => 'https://'.Str::slug($organization, '-').'.example.test',
            'description' => fake()->paragraph(3),
            'verification_status' => VerificationStatus::Pending->value,
        ];
    }

    /** Profil milik user tertentu, supaya tidak dibuatkan akun tambahan. */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'official_email' => $user->email,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Verified->value,
            'verified_at' => now(),
        ]);
    }

    public function rejected(string $note = 'Bukti organisasi tidak bisa diperiksa.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Rejected->value,
            'verification_note' => $note,
        ]);
    }
}
