<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConfigurationSnapshot;
use App\Models\ConfigurationSnapshotItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfigurationSnapshotItem>
 */
class ConfigurationSnapshotItemFactory extends Factory
{
    protected $model = ConfigurationSnapshotItem::class;

    public function definition(): array
    {
        return [
            'configuration_snapshot_id' => ConfigurationSnapshot::factory(),
            'item_type' => ConfigurationSnapshotItem::TYPE_SETTING,
            'item_key' => 'hotel_name',
            'captured_value' => 'Factory Hotel',
        ];
    }
}
