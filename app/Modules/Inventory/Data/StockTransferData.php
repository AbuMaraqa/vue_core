<?php

namespace App\Modules\Inventory\Data;

use Spatie\LaravelData\Data;

/**
 * A stock transfer as entered.
 */
class StockTransferData extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string}>  $lines
     */
    public function __construct(
        public string $date,
        public int $from_warehouse_id,
        public int $to_warehouse_id,
        public array $lines,
        public ?string $description = null,
    ) {}
}
