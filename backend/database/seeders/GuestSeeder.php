<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 60 fictional guests.
 *
 * The data is invented from fixed name/location lists rather than Faker, so
 * the seeder also runs in the production image (which installs no dev
 * dependencies). The phone / national id / address columns are encrypted at
 * rest by the model cast, so the database stores ciphertext for those three
 * columns.
 *
 * A few guests are linked to self-service user accounts so the guest portal
 * has meaningful data.
 */
class GuestSeeder extends Seeder
{
    public const GUEST_COUNT = 60;

    /** @var string[] */
    private const FIRST_NAMES = [
        'Amara', 'Noah', 'Sofia', 'Liam', 'Mei', 'Ethan', 'Priya', 'Lucas',
        'Isla', 'Omar', 'Chloe', 'Mateo', 'Aisha', 'Jonas', 'Hana', 'Diego',
        'Leila', 'Felix', 'Nora', 'Ravi',
    ];

    /** @var string[] */
    private const LAST_NAMES = [
        'Okafor', 'Bennett', 'Tanaka', 'Silva', 'Chen', 'Walker', 'Patel', 'Moreau',
        'Nguyen', 'Hassan', 'Fischer', 'Rossi', 'Kim', 'Novak', 'Andersen', 'Costa',
        'Ahmed', 'Weber', 'Lindqvist', 'Sharma',
    ];

    /** @var array<string, array{0:string,1:string}> country => [city, postal prefix] */
    private const PLACES = [
        'Australia' => ['Sydney', '2000'],
        'New Zealand' => ['Auckland', '1010'],
        'Singapore' => ['Singapore', '018956'],
        'United Kingdom' => ['London', 'EC1A'],
        'Germany' => ['Berlin', '10115'],
        'Japan' => ['Tokyo', '100-0001'],
        'Canada' => ['Vancouver', 'V6B'],
    ];

    /** @var string[] */
    private const STREETS = [
        'Harbour View Road', 'Queen Street', 'Marina Walk', 'King Street',
        'Garden Lane', 'Ocean Drive', 'Palm Avenue', 'Station Road',
    ];

    /** @var string[] */
    private const ID_TYPES = ['passport', 'national_id', 'drivers_license'];

    public function run(): void
    {
        $guestAccounts = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'guest'))
            ->orderBy('id')
            ->get();

        $countries = array_keys(self::PLACES);

        DB::transaction(function () use ($guestAccounts, $countries): void {
            $created = 0;

            for ($i = 0; $i < self::GUEST_COUNT; $i++) {
                $first = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
                $last = self::LAST_NAMES[($i * 7) % count(self::LAST_NAMES)];
                $country = $countries[$i % count($countries)];
                [$city, $postalPrefix] = self::PLACES[$country];

                /** @var Guest $guest */
                $guest = Guest::query()->create([
                    'user_id' => $i < $guestAccounts->count() ? $guestAccounts[$i]->getKey() : null,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => strtolower($first.'.'.$last.$i.'@example.com'),
                    'date_of_birth' => CarbonImmutable::today()
                        ->subYears(21 + ($i % 45))
                        ->subDays(($i * 11) % 350)
                        ->toDateString(),
                    'phone' => '+61 4'.str_pad((string) (10_000_000 + $i * 137), 8, '0', STR_PAD_LEFT),
                    'national_id' => 'P'.str_pad((string) (1_000_000 + $i * 97), 7, '0', STR_PAD_LEFT),
                    'address' => (12 + $i).' '.self::STREETS[$i % count(self::STREETS)],
                    'city' => $city,
                    'country' => $country,
                    'postal_code' => $postalPrefix === 'EC1A' || $postalPrefix === 'V6B'
                        ? $postalPrefix.' '.($i % 9 + 1).'AB'
                        : $postalPrefix,
                    'id_type' => self::ID_TYPES[$i % count(self::ID_TYPES)],
                    'nationality' => $country,
                    'notes' => null,
                    'is_active' => true,
                ]);

                $created++;
            }

            $this->command->info("Guests seeded: {$created}");
        });
    }
}
