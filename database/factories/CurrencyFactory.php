<?php

namespace Database\Factories;

use App\Modules\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => ['ar' => $code, 'en' => $code],
            'symbol' => $code,
            'decimal_places' => 2,
            'is_active' => true,
        ];
    }
}
