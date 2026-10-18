<?php

namespace App\Modules\Banking\Data;

use App\Modules\Banking\Enums\ChequeDirection;
use Spatie\LaravelData\Data;

/**
 * A cheque as entered. An issued cheque is drawn on `bank_account_id` and
 * may take its number from a cheque book. Without `counter_account_id`, the
 * contact's receivable (received) or payable (issued) account is used.
 */
class ChequeData extends Data
{
    public function __construct(
        public ChequeDirection $direction,
        public string $amount,
        public int $currency_id,
        public string $issue_date,
        public string $due_date,
        public ?string $number = null,
        public ?int $contact_id = null,
        public ?string $exchange_rate = null,
        public ?string $drawee_bank = null,
        public ?int $bank_account_id = null,
        public ?int $cheque_book_id = null,
        public ?int $counter_account_id = null,
        public ?string $description = null,
    ) {}
}
