<?php

namespace Database\Factories;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'FA-'.fake()->unique()->numerify('######'),
            'name' => fake()->words(2, true),
            'category_id' => AssetCategory::factory(),
            'acquisition_date' => now()->toDateString(),
            'depreciation_start_date' => now()->toDateString(),
            'cost' => '1200',
            'method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 12,
            'status' => AssetStatus::Active,
        ];
    }
}
