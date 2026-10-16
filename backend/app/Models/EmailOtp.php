<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use, hashed, expiring email OTP.
 *
 * The plaintext code is NEVER stored: only `code_hash` (Hash::make).
 */
class EmailOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'purpose',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
        'max_attempts',
        'last_sent_at',
        'ip_address',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired() && $this->hasAttemptsLeft();
    }
}
