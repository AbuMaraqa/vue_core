<?php

namespace Database\Factories;

use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
