<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\StayStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property BookingStatus $status
 */
class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'guest_id',
        'user_id',
        'check_in_date',
        'check_out_date',
        'adults',
        'children',
        'status',
        'total_amount',
        'source',
        'special_requests',
        'internal_notes',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'status' => BookingStatus::class,
            'adults' => 'integer',
            'children' => 'integer',
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Assign a human-friendly, unique reference (HM-2026-00042) if the
        // caller did not supply one. The exists() retry keeps it unique even
        // if two bookings are created in the same second.
        static::creating(function (Booking $booking): void {
            if (blank($booking->reference)) {
                $booking->reference = self::nextReference();
            }
        });
    }

    public static function nextReference(): string
    {
        $year = (int) now()->format('Y');
        $sequence = (int) static::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $reference = sprintf('HM-%d-%05d', $year, $sequence);
            $sequence++;
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** The user who created the reservation. @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsToMany<Room, $this> */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'booking_rooms')
            ->withPivot(['rate'])
            ->withTimestamps();
    }

    /** @return HasMany<BookingRoom, $this> */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<CheckIn, $this> */
    public function stays(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /** @return HasMany<CheckIn, $this> */
    public function activeStays(): HasMany
    {
        return $this->hasMany(CheckIn::class)->where('status', StayStatus::Active->value);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    public function nights(): int
    {
        return max(1, (int) $this->check_in_date->diffInDays($this->check_out_date));
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->total_amount - $this->paidAmount(), 2);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }

    public function isCheckInnable(): bool
    {
        return $this->status === BookingStatus::Confirmed;
    }

    public function isCheckOuttable(): bool
    {
        return $this->status === BookingStatus::CheckedIn;
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /** @param Builder<Booking> $query */
    public function scopeStatus(Builder $query, BookingStatus|string $status): void
    {
        $query->where('status', $status instanceof BookingStatus ? $status->value : $status);
    }

    /** @param Builder<Booking> $query */
    public function scopeBetweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereDate('check_in_date', '>=', $from)
            ->whereDate('check_in_date', '<=', $to);
    }

    /** @param Builder<Booking> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('reference', 'like', "%{$term}%")
                ->orWhereHas('guest', function (Builder $g) use ($term): void {
                    $g->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }
}
