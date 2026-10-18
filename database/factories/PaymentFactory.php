<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PaymentType::Receipt,
            'number' => 'RCT-'.fake()->unique()->numerify('######'),
            'contact_id' => Contact::factory(),
            'date' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
            'amount' => '100',
            'currency_id' => Currency::factory(),
            'status' => DocumentStatus::Posted,
        ];
    }
}
