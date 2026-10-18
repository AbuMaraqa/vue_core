<?php

namespace App\Modules\Banking\Enums;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Money paid out of a bank or cash account (an expense voucher), or
 * received into it (a deposit), without an invoice.
 */
enum VoucherDirection: string
{
    use EnumOptions;

    case Out = 'out';
    case In = 'in';

    public function label(): string
    {
        return match ($this) {
            self::Out => __('Payment voucher'),
            self::In => __('Receipt voucher'),
        };
    }

    public function documentType(): DocumentType
    {
        return match ($this) {
            self::Out => DocumentType::Expense,
            self::In => DocumentType::Deposit,
        };
    }
}
