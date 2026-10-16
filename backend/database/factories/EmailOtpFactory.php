<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<EmailOtp>
 *
 * The factory always stores a HASH of the code, mirroring production.
 */
class EmailOtpFactory extends Factory
{
    protected $model = EmailOtp::class;

    /** The plaintext code produced by the most recent factory call. */
    public static string $lastCode = '000000';

    public function definition(): array
    {
        self::$lastCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'user_id' => User::factory(),
            'purpose' => (string) config('security.otp.purpose', 'login_2fa'),
            'code_hash' => Hash::make(self::$lastCode),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'max_attempts' => 5,
            'last_sent_at' => now(),
            'ip_address' => fake()->ipv4(),
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => ['consumed_at' => now()]);
    }
}
