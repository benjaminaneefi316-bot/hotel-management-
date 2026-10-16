<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 *
 * NOTE: `phone`, `national_id` and `address` are encrypted by the model cast,
 * so the factory assigns them plaintext and the database stores ciphertext.
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    /** @var string[] */
    private const COUNTRIES = ['Australia', 'New Zealand', 'Singapore', 'United Kingdom', 'Germany', 'Japan', 'Canada'];

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();
        $country = fake()->randomElement(self::COUNTRIES);

        return [
            'user_id' => null,
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first.'.'.$last.fake()->numberBetween(1, 9999).'@example.com'),
            'date_of_birth' => fake()->dateTimeBetween('-75 years', '-18 years')->format('Y-m-d'),
            'phone' => '+'.fake()->numberBetween(1, 99).' '.fake()->numerify('### ### ###'),
            'national_id' => strtoupper(fake()->bothify('??#######')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => $country,
            'postal_code' => fake()->postcode(),
            'id_type' => fake()->randomElement(['passport', 'national_id', 'drivers_license']),
            'nationality' => $country,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function withAccount(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user?->getKey() ?? User::factory()->guest(),
        ]);
    }
}
