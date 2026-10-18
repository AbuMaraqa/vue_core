<?php

namespace Database\Factories;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'QT-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'contact_id' => Contact::factory(),
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_id' => Currency::factory(),
            'status' => QuotationStatus::Draft,
        ];
    }
}
