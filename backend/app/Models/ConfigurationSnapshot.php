<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configuration-only restore point.
 *
 * Contains ONLY settings and (optionally) role assignments - never business
 * data and never secrets.
 */
class ConfigurationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'schema_version',
        'includes_role_assignments',
        'item_count',
        'checksum',
        'created_by',
        'captured_at',
        'last_restored_at',
        'restore_count',
    ];

    protected function casts(): array
    {
        return [
            'includes_role_assignments' => 'boolean',
            'item_count' => 'integer',
            'restore_count' => 'integer',
            'captured_at' => 'datetime',
            'last_restored_at' => 'datetime',
        ];
    }

    /** @return HasMany<ConfigurationSnapshotItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ConfigurationSnapshotItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
