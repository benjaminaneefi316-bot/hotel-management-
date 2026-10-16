<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three roles required by the assignment.
 */
enum RoleName: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Receptionist => 'Receptionist',
            self::Guest => 'Guest',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access: users, roles, rooms, bookings, reports, settings, backups, restore points and audit logs.',
            self::Receptionist => 'Operational access: guests, rooms, reservations, check-in and check-out. Cannot manage users, roles, backups or restore points.',
            self::Guest => 'Self-service access: own profile and own bookings only.',
        };
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
