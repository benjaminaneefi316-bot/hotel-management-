<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    /** @var array<int, array{name: string, bed: string, capacity: int, price: float}> */
    private const CATALOGUE = [
        ['name' => 'Classic Queen', 'bed' => 'Queen', 'capacity' => 2, 'price' => 145.00],
        ['name' => 'Deluxe King', 'bed' => 'King', 'capacity' => 2, 'price' => 210.00],
        ['name' => 'Executive Suite', 'bed' => 'King + Sofa bed', 'capacity' => 3, 'price' => 320.00],
        ['name' => 'Family Room', 'bed' => '2 Queen', 'capacity' => 4, 'price' => 260.00],
        ['name' => 'Garden Twin', 'bed' => '2 Twin', 'capacity' => 2, 'price' => 130.00],
        ['name' => 'Presidential Suite', 'bed' => 'King', 'capacity' => 4, 'price' => 640.00],
    ];

    public function definition(): array
    {
        $pick = fake()->randomElement(self::CATALOGUE);
        $name = $pick['name'].' '.fake()->unique()->numberBetween(1, 999);

        return [
            'code' => Str::upper(Str::slug($name, '')),
            'name' => $name,
            'description' => fake()->paragraph(),
            'bed_type' => $pick['bed'],
            'capacity' => $pick['capacity'],
            'base_price' => $pick['price'],
            'amenities' => ['Wi-Fi', 'Air conditioning', 'Smart TV', 'Safe', 'Mini bar'],
            'image_url' => null,
            'is_active' => true,
        ];
    }
}
