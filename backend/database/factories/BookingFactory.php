<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('-30 days', '+30 days');
        $nights = fake()->numberBetween(1, 6);

        return [
            'guest_id' => Guest::factory(),
            'user_id' => User::factory()->receptionist(),
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => (clone $checkIn)->modify("+{$nights} days")->format('Y-m-d'),
            'adults' => fake()->numberBetween(1, 2),
            'children' => fake()->numberBetween(0, 2),
            'status' => BookingStatus::Confirmed,
            'total_amount' => 0,
            'source' => fake()->randomElement(['website', 'phone', 'walk_in', 'ota']),
            'special_requests' => null,
            'internal_notes' => null,
            'confirmed_at' => now(),
            'cancelled_at' => null,
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
        ]);
    }

    /** Dates relative to today, e.g. offset(3, 5) = arrives in 3 days, 5 nights. */
    public function stay(int $startsInDays, int $nights): static
    {
        return $this->state(fn (): array => [
            'check_in_date' => now()->addDays($startsInDays)->toDateString(),
            'check_out_date' => now()->addDays($startsInDays + $nights)->toDateString(),
        ]);
    }
}
