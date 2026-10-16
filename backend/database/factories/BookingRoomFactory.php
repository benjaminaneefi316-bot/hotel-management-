<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRoom>
 */
class BookingRoomFactory extends Factory
{
    protected $model = BookingRoom::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'room_id' => Room::factory(),
            'rate' => fake()->randomFloat(2, 120, 700),
        ];
    }
}
