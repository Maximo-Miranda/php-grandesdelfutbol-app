<?php

namespace App\Enums;

enum AttendanceRole: string
{
    case Pending = 'pending';
    case Starter = 'starter';
    case Substitute = 'substitute';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Starter => 'Titular',
            self::Substitute => 'Suplente',
        };
    }
}
