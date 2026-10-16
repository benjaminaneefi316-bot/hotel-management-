<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * 30 physical rooms spread over four floors.
 *
 * Images are reused from the room-type photography (root-relative, offline
 * safe). `nightly_rate` is intentionally left NULL for most rooms so the room
 * type's base price applies; a few rooms carry an explicit override to
 * demonstrate the pricing fallback.
 */
class RoomSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, RoomType> $types */
        $types = RoomType::query()->get()->keyBy('code');

        // [floor, room numbers] per room type code.
        $layout = [
            'GDN' => [1, [101, 102, 103, 104]],
            'CLS' => [2, [201, 202, 203, 204, 205, 206]],
            'DLX' => [3, [301, 302, 303, 304, 305, 306]],
            'FAM' => [3, [310, 311, 312, 313]],
            'EXE' => [4, [401, 402, 403, 404]],
            'PRS' => [5, [501, 502]],
        ];

        $rooms = [];

        foreach ($layout as $code => [$floor, $numbers]) {
            $type = $types->get($code);

            if (! $type) {
                continue;
            }

            foreach ($numbers as $number) {
                $rooms[] = [
                    'room_number' => (string) $number,
                    'room_type_id' => $type->getKey(),
                    'floor' => $floor,
                    'status' => RoomStatus::Available,
                    'nightly_rate' => null,
                    'image_url' => $type->image_url,
                    'view' => match ($floor) {
                        1 => 'Garden view',
                        5 => 'Panoramic view',
                        default => $number % 2 === 0 ? 'Sea view' : 'City view',
                    },
                    'notes' => null,
                    'is_active' => true,
                ];
            }
        }

        foreach ($rooms as $room) {
            Room::query()->updateOrCreate(['room_number' => $room['room_number']], $room);
        }

        // A couple of real-world operational states.
        Room::query()->where('room_number', '502')->update([
            'status' => RoomStatus::Maintenance,
            'notes' => 'Spa bathroom refit scheduled.',
        ]);
        Room::query()->where('room_number', '104')->update([
            'status' => RoomStatus::OutOfService,
            'notes' => 'Awaiting replacement air-conditioning unit.',
        ]);

        $this->command->info('Rooms seeded: '.count($rooms));
    }
}
