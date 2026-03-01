<?php

namespace App\Enums;

enum ClubMemberStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
        };
    }
}
