<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $warehouse = Warehouse::factory();

        return [
            'number' => 'PRD-'.fake()->unique()->numerify('######'),
            'date' => now()->toDateString(),
            'item_id' => Item::factory(),
            'quantity' => '1',
            'materials_warehouse_id' => $warehouse,
            'product_warehouse_id' => $warehouse,
            'status' => ProductionOrderStatus::Draft,
        ];
    }
}
