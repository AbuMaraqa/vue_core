<?php

namespace App\Modules\Banking\Enums;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * A cash box or an account at a bank.
 */
enum BankAccountType: string
{
    use EnumOptions;

    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash box'),
            self::Bank => __('Bank account'),
        };
    }

    /**
     * The subtype of its account in the chart of accounts.
     */
    public function accountSubtype(): AccountSubtype
    {
        return match ($this) {
            self::Cash => AccountSubtype::Cash,
            self::Bank => AccountSubtype::Bank,
        };
    }
}
