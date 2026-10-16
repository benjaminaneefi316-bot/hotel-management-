<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    protected $model = CheckIn::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'room_id' => Room::factory(),
            'guest_id' => null,
            'status' => StayStatus::Active,
            'checked_in_at' => now(),
            'checked_in_by' => null,
            'checked_out_at' => null,
            'checked_out_by' => null,
            'actual_nights' => null,
            'notes' => null,
        ];
    }

    public function completed(int $nights = 2): static
    {
        return $this->state(fn (): array => [
            'status' => StayStatus::Completed,
            'checked_in_at' => now()->subDays($nights),
            'checked_out_at' => now(),
            'actual_nights' => $nights,
        ]);
    }
}
