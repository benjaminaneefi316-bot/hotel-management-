<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The three system roles required by the assignment.
 *
 * `is_system` marks roles that must not be deleted, because application
 * authorization (config/abilities.php) is written against their names.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value],
                [
                    'display_name' => $role->label(),
                    'description' => $role->description(),
                    'is_system' => true,
                ],
            );
        }

        $this->command->info('Roles seeded: '.implode(', ', RoleName::names()));
    }
}
