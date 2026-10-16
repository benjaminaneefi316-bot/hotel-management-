<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hotel guest.
 *
 * `phone`, `national_id` and `address` are transparently encrypted with
 * AES-256-GCM (App\Casts\EncryptedString). The database only ever contains
 * ciphertext for these columns.
 *
 * @property string|null $phone
 * @property string|null $national_id
 * @property string|null $address
 */
class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'date_of_birth',
        'phone',
        'national_id',
        'address',
        'city',
        'country',
        'postal_code',
        'id_type',
        'nationality',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest (AES-256-GCM).
            'phone' => EncryptedString::class,
            'national_id' => EncryptedString::class,
            'address' => EncryptedString::class,

            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Masked phone for list views (never exposes the full number). */
    public function maskedPhone(): string
    {
        $phone = (string) ($this->phone ?? '');

        if (strlen($phone) < 4) {
            return $phone === '' ? '—' : str_repeat('•', strlen($phone));
        }

        return str_repeat('•', max(0, strlen($phone) - 4)).substr($phone, -4);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
