<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Seeds the demo dataset (roles, the super-admin and staff accounts, the room
 * catalogue and 60 fictional guests + bookings) — but ONLY when the database
 * is still empty.
 *
 * Why the guard: `GuestSeeder` and `BookingSeeder` are not idempotent (they
 * create new rows on every run). The container calls this command on boot when
 * RUN_SEEDERS=true, so a naive `db:seed` would pile up duplicate demo data on
 * every restart. Requiring an empty `users` table makes it safe to leave the
 * flag on: it seeds once and then does nothing.
 *
 * Pass --force to seed again on purpose.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--force : Seed even when the database already contains users}';

    protected $description = 'Seed roles, demo accounts and demo data (skipped when data already exists)';

    public function handle(): int
    {
        if (User::query()->exists() && ! $this->option('force')) {
            $this->info('Database already contains users; skipping demo seed.');

            return self::SUCCESS;
        }

        $this->info('Seeding demo data...');

        $this->call('db:seed', ['--force' => true]);

        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }
}
