<?php

namespace App\Enums;

enum NewsSourceType: string
{
    case Rss = 'rss';
    case ScorebatApi = 'scorebat_api';
    case YouTube = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::Rss => 'RSS',
            self::ScorebatApi => 'Scorebat API',
            self::YouTube => 'YouTube',
        };
    }
}
