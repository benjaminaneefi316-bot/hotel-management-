<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 *
 * Only a tokenised provider reference is ever stored - never card data.
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => null,
            'amount' => fake()->randomFloat(2, 120, 2400),
            'currency' => 'USD',
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'status' => PaymentStatus::Paid,
            'provider' => 'stripe',
            'provider_reference' => 'tok_'.Str::random(18),
            'paid_at' => now(),
            'refunded_at' => null,
            'failure_reason' => null,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => PaymentStatus::Pending, 'paid_at' => null]);
    }

    public function refunded(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ]);
    }
}
