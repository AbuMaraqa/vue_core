<?php

namespace App\Modules\Invoicing\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * How much of a posted invoice has been settled.
 */
enum PaymentStatus: string
{
    use EnumOptions;

    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::PartiallyPaid => __('Partially paid'),
            self::Paid => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
        };
    }
}
