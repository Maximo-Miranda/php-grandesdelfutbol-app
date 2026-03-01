<?php

namespace App\Enums;

enum ClubMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Player = 'player';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Player => 'Player',
        };
    }
}
