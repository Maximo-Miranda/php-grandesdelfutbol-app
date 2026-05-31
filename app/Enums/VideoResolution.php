<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VideoResolution: string implements HasLabel
{
    case Original = 'original';
    case P720 = '720p';
    case P1080 = '1080p';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'Original',
            self::P720 => '720p',
            self::P1080 => '1080p',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
