<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Services\SettingsService;

/**
 * Test helper that creates the baseline rows the application assumes already
 * exist: the three system roles and a small, deterministic set of whitelisted
 * settings.
 *
 * It deliberately does NOT call the production seeders: that keeps each test
 * independent of seeder ordering, console output and demo business data.
 */
trait SeedsHotel
{
    protected function seedRoles(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value],
                [
                    'display_name' => $role->label(),
                    'description' => $role->description(),
                    'is_system' => true,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedSettings(array $overrides = []): void
    {
        $settings = array_merge([
            'hotel_name' => 'Azure Bay Hotel',
            'hotel_tagline' => 'A premium coastal escape',
            'hotel_currency' => 'USD',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'tax_rate' => '10',
            'service_charge_rate' => '5',
        ], $overrides);

        foreach ($settings as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                    'group' => 'test',
                    'type' => is_numeric($value) ? 'decimal' : 'string',
                    'description' => 'Seeded by the test suite.',
                    'is_public' => false,
                ],
            );
        }

        app(SettingsService::class)->flush();
    }
}
