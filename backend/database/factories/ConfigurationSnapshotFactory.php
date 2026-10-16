<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConfigurationSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfigurationSnapshot>
 */
class ConfigurationSnapshotFactory extends Factory
{
    protected $model = ConfigurationSnapshot::class;

    public function definition(): array
    {
        return [
            'name' => 'Snapshot '.fake()->unique()->dateTimeThisYear()->format('Y-m-d H:i'),
            'description' => fake()->sentence(),
            'schema_version' => (string) config('restore-points.schema_version', '1.0'),
            'includes_role_assignments' => false,
            'item_count' => 0,
            'checksum' => hash('sha256', fake()->uuid()),
            'created_by' => User::factory()->admin(),
            'captured_at' => now(),
            'last_restored_at' => null,
            'restore_count' => 0,
        ];
    }

    /**
     * NOTE: a factory-made snapshot has a meaningless checksum. Anything that
     * needs to RESTORE a snapshot must create it through
     * ConfigurationSnapshotService::create() so the checksum is real.
     */
    public function withRoles(): static
    {
        return $this->state(fn (): array => ['includes_role_assignments' => true]);
    }
}
