<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 60 fictional guests.
 *
 * All data is invented (Faker) - no real personal information is used. The
 * phone / national id / address columns are encrypted at rest by the model
 * cast, so the database stores ciphertext for those three columns.
 *
 * A few guests are linked to self-service user accounts so the guest portal
 * has meaningful data.
 */
class GuestSeeder extends Seeder
{
    public const GUEST_COUNT = 60;

    public function run(): void
    {
        $guestAccounts = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'guest'))
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($guestAccounts): void {
            $created = 0;

            for ($i = 0; $i < self::GUEST_COUNT; $i++) {
                /** @var Guest $guest */
                $guest = Guest::factory()->create();

                // Link the first few guests to the seeded guest accounts so
                // the "my bookings" portal is not empty.
                if ($i < $guestAccounts->count()) {
                    $guest->forceFill(['user_id' => $guestAccounts[$i]->getKey()])->save();
                }

                $created++;
            }

            $this->command->info("Guests seeded: {$created}");
        });
    }
}
