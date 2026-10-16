<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The container runs `php artisan demo:seed` on boot when RUN_SEEDERS=true.
 *
 * GuestSeeder / BookingSeeder are not idempotent, so the command must seed only
 * a database that is still empty — otherwise every restart would add another 60
 * guests. These tests pin both behaviours.
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
}
