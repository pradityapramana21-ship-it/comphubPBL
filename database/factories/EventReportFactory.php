<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReport>
 *
 * Default-nya laporan yang masih terbuka dan belum dibaca admin —
 * itulah keadaan yang paling sering diuji, karena antrean laporan
 * justru yang membuat halaman admin terasa hidup.
 */
class EventReportFactory extends Factory
{
    protected $model = EventReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->published(),
            'reporter_user_id' => User::factory(),
            'reason_code' => fake()->randomElement(ReportReason::values()),
            'description' => fake()->optional()->sentence(12),
            'status' => ReportStatus::Open->value,
        ];
    }

    public function forEvent(Event $event, User $reporter): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_id' => $event->id,
            'reporter_user_id' => $reporter->id,
        ]);
    }

    public function reviewing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Reviewing->value,
        ]);
    }

    public function resolved(User $admin, string $note = 'Tautan diperbaiki oleh penyelenggara.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Resolved->value,
            'resolution_note' => $note,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);
    }
}
