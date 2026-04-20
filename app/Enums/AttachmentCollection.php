<?php

namespace App\Enums;

enum AttachmentCollection: string
{
    case Logo = 'logo';
    case Photo = 'photo';
    case TeamLogo = 'team_logo';
    case TeamCover = 'team_cover';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'Logo',
            self::Photo => 'Photo',
            self::TeamLogo => 'Team Logo',
            self::TeamCover => 'Team Cover',
        };
    }
}
