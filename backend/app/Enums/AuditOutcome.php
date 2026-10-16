<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Failure => 'Failed',
            self::Denied => 'Denied',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
