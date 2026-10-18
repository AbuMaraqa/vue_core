<?php

namespace App\Modules\Accounting\Data;

use Spatie\LaravelData\Data;

/**
 * A manual journal entry as entered by the user.
 */
class JournalEntryData extends Data
{
    /**
     * @param  list<array{account_id: int, cost_center_id?: int|null, description?: string|null, debit?: string|null, credit?: string|null}>  $lines
     */
    public function __construct(
        public string $date,
        public int $currency_id,
        public string $exchange_rate,
        public array $lines,
        public ?string $reference = null,
        public ?string $description = null,
    ) {}
}
