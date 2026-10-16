<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StayStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A real stay: created on check-in, closed on check-out.
 *
 * @property StayStatus $status
 */
class CheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'room_id',
        'guest_id',
        'status',
        'checked_in_at',
        'checked_in_by',
        'checked_out_at',
        'checked_out_by',
        'actual_nights',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StayStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'actual_nights' => 'integer',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function isActive(): bool
    {
        return $this->status === StayStatus::Active;
    }
}
