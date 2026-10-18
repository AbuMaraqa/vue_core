<?php

namespace App\Modules\Contacts\Http\Requests\Contacts;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Contacts\Data\ContactData;
use App\Modules\Contacts\Enums\ContactKind;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a contact. It must be a customer, a vendor or both, and
 * its own accounts, when chosen, must be receivable and payable accounts.
 */
class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->contact();

        return $contact ? $this->user()->can('update', $contact) : $this->user()->can('create', Contact::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::fieldRules($this->contact());
    }

    /**
     * The rules of every contact field, shared with the spreadsheet import.
     *
     * @return array<string, array<mixed>>
     */
    public static function fieldRules(?Contact $contact = null): array
    {
        $account = fn (AccountSubtype $subtype) => Rule::exists(Account::class, 'id')->where('is_group', false)->where('subtype', $subtype->value);

        return [
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-_]+$/', Rule::unique(Contact::class, 'code')->ignore($contact)],
            'kind' => ['required', Rule::enum(ContactKind::class)],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'is_customer' => ['boolean'],
            'is_vendor' => ['boolean'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['nullable', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'receivable_account_id' => ['nullable', 'integer', $account(AccountSubtype::Receivable)],
            'payable_account_id' => ['nullable', 'integer', $account(AccountSubtype::Payable)],
            'payment_terms_days' => ['nullable', 'integer', 'between:0,365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('is_customer') && ! $this->boolean('is_vendor')) {
                    $validator->errors()->add('is_customer', __('Choose whether the contact is a customer, a vendor or both.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('Code'),
            'kind' => __('Kind'),
            'name' => __('Name'),
            'contact_person' => __('Contact person'),
            'tax_number' => __('Tax number'),
            'currency_id' => __('Currency'),
            'receivable_account_id' => __('Receivable account'),
            'payable_account_id' => __('Payable account'),
            'payment_terms_days' => __('Payment terms'),
            'credit_limit' => __('Credit limit'),
        ];
    }

    /**
     * The validated input, with the checkboxes as booleans.
     */
    public function toData(): ContactData
    {
        return ContactData::from([
            ...$this->validated(),
            'is_customer' => $this->boolean('is_customer'),
            'is_vendor' => $this->boolean('is_vendor'),
            'is_active' => $this->boolean('is_active', true),
            'payment_terms_days' => $this->integer('payment_terms_days'),
        ]);
    }

    public function contact(): ?Contact
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact ? $contact : null;
    }
}
