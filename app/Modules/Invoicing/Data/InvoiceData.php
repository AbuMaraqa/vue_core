<?php

namespace App\Modules\Invoicing\Data;

use App\Modules\Invoicing\Enums\InvoiceType;
use Spatie\LaravelData\Data;

/**
 * An invoice as entered. Lines without an account take it from their item;
 * the due date defaults to the contact's payment terms.
 */
class InvoiceData extends Data
{
    /**
     * @param  list<array{item_id?: int|null, description?: string|null, account_id?: int|null, cost_center_id?: int|null, quantity: string, unit_price: string, discount_percent?: string|null, tax_id?: int|null}>  $lines
     */
    public function __construct(
        public InvoiceType $type,
        public int $contact_id,
        public string $date,
        public int $currency_id,
        public array $lines,
        public ?string $due_date = null,
        public ?string $reference = null,
        public ?string $exchange_rate = null,
        public ?int $warehouse_id = null,
        public ?int $cost_center_id = null,
        public ?int $original_invoice_id = null,
        public bool $prices_include_tax = false,
        public bool $is_opening = false,
        public ?string $notes = null,
    ) {}
}
