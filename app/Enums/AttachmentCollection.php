<?php

namespace App\Enums;

enum AttachmentCollection: string
{
    case Logo = 'logo';
    case Photo = 'photo';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'Logo',
            self::Photo => 'Photo',
        };
    }
}
