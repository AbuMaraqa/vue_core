<?php

namespace App\Modules\Invoicing\Data;

use Spatie\LaravelData\Data;

/**
 * A quotation as entered. The validity date defaults to the configured
 * number of days after its date.
 */
class QuotationData extends Data
{
    /**
     * @param  list<array{item_id?: int|null, description?: string|null, quantity: string, unit_price: string, discount_percent?: string|null, tax_id?: int|null}>  $lines
     */
    public function __construct(
        public int $contact_id,
        public string $date,
        public int $currency_id,
        public array $lines,
        public ?string $valid_until = null,
        public ?string $reference = null,
        public bool $prices_include_tax = false,
        public ?string $notes = null,
    ) {}
}
