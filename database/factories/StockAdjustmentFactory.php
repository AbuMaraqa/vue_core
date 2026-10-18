<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ADJ-'.fake()->unique()->numerify('######'),
            'date' => now()->toDateString(),
            'warehouse_id' => Warehouse::factory(),
            'account_id' => Account::factory(),
            'status' => DocumentStatus::Posted,
        ];
    }
}
