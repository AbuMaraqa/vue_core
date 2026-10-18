<?php

namespace App\Modules\Banking\Data;

use App\Modules\Banking\Enums\BankAccountType;
use Spatie\LaravelData\Data;

/**
 * A bank or cash account as entered. Without `account_id`, its account in
 * the chart of accounts is created under the cash or banks group.
 */
class BankAccountData extends Data
{
    public function __construct(
        public string $name,
        public BankAccountType $type,
        public int $currency_id,
        public ?int $account_id = null,
        public ?string $bank_name = null,
        public ?string $branch = null,
        public ?string $account_number = null,
        public ?string $iban = null,
        public bool $is_active = true,
    ) {}
}
