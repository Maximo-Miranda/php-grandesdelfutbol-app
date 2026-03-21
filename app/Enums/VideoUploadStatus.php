<?php

namespace App\Enums;

enum VideoUploadStatus: string
{
    case Uploading = 'uploading';
    case Encoding = 'encoding';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploading => 'Subiendo',
            self::Encoding => 'Procesando',
            self::Ready => 'Listo',
            self::Failed => 'Error',
        };
    }
}
