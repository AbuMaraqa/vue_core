<?php

namespace App\Modules\Assets\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * How an asset loses value: the same amount every month, or a fixed rate of
 * what is left (faster at first).
 */
enum DepreciationMethod: string
{
    use EnumOptions;

    case StraightLine = 'straight_line';
    case DecliningBalance = 'declining_balance';

    public function label(): string
    {
        return match ($this) {
            self::StraightLine => __('Straight line'),
            self::DecliningBalance => __('Declining balance'),
        };
    }
}
