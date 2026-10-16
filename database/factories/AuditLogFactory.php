<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 *
 * Tabel audit_log tidak punya updated_at dan tidak pernah diubah,
 * jadi factory ini hanya mengisi apa yang ada.
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory()->admin(),
            'actor_role' => 'admin',
            'action' => fake()->randomElement(['event.approved', 'event.rejected', 'provider.verified']),
            'target_type' => Event::class,
            'target_id' => Event::factory(),
            'metadata' => ['sumber' => 'factory'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /** Jejak audit dengan pelaku sistem, bukan manusia. */
    public function bySystem(): static
    {
        return $this->state(fn (array $attributes): array => [
            'actor_user_id' => null,
            'actor_role' => null,
            'action' => 'event.expired',
            'metadata' => ['jumlah' => 3],
        ]);
    }
}
