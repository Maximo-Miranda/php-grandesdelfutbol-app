<?php

namespace App\Enums;

enum NewsAdPlacementType: string
{
    case FeedCard = 'feed_card';
    case ArticleDetailBanner = 'article_detail_banner';

    public function label(): string
    {
        return match ($this) {
            self::FeedCard => 'Card en Feed',
            self::ArticleDetailBanner => 'Banner en Detalle',
        };
    }
}
