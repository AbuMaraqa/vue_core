<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Assets\Models\DepreciationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepreciationRun>
 */
class DepreciationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'DPR-'.fake()->unique()->numerify('######'),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total' => '0',
            'status' => DocumentStatus::Posted,
        ];
    }
}
