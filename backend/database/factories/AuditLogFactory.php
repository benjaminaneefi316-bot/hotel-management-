<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditOutcome;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /** @var string[] */
    private const EVENTS = ['auth', 'security', 'data', 'system', 'admin'];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_username' => fake()->userName(),
            'action' => fake()->randomElement([
                'auth.login.success',
                'auth.login.failed',
                'auth.2fa.verified',
                'booking.create',
                'booking.cancel',
                'stay.check_in',
                'stay.check_out',
                'setting.update',
                'backup.run',
                'restore_point.create',
                'restore_point.restore',
            ]),
            'event_type' => fake()->randomElement(self::EVENTS),
            'description' => fake()->sentence(),
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'FactoryAgent/1.0',
            'old_values' => null,
            'new_values' => null,
            'outcome' => AuditOutcome::Success,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['outcome' => AuditOutcome::Failure]);
    }

    public function denied(): static
    {
        return $this->state(fn (): array => ['outcome' => AuditOutcome::Denied]);
    }
}
