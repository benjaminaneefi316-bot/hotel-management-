<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property string $bed_type
 * @property int $capacity
 * @property string $base_price
 * @property array<int, string>|null $amenities
 */
class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'description',
        'bed_type',
        'capacity',
        'base_price',
        'amenities',
        'image_url',
        'is_active',
    ];

    protected static function booted(): void
    {
        // Keep the public URL slug in sync with the display name.
        static::saving(function (RoomType $type): void {
            if (blank($type->slug)) {
                $base = \Illuminate\Support\Str::slug((string) $type->name) ?: \Illuminate\Support\Str::lower((string) $type->code);
                $slug = $base;
                $suffix = 2;

                while (static::query()->where('slug', $slug)->whereKeyNot($type->getKey() ?? 0)->exists()) {
                    $slug = $base.'-'.$suffix++;
                }

                $type->slug = $slug;
            }
        });
    }

    /** Route model binding key for public URLs. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'capacity' => 'integer',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
