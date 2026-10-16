<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Deactivated',
            self::Archived => 'Archived',
        };
    }

    /** Only an active account may authenticate. */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
