<?php

namespace App\Enums;

enum VideoProcessingStage: string
{
    case Receiving = 'receiving';
    case Storing = 'storing';
    case Publishing = 'publishing';

    /**
     * User-facing label. Intentionally non-technical: the end user does not
     * know what "S3" or "Drive" are, so we describe the experience instead.
     */
    public function label(): string
    {
        return match ($this) {
            self::Receiving => 'Recibiendo el video',
            self::Storing => 'Preparando el video',
            self::Publishing => 'Publicando el video',
        };
    }
}
