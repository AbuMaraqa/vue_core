<?php

namespace App\Modules\Invoicing\Data;

use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentType;
use Spatie\LaravelData\Data;

/**
 * A customer receipt or vendor payment as entered, with the invoices it
 * settles. A cheque payment carries the cheque's details.
 */
class PaymentData extends Data
{
    /**
     * @param  list<array{invoice_id: int, amount: string}>  $allocations
     */
    public function __construct(
        public PaymentType $type,
        public int $contact_id,
        public string $date,
        public PaymentMethod $method,
        public string $amount,
        public int $currency_id,
        public array $allocations = [],
        public ?int $bank_account_id = null,
        public ?string $exchange_rate = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $cheque_number = null,
        public ?string $cheque_due_date = null,
        public ?string $drawee_bank = null,
        public ?int $cheque_book_id = null,
    ) {}
}
