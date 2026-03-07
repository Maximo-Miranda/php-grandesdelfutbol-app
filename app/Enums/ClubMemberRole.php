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

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 2,
            self::Admin => 1,
            self::Player => 0,
        };
    }

    public function outranks(self $other): bool
    {
        return $this->rank() > $other->rank();
    }
}
