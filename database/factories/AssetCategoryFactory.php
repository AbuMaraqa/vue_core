<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCategory>
 */
class AssetCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['ar' => 'أجهزة', 'en' => 'Equipment'],
            'asset_account_id' => Account::factory()->subtype(AccountSubtype::FixedAsset),
            'accumulated_depreciation_account_id' => Account::factory()->subtype(AccountSubtype::AccumulatedDepreciation),
            'depreciation_expense_account_id' => Account::factory(),
            'method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 60,
        ];
    }
}
