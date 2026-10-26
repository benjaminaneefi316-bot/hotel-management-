<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Database\Seeders\AuditLogSeeder;
use Database\Seeders\BookingSeeder;
use Database\Seeders\GuestSeeder;
use Illuminate\Console\Command;

/**
 * Seeds the demo dataset (roles, the super-admin and staff accounts, the room
 * catalogue and 60 fictional guests + bookings).
 *
 * On a brand-new database this runs the whole `DatabaseSeeder`. On a database
 * that already has accounts it only fills in demo stays when they are missing:
 * the guest and booking seeders are not idempotent, so they must never run
 * twice, and the accounts (and their passwords) must never be reset by a
 * redeploy. `GuestSeeder` and `BookingSeeder` avoid Faker so they also work in
 * the production image, which installs no dev dependencies.
 *
 * Pass --force to seed the full dataset again on purpose.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--force : Seed even when the database already contains users}';

    protected $description = 'Seed roles, demo accounts and demo data (skipped when data already exists)';

    public function handle(): int
    {
        if (! User::query()->exists()) {
            $this->info('Seeding demo data...');

            $this->call('db:seed', ['--force' => true]);

            $this->info('Demo data seeded.');

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            $this->warn('Accounts already exist; --force is ignored so sign-in details are not reset.');

            return $this->topUp();
        }

        $this->info('Database already contains users; checking for missing demo stays...');

        return $this->topUp();
    }

    /**
     * Create the demo guests and/or bookings when a previous boot failed
     * before they were written. Accounts are left untouched.
     */
    private function topUp(): int
    {
        $filled = false;

        if (Guest::query()->doesntExist()) {
            $this->info('No guests found; seeding demo guests...');
            $this->call('db:seed', ['--class' => GuestSeeder::class, '--force' => true]);
            $filled = true;
        }

        if (Booking::query()->doesntExist()) {
            $this->info('No bookings found; seeding demo bookings...');
            $this->call('db:seed', ['--class' => BookingSeeder::class, '--force' => true]);
            $filled = true;
        }

        if (AuditLog::query()->doesntExist()) {
            $this->call('db:seed', ['--class' => AuditLogSeeder::class, '--force' => true]);
        }

        if (! $filled) {
            $this->info('Demo data is already complete; skipping demo seed.');
        }

        return self::SUCCESS;
    }
}
