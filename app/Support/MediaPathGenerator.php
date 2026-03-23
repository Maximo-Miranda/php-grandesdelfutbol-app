<?php

namespace App\Support;

use App\Models\MatchReel;
use App\Models\PlayerProfile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $prefix = match ($media->model_type) {
            MatchReel::class => 'media/reels/',
            PlayerProfile::class => 'media/players/',
            default => 'media/other/',
        };

        return $prefix.$media->uuid.'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }
}
