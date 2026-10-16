<?php

declare(strict_types=1);

namespace App\Enums;

enum RoomStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Cleaning = 'cleaning';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Cleaning => 'Cleaning',
            self::Maintenance => 'Maintenance',
            self::OutOfService => 'Out of service',
        };
    }

    /** Is the room bookable in its current state? */
    public function isBookable(): bool
    {
        return $this === self::Available || $this === self::Cleaning;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
