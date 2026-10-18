<?php

namespace App\Modules\Inventory\Data;

use Spatie\LaravelData\Data;

/**
 * A stock adjustment as entered: signed quantities, and unit costs for the
 * increases (the average cost when empty).
 */
class StockAdjustmentData extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string, unit_cost?: string|null}>  $lines
     */
    public function __construct(
        public string $date,
        public int $warehouse_id,
        public array $lines,
        public ?int $account_id = null,
        public ?string $description = null,
    ) {}
}
