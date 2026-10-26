<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The container runs `php artisan demo:seed` on boot when RUN_SEEDERS=true.
 *
 * GuestSeeder / BookingSeeder are not idempotent, so the command must never run
 * them twice. It also has to repair a half-seeded database (the production
 * image once skipped guests because Faker was unavailable) without touching the
 * accounts. These tests pin all three behaviours.
 */
class DemoSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_demo_data_when_the_database_is_empty(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        // The super admin requested for this project exists after seeding.
        $this->assertDatabaseHas('users', [
            'email' => 'benjaminaneefi316@gmail.com',
            'username' => 'admin',
        ]);

        // Well over the 50 guests / 50 bookings the brief asks for.
        $this->assertGreaterThanOrEqual(50, Guest::query()->count());
        $this->assertGreaterThanOrEqual(50, Booking::query()->count());
    }

    public function test_it_does_not_seed_again_when_users_already_exist(): void
    {
        // First run seeds.
        $this->artisan('demo:seed')->assertSuccessful();

        $guests = Guest::query()->count();
        $users = User::query()->count();

        // Second run must be a no-op.
        $this->artisan('demo:seed')
            ->expectsOutputToContain('skipping demo seed')
            ->assertSuccessful();

        $this->assertSame($guests, Guest::query()->count());
        $this->assertSame($users, User::query()->count());
    }

    public function test_it_fills_missing_stays_without_resetting_accounts(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(SystemSettingSeeder::class);
        $this->seed(RoomTypeSeeder::class);
        $this->seed(RoomSeeder::class);

        $this->assertSame(0, Guest::query()->count());
        $this->assertSame(0, Booking::query()->count());

        $users = User::query()->count();
        $password = User::query()
            ->where('email', 'benjaminaneefi316@gmail.com')
            ->value('password');

        $this->artisan('demo:seed')
            ->expectsOutputToContain('No guests found')
            ->assertSuccessful();

        $this->assertSame($users, User::query()->count());
        $this->assertSame(
            $password,
            User::query()->where('email', 'benjaminaneefi316@gmail.com')->value('password'),
        );
        $this->assertGreaterThanOrEqual(50, Guest::query()->count());
        $this->assertGreaterThanOrEqual(50, Booking::query()->count());
    }
}
