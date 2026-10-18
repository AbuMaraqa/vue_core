<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\FiscalYearStatus;
use App\Modules\Accounting\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    /**
     * Define the model's default state: the current calendar year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => (string) now()->year,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => FiscalYearStatus::Open,
        ];
    }

    public function year(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => FiscalYearStatus::Closed]);
    }
}
