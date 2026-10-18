<?php

namespace Database\Factories;

use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Enums\PayCalculation;
use App\Modules\Payroll\Enums\PayComponentType;
use App\Modules\Payroll\Models\PayComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayComponent>
 */
class PayComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['ar' => 'بدل', 'en' => 'Allowance'],
            'type' => PayComponentType::Earning,
            'calculation' => PayCalculation::Fixed,
            'default_value' => '50',
            'account_id' => Account::factory(),
            'is_active' => true,
        ];
    }
}
