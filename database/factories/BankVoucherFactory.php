<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankVoucher>
 */
class BankVoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'EXP-'.fake()->unique()->numerify('######'),
            'direction' => VoucherDirection::Out,
            'date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory(),
            'currency_id' => Currency::factory(),
            'total' => '100',
            'subtotal' => '100',
            'status' => DocumentStatus::Posted,
        ];
    }
}
