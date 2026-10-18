<?php

namespace Database\Factories;

use App\Modules\Accounting\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => (string) fake()->unique()->numberBetween(100, 9999),
            'name' => ['ar' => $name, 'en' => $name],
            'is_active' => true,
        ];
    }
}
