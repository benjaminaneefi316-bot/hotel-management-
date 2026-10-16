<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(RoleName::names());

        return [
            'name' => $name,
            'display_name' => ucfirst($name),
            'description' => 'Factory-generated role.',
            'is_system' => true,
        ];
    }

    public function named(RoleName $role): static
    {
        return $this->state(fn (): array => [
            'name' => $role->value,
            'display_name' => $role->label(),
            'description' => $role->description(),
            'is_system' => true,
        ]);
    }
}
