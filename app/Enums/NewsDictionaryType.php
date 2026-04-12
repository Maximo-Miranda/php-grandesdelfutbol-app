<?php

namespace App\Enums;

enum NewsDictionaryType: string
{
    case Team = 'team';
    case Competition = 'competition';
    case Topic = 'topic';
    case BreakingKeyword = 'breaking_keyword';

    public function label(): string
    {
        return match ($this) {
            self::Team => 'Equipo',
            self::Competition => 'Competición',
            self::Topic => 'Tema',
            self::BreakingKeyword => 'Palabra Clave Urgente',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $type) => [$type->value => $type->label()]
        )->all();
    }
}
