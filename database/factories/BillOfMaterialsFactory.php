<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\Item;
use App\Modules\Production\Models\BillOfMaterials;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillOfMaterials>
 */
class BillOfMaterialsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BOM-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(2, true),
            'item_id' => Item::factory(),
            'quantity' => '1',
            'is_active' => true,
        ];
    }
}
