<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $floor = fake()->numberBetween(1, 6);

        return [
            'room_number' => (string) ($floor * 100 + fake()->unique()->numberBetween(1, 40)),
            'room_type_id' => RoomType::factory(),
            'floor' => $floor,
            'status' => RoomStatus::Available,
            'nightly_rate' => null,
            'view' => fake()->randomElement(['Sea view', 'City view', 'Garden view', 'Pool view']),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function forType(RoomType $type): static
    {
        return $this->state(fn (): array => ['room_type_id' => $type->getKey()]);
    }

    public function status(RoomStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
