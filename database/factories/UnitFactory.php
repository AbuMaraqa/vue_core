<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['ar' => 'قطعة', 'en' => 'Piece'],
            'symbol' => 'pc',
            'is_active' => true,
        ];
    }
}
