<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\ArchivedUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchivedUser>
 */
class ArchivedUserFactory extends Factory
{
    protected $model = ArchivedUser::class;

    public function definition(): array
    {
        return [
            'original_user_id' => 0,
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'factory-placeholder-hash',
            'password_salt' => 'factory-placeholder-salt',
            'status_at_archive' => UserStatus::Active->value,
            'two_factor_enabled' => true,
            'roles' => ['receptionist'],
            'attributes' => [],
            'archived_by' => User::factory()->admin(),
            'reason' => fake()->sentence(),
            'archived_at' => now(),
            'restored_at' => null,
            'restored_by' => null,
        ];
    }
}
