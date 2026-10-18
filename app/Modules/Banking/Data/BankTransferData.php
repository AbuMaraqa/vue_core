<?php

namespace App\Modules\Banking\Data;

use Spatie\LaravelData\Data;

/**
 * A transfer as entered. `to_amount` is needed when the two accounts have
 * different currencies; `exchange_rate` when they share a foreign one.
 */
class BankTransferData extends Data
{
    public function __construct(
        public string $date,
        public int $from_bank_account_id,
        public int $to_bank_account_id,
        public string $amount,
        public ?string $to_amount = null,
        public ?string $exchange_rate = null,
        public ?string $fees = null,
        public ?string $reference = null,
        public ?string $description = null,
    ) {}
}
