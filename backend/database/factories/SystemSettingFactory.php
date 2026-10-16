<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'key' => 'setting_'.fake()->unique()->numberBetween(1, 100000),
            'value' => fake()->word(),
            'group' => 'general',
            'type' => 'string',
            'description' => null,
            'is_public' => false,
        ];
    }

    public function key(string $key, string $value, string $type = 'string', string $group = 'general'): static
    {
        return $this->state(fn (): array => [
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'group' => $group,
        ]);
    }
}
