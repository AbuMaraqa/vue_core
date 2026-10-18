<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['ar' => 'ضريبة 16%', 'en' => 'VAT 16%'],
            'rate' => '16',
            'scope' => TaxScope::Both,
            'sales_account_id' => Account::factory()->subtype(AccountSubtype::TaxPayable),
            'purchase_account_id' => Account::factory()->subtype(AccountSubtype::OtherCurrentAsset),
            'is_active' => true,
        ];
    }
}
