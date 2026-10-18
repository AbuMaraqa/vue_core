<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Models\Cheque;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cheque>
 */
class ChequeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direction' => ChequeDirection::Incoming,
            'number' => fake()->unique()->numerify('######'),
            'status' => ChequeStatus::InHand,
            'amount' => '100',
            'currency_id' => Currency::factory(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'counter_account_id' => Account::factory()->subtype(AccountSubtype::Receivable),
        ];
    }
}
