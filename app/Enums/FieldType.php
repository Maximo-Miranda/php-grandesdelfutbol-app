<?php

namespace App\Enums;

enum FieldType: string
{
    case FiveVsFive = '5v5';
    case SixVsSix = '6v6';
    case SevenVsSeven = '7v7';
    case EightVsEight = '8v8';
    case NineVsNine = '9v9';
    case TenVsTen = '10v10';
    case ElevenVsEleven = '11v11';

    public function label(): string
    {
        return match ($this) {
            self::FiveVsFive => '5 vs 5',
            self::SixVsSix => '6 vs 6',
            self::SevenVsSeven => '7 vs 7',
            self::EightVsEight => '8 vs 8',
            self::NineVsNine => '9 vs 9',
            self::TenVsTen => '10 vs 10',
            self::ElevenVsEleven => '11 vs 11',
        };
    }
}
