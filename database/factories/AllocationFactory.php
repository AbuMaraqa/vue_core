<?php

namespace Database\Factories;

use App\Modules\Invoicing\Models\Allocation;
use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allocation>
 */
class AllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => '10',
            'date' => now()->toDateString(),
        ];
    }
}
