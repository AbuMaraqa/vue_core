<?php

namespace App\Modules\Contacts\Data;

use App\Modules\Contacts\Enums\ContactKind;
use Spatie\LaravelData\Data;

/**
 * The validated details of a customer or vendor. An empty code is generated.
 */
class ContactData extends Data
{
    public function __construct(
        public ContactKind $kind,
        public string $name,
        public bool $is_customer,
        public bool $is_vendor,
        public ?string $code = null,
        public ?string $contact_person = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $mobile = null,
        public ?string $tax_number = null,
        public ?int $currency_id = null,
        public ?int $receivable_account_id = null,
        public ?int $payable_account_id = null,
        public int $payment_terms_days = 0,
        public ?string $credit_limit = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $notes = null,
        public bool $is_active = true,
    ) {}
}
