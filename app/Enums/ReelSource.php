<?php

namespace App\Enums;

enum ReelSource: string
{
    case Auto = 'auto';
    case Manual = 'manual';
    case Request = 'request';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automático',
            self::Manual => 'Manual',
            self::Request => 'Solicitud',
        };
    }
}
