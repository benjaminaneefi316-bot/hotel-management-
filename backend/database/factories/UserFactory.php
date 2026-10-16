<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Password used for factory-made accounts when the test does not supply
     * one. It satisfies the application password policy and is only ever used
     * for local development and automated tests.
     */
    public const DEFAULT_PASSWORD = 'Password!2345';

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
            'two_factor_enabled' => true,
            'last_login_at' => null,
        ];
    }

    /**
     * `password` and `password_salt` are NOT mass-assignable on purpose (so a
     * mass-assignment bug can never bypass the pepper + salt logic), which
     * means a factory must write them through setPassword() after making.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if (blank($user->password) || blank($user->password_salt)) {
                $user->setPassword(self::DEFAULT_PASSWORD);
            }
        });
    }

    /** Set a specific plaintext password for the account. */
    public function withPassword(string $plaintext): static
    {
        return $this->afterMaking(function (User $user) use ($plaintext): void {
            $user->setPassword($plaintext);
        });
    }

    public function admin(): static
    {
        return $this->withRole(RoleName::Admin);
    }

    public function receptionist(): static
    {
        return $this->withRole(RoleName::Receptionist);
    }

    public function guest(): static
    {
        return $this->withRole(RoleName::Guest);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Inactive]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Archived]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn (): array => ['two_factor_enabled' => false]);
    }

    /**
     * Attach a role after the user is created.
     */
    public function withRole(RoleName|string ...$names): static
    {
        return $this->afterCreating(function (User $user) use ($names): void {
            $ids = Role::query()
                ->whereIn('name', array_map(
                    static fn (RoleName|string $n): string => $n instanceof RoleName ? $n->value : $n,
                    $names,
                ))
                ->pluck('id');

            $pivot = [];
            foreach ($ids as $id) {
                $pivot[$id] = ['assigned_at' => now()];
            }

            $user->roles()->syncWithoutDetaching($pivot);
            $user->unsetRelation('roles');
        });
    }
}
