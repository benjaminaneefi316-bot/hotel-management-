<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property RoomStatus $status
 * @property string $room_number
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'room_type_id',
        'floor',
        'status',
        'nightly_rate',
        'image_url',
        'view',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'floor' => 'integer',
            'nightly_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<RoomType, $this> */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /** @return BelongsToMany<Booking, $this> */
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_rooms')
            ->withPivot('rate')
            ->withTimestamps();
    }

    /** @return HasMany<CheckIn, $this> */
    public function stays(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /** @return HasOne<CheckIn, $this> */
    public function activeStay(): HasOne
    {
        return $this->hasOne(CheckIn::class)->where('status', \App\Enums\StayStatus::Active->value);
    }

    /** Effective nightly rate (override or the room type's base price). */
    public function effectiveRate(): float
    {
        return (float) ($this->nightly_rate ?? $this->roomType->base_price);
    }
}
