<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Confirmed = 'confirmed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::Declined => 'Rechazado',
        };
    }
}
