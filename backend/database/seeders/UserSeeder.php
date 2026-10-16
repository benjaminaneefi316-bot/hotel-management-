<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo staff and guest accounts.
 *
 * The password is read from SEED_DEMO_PASSWORD (default below). It exists so
 * the system can be demonstrated immediately; it MUST be changed before any
 * real deployment, and it is never used by production code paths.
 *
 * Every password still goes through User::setPassword(), so it is stored with
 * the Argon2id + per-user-salt + application-pepper construction.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('SEED_DEMO_PASSWORD', 'Password!2345');

        $accounts = [
            [
                'name' => 'Benjamin Aneefi',
                'username' => 'admin',
                'email' => 'benjaminaneefi316@gmail.com',
                'phone' => '0539460356',
                'role' => RoleName::Admin,
                'two_factor' => true,
            ],
            [
                'name' => 'Liam Chen',
                'username' => 'reception',
                'email' => 'reception@azurebay.test',
                'role' => RoleName::Receptionist,
                'two_factor' => true,
            ],
            [
                'name' => 'Sofia Rossi',
                'username' => 'reception2',
                'email' => 'sofia@azurebay.test',
                'role' => RoleName::Receptionist,
                'two_factor' => true,
            ],
            [
                'name' => 'Noah Williams',
                'username' => 'guest',
                'email' => 'guest@azurebay.test',
                'role' => RoleName::Guest,
                'two_factor' => true,
            ],
            [
                'name' => 'Mei Tanaka',
                'username' => 'guest2',
                'email' => 'mei@azurebay.test',
                'role' => RoleName::Guest,
                'two_factor' => false,
            ],
        ];

        foreach ($accounts as $account) {
            DB::transaction(function () use ($account, $password): void {
                /** @var User $user */
                $user = User::query()->firstOrNew(['username' => $account['username']]);

                $user->fill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => $account['phone'] ?? null,
                    'status' => UserStatus::Active,
                    'two_factor_enabled' => $account['two_factor'],
                    'email_verified_at' => now(),
                ]);

                // Always (re)set a known demo password so the demo is repeatable.
                $user->setPassword($password);
                $user->save();

                $roleId = Role::query()->where('name', $account['role']->value)->value('id');

                if ($roleId !== null) {
                    $user->roles()->syncWithoutDetaching([
                        $roleId => ['assigned_at' => now()],
                    ]);
                }
            });
        }

        $this->command->info('Users seeded. Demo password: '.$password.' (CHANGE THIS IN PRODUCTION)');
    }
}
