<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft entry without lines. Use JournalEntryBuilder in tests that need a
 * posted, balanced entry.
 *
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'description' => fake()->sentence(),
            'status' => JournalEntryStatus::Draft,
            'currency_id' => Currency::factory(),
            'exchange_rate' => '1',
        ];
    }
}
