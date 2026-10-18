<?php

namespace Database\Factories;

use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ITM-'.fake()->unique()->numerify('#####'),
            'name' => ['ar' => 'صنف', 'en' => fake()->unique()->words(2, true)],
            'type' => ItemType::Stock,
            'sale_price' => '10',
            'purchase_price' => '6',
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ItemType::Service]);
    }
}
