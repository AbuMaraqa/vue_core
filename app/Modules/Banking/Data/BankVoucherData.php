<?php

namespace App\Modules\Banking\Data;

use App\Modules\Banking\Enums\VoucherDirection;
use Spatie\LaravelData\Data;

/**
 * A payment or receipt voucher as entered: amounts are before tax.
 */
class BankVoucherData extends Data
{
    /**
     * @param  list<array{account_id: int, amount: string, tax_id?: int|null, cost_center_id?: int|null, description?: string|null}>  $lines
     */
    public function __construct(
        public VoucherDirection $direction,
        public string $date,
        public int $bank_account_id,
        public array $lines,
        public ?int $contact_id = null,
        public ?string $payee = null,
        public ?string $exchange_rate = null,
        public ?string $reference = null,
        public ?string $description = null,
    ) {}
}
