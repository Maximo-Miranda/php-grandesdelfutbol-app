<?php

namespace App\Enums;

enum SeasonStatus: string
{
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Completed => 'Completada',
        };
    }
}
