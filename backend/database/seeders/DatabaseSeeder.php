<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Order matters: roles before users, room types before rooms, and guests/users
 * before bookings (BookingSeeder drives the real BookingService, which needs
 * rooms, guests and staff to exist).
 *
 * NOTE: model events are deliberately NOT disabled here. The Booking model
 * generates its `reference` in a `creating` hook and RoomType derives its
 * `slug`; suppressing events would leave those columns empty.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            RoomTypeSeeder::class,
            RoomSeeder::class,
            GuestSeeder::class,
            SystemSettingSeeder::class,
            BookingSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
