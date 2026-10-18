<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransfer>
 */
class BankTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TRF-'.fake()->unique()->numerify('######'),
            'date' => now()->toDateString(),
            'from_bank_account_id' => BankAccount::factory(),
            'to_bank_account_id' => BankAccount::factory(),
            'amount' => '100',
            'to_amount' => '100',
            'status' => DocumentStatus::Posted,
        ];
    }
}
