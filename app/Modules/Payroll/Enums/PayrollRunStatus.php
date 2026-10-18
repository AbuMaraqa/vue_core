<?php

namespace App\Modules\Payroll\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Draft runs are reviewed and adjusted; posting accrues the salaries in the
 * books; paying settles them from a bank or cash account.
 */
enum PayrollRunStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Posted => __('Posted'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'light',
            self::Posted => 'info',
            self::Paid => 'success',
            self::Cancelled => 'error',
        };
    }
}
