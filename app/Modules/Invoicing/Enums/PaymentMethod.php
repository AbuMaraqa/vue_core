<?php

namespace App\Modules\Invoicing\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * How money changed hands. Cash, transfers and cards go through a bank or
 * cash account; cheques go through the cheque register until the bank pays
 * them.
 */
enum PaymentMethod: string
{
    use EnumOptions;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank transfer'),
            self::Card => __('Card'),
            self::Cheque => __('Cheque'),
        };
    }
}
