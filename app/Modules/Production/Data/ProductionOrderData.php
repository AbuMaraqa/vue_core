<?php

namespace App\Modules\Production\Data;

use Spatie\LaravelData\Data;

/**
 * A production order as planned: the product and quantity to make, where
 * the materials come from and the product goes, the materials to issue and
 * the expected other costs.
 */
class ProductionOrderData extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string}>  $materials
     * @param  list<array{description: string, account_id: int, amount: string}>  $costs
     */
    public function __construct(
        public string $date,
        public int $item_id,
        public string $quantity,
        public int $materials_warehouse_id,
        public int $product_warehouse_id,
        public array $materials,
        public array $costs = [],
        public ?int $bill_of_materials_id = null,
        public ?int $contact_id = null,
        public ?int $cost_center_id = null,
        public ?string $reference = null,
        public ?string $description = null,
    ) {}
}
