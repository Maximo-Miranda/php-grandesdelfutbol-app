<?php

namespace App\Enums;

enum AttendanceTeam: string
{
    case A = 'a';
    case B = 'b';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Equipo A',
            self::B => 'Equipo B',
        };
    }
}
