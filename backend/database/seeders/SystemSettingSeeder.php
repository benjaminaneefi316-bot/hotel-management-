<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Operational configuration values.
 *
 * Every key below is on the whitelist in config/restore-points.php, which is
 * what makes it eligible to be captured by a configuration snapshot and rolled
 * back later. None of these are secrets: passwords, peppers, salts, tokens and
 * keys are matched by `restore-points.never_snapshot` and would be refused by
 * SettingsService even if someone whitelisted them by mistake.
 */
class SystemSettingSeeder extends Seeder
{
    /**
     * key => [group, type, value, description, is_public]
     *
     * @var array<string, array{0:string,1:string,2:mixed,3:string,4:bool}>
     */
    private const SETTINGS = [
        // --- Hotel identity -------------------------------------------------
        'hotel_name' => ['hotel', 'string', 'Azure Bay Hotel', 'Public name of the property.', true],
        'hotel_tagline' => ['hotel', 'string', 'A premium seaside retreat', 'Short tagline used on the public site.', true],
        'hotel_email' => ['hotel', 'string', 'benjaminaneefi316@gmail.com', 'General enquiries inbox (also the system e-mail address).', true],
        'hotel_phone' => ['hotel', 'string', '0539460356', 'Front-desk telephone number.', true],
        'hotel_address' => ['hotel', 'string', '12 Marine Parade, Azure Bay, CA 90210', 'Postal address shown on invoices.', true],
        'support_email' => ['hotel', 'string', 'support@azurebay.test', 'Support address for guest issues.', true],
        'hotel_currency' => ['hotel', 'string', 'USD', 'ISO 4217 currency code used for pricing.', true],
        'hotel_timezone' => ['hotel', 'string', 'UTC', 'Timezone for reporting and stay dates.', false],

        // --- Stay policy ----------------------------------------------------
        'check_in_time' => ['policy', 'string', '15:00', 'Earliest standard check-in time.', true],
        'check_out_time' => ['policy', 'string', '11:00', 'Standard check-out time.', true],
        'booking_lead_time_days' => ['policy', 'integer', 0, 'Minimum days between booking and arrival.', true],
        'max_advance_booking_days' => ['policy', 'integer', 365, 'How far ahead a booking may be made.', true],
        'cancellation_window_hours' => ['policy', 'integer', 48, 'Free-cancellation window before arrival.', true],

        // --- Financials -----------------------------------------------------
        'tax_rate' => ['finance', 'decimal', 0.10, 'Sales/accommodation tax rate (0.10 = 10%).', false],
        'service_charge_rate' => ['finance', 'decimal', 0.05, 'Service charge applied to the room subtotal.', false],
        'default_nightly_rate' => ['finance', 'decimal', 150.00, 'Fallback nightly rate when a room has no override.', false],

        // --- Loyalty & invoicing -------------------------------------------
        'loyalty_points_per_night' => ['loyalty', 'integer', 100, 'Loyalty points earned per night stayed.', true],
        'invoice_footer' => ['loyalty', 'string', 'Thank you for staying with us at Azure Bay.', 'Printed at the bottom of every invoice.', true],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $key => [$group, $type, $value, $description, $isPublic]) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'type' => $type,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'description' => $description,
                    'is_public' => $isPublic,
                ],
            );
        }

        $this->command->info('Settings seeded: '.count(self::SETTINGS));
    }
}
