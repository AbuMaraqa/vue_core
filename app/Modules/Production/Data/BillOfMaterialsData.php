<?php

namespace App\Modules\Production\Data;

use Spatie\LaravelData\Data;

/**
 * A recipe as entered: its product and batch size, the materials of one
 * batch and its other costs.
 */
class BillOfMaterialsData extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string, waste_percent?: string|null}>  $components
     * @param  list<array{description: string, account_id: int, amount: string}>  $costs
     */
    public function __construct(
        public string $code,
        public string $name,
        public int $item_id,
        public string $quantity,
        public array $components,
        public array $costs = [],
        public ?string $notes = null,
        public bool $is_active = true,
    ) {}
}
