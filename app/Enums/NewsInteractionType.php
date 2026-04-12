<?php

namespace App\Enums;

enum NewsInteractionType: string
{
    case View = 'view';
    case Click = 'click';
    case Like = 'like';
    case Bookmark = 'bookmark';
    case Share = 'share';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Vista',
            self::Click => 'Click',
            self::Like => 'Me gusta',
            self::Bookmark => 'Guardado',
            self::Share => 'Compartido',
        };
    }
}
