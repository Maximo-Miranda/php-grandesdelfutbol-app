<?php

namespace App\Enums;

enum VideoServiceRequestStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Contacted => 'Contactado',
            self::Completed => 'Completado',
            self::Rejected => 'Rechazado',
        };
    }
}
