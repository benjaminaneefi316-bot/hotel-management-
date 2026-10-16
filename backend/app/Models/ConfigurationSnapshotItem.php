<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One captured value inside a configuration snapshot.
 *
 * @property string $item_type  setting|role_assignment
 * @property string $item_key
 */
class ConfigurationSnapshotItem extends Model
{
    use HasFactory;

    public const TYPE_SETTING = 'setting';

    public const TYPE_ROLE_ASSIGNMENT = 'role_assignment';

    protected $fillable = [
        'configuration_snapshot_id',
        'item_type',
        'item_key',
        'captured_value',
    ];

    /** @return BelongsTo<ConfigurationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ConfigurationSnapshot::class, 'configuration_snapshot_id');
    }
}
