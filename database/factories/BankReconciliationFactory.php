<?php

namespace Database\Factories;

use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'statement_date' => now()->toDateString(),
            'statement_balance' => '0',
        ];
    }
}
