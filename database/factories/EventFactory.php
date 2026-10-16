<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 *
 * Default-nya kegiatan DRAF milik penyedia yang belum diverifikasi,
 * dengan tenggat yang masih jauh. Dua hal itu bukan kebetulan:
 *
 *  - status draf adalah satu-satunya status yang boleh disunting
 *    penyedia, jadi test form perlu data seperti ini;
 *  - tenggat yang jauh mencegah kegiatan dari factory
 *    ikut kedaluwarsa di tengah test dan membuat assertion jadi flaky.
 *
 * Deskripsi sengaja dibuat panjang. Aturan moderasi menolak isi yang
 * terlalu tipis, jadi test moderasi tidak bisa memakai data contoh
 * yang cuma satu kalimat.
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Keunikan slug dijamin angka acak, bukan modifier unique() dari
        // Faker. UniqueGenerator menyimpan daftarnya di objek Faker itu
        // sendiri, dan di lingkungan ini setiap factory baru mendapat
        // urutan nilai yang sama — sehingga unique() kehabisan percobaan
        // dan melempar OverflowException pada pembuatan data ketujuh.
        $title = Str::title(fake()->words(5, true)).' '.fake()->numberBetween(1000, 999999);

        return [
            'provider_profile_id' => ProviderProfile::factory()->verified(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->optional()->sentences(2, true),
            'registration_url' => 'https://pendaftaran.test/'.Str::slug($title),
            'mode' => EventMode::Online->value,
            'platform_url' => 'https://meet.test/'.Str::slug($title),
            'event_start_at' => now()->addMonth(),
            'event_end_at' => now()->addMonth()->addDay(),
            'registration_deadline' => now()->addWeek(),
            'status' => EventStatus::Draft->value,
            'risk_score' => 0,
        ];
    }

    public function forProvider(ProviderProfile $profile): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_profile_id' => $profile->id,
        ]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_id' => $category->id,
        ]);
    }

    /** Sudah disetujui admin dan tayang untuk publik. */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EventStatus::Approved->value,
            'submitted_at' => now()->subWeek(),
            'published_at' => now()->subDays(6),
        ]);
    }

    /** Menunggu keputusan admin: belum boleh tampil untuk publik. */
    public function pending(int $riskScore = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EventStatus::Pending->value,
            'submitted_at' => now()->subDay(),
            'risk_score' => $riskScore,
        ]);
    }

    public function rejected(string $reason = 'Deskripsi belum menjelaskan aturan peserta.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EventStatus::Rejected->value,
            'submitted_at' => now()->subDays(3),
            'rejection_reason' => $reason,
        ]);
    }

    public function changesRequested(string $reason = 'Tambahkan tautan pendaftaran yang bisa diklik.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EventStatus::ChangesRequested->value,
            'rejection_reason' => $reason,
            'submitted_at' => now()->subDays(2),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EventStatus::Archived->value,
            'published_at' => now()->subMonth(),
            'archived_at' => now()->subDay(),
        ]);
    }

    /**
     * Kegiatan yang sudah lewat tenggat dan belum diarsipkan.
     *
     * Statusnya masih approved supaya terlihat bahwa yang menentukan
     * tampilan bukan kolom status saja, tapi juga tanggalnya — inilah
     * kasus batas yang paling sering terlewat (Panduan TC-EDGE).
     */
    public function deadlinePassed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_start_at' => now()->subWeek(),
            'event_end_at' => now()->subDays(5),
            'registration_deadline' => now()->subDays(10),
        ])->published();
    }
}