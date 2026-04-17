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

    public function opposite(): self
    {
        return match ($this) {
            self::A => self::B,
            self::B => self::A,
        };
    }
}
