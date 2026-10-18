<?php

namespace Database\Factories;

use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\ChequeBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChequeBook>
 */
class ChequeBookFactory extends Factory
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
            'first_number' => 1001,
            'last_number' => 1050,
            'next_number' => 1001,
            'is_active' => true,
        ];
    }
}
