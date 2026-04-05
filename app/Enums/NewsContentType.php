<?php

namespace App\Enums;

enum NewsContentType: string
{
    case Article = 'article';
    case VideoHighlight = 'video_highlight';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Artículo',
            self::VideoHighlight => 'Video Highlight',
        };
    }
}
