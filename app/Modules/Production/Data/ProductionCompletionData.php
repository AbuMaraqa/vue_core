<?php

namespace App\Modules\Production\Data;

use Spatie\LaravelData\Data;

/**
 * What really happened when an order is finished: the day, the quantity
 * made, the quantity of each material used (`id` of an issued line, or a new
 * material without `id`) and the final other costs.
 */
class ProductionCompletionData extends Data
{
    /**
     * @param  list<array{id?: int|null, item_id: int, consumed_quantity: string}>  $materials
     * @param  list<array{description: string, account_id: int, amount: string}>  $costs
     */
    public function __construct(
        public string $date,
        public string $produced_quantity,
        public array $materials,
        public array $costs = [],
    ) {}
}
