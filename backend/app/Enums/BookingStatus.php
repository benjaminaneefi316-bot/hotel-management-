<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    /** Statuses that occupy a room's calendar (blocks conflicting bookings). */
    public function occupiesCalendar(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::CheckedIn, self::CheckedOut], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
