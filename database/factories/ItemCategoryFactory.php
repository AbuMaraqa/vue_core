<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCategory>
 */
class ItemCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['ar' => fake()->unique()->word(), 'en' => fake()->unique()->word()],
        ];
    }
}
