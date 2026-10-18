<?php

namespace App\Modules\Payroll\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * How a component's amount is worked out each month.
 */
enum PayCalculation: string
{
    use EnumOptions;

    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_of_basic';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed amount'),
            self::PercentOfBasic => __('Percent of basic salary'),
        };
    }
}
