<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment record.
 *
 * SECURITY: no card numbers, CVVs or other authentication data are ever stored
 * here - only a tokenised `provider_reference` and status metadata.
 *
 * @property PaymentStatus $status
 * @property PaymentMethod $method
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'amount',
        'currency',
        'method',
        'status',
        'provider',
        'provider_reference',
        'paid_at',
        'refunded_at',
        'failure_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }
}
