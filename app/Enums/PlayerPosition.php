<?php

namespace App\Enums;

enum PlayerPosition: string
{
    case Gk = 'GK';
    case Cb = 'CB';
    case Lb = 'LB';
    case Rb = 'RB';
    case Cdm = 'CDM';
    case Cm = 'CM';
    case Cam = 'CAM';
    case Lw = 'LW';
    case Rw = 'RW';
    case St = 'ST';
    case Cf = 'CF';

    public function label(): string
    {
        return match ($this) {
            self::Gk => 'Portero',
            self::Cb => 'Central',
            self::Lb => 'Lateral Izq.',
            self::Rb => 'Lateral Der.',
            self::Cdm => 'Mediocampista Def.',
            self::Cm => 'Mediocampista',
            self::Cam => 'Mediocampista Of.',
            self::Lw => 'Extremo Izq.',
            self::Rw => 'Extremo Der.',
            self::St => 'Delantero',
            self::Cf => 'Centro Delantero',
        };
    }
}
