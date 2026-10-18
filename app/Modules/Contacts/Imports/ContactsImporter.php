<?php

namespace App\Modules\Contacts\Imports;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Contacts\Actions\Contacts\SaveContact;
use App\Modules\Contacts\Data\ContactData;
use App\Modules\Contacts\Enums\ContactKind;
use App\Modules\Contacts\Http\Requests\Contacts\SaveContactRequest;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Foundation\Imports\Importer;
use Illuminate\Validation\Rule;

/**
 * Create customers and vendors in bulk, e.g. when moving from another
 * system. `type` is `customer`, `vendor` or `both`; `kind` is `company`
 * (default) or `individual`; `currency` is a currency code such as `USD`.
 * Balances are entered afterwards as opening balances.
 */
class ContactsImporter extends Importer
{
    public function __construct(protected SaveContact $saveContact) {}

    public function columns(): array
    {
        return [
            'code', 'name', 'type', 'kind', 'contact_person', 'email', 'phone', 'mobile', 'tax_number',
            'currency', 'payment_terms_days', 'credit_limit', 'city', 'country', 'address',
        ];
    }

    public function rules(): array
    {
        $rules = SaveContactRequest::fieldRules();

        return [
            'code' => [...$rules['code'], 'distinct'],
            'name' => $rules['name'],
            'type' => ['required', Rule::in(['customer', 'vendor', 'both'])],
            'kind' => ['nullable', Rule::enum(ContactKind::class)],
            'contact_person' => $rules['contact_person'],
            'email' => $rules['email'],
            'phone' => $rules['phone'],
            'mobile' => $rules['mobile'],
            'tax_number' => $rules['tax_number'],
            'currency' => ['nullable', 'string', Rule::exists(Currency::class, 'code')->where('is_active', true)],
            'payment_terms_days' => $rules['payment_terms_days'],
            'credit_limit' => $rules['credit_limit'],
            'city' => $rules['city'],
            'country' => $rules['country'],
            'address' => $rules['address'],
        ];
    }

    protected function importRow(array $row): void
    {
        $type = (string) $row['type'];

        $this->saveContact->handle(new Contact, ContactData::from([
            ...array_filter($row, fn (mixed $value): bool => $value !== null),
            'kind' => $row['kind'] ?? ContactKind::Company->value,
            'is_customer' => $type !== 'vendor',
            'is_vendor' => $type !== 'customer',
            'currency_id' => filled($row['currency'] ?? null)
                ? Currency::query()->where('code', $row['currency'])->value('id')
                : null,
            'payment_terms_days' => (int) ($row['payment_terms_days'] ?? 0),
        ]));
    }
}
