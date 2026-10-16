<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
        };
    }

    /** Amounts that count towards collected revenue. */
    public function countsAsRevenue(): bool
    {
        return $this === self::Paid;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
