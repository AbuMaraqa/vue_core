<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => InvoiceType::SalesInvoice,
            'contact_id' => Contact::factory(),
            'date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'currency_id' => Currency::factory(),
            'status' => DocumentStatus::Draft,
        ];
    }
}
