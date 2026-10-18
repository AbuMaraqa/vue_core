<?php

namespace App\Modules\Invoicing\Http\Requests;

use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoicing\Data\QuotationData;
use App\Modules\Invoicing\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a quotation for a customer. Every line needs an item or
 * a description, so the customer knows what is offered.
 */
class SaveQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation
            ? $this->user()->can('update', $quotation)
            : $this->user()->can('Sales Quotation Create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', Rule::exists(Contact::class, 'id')->where('is_customer', true)],
            'date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'prices_include_tax' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:300'],
            'lines.*.item_id' => ['nullable', 'integer', Rule::exists(Item::class, 'id')->where('is_active', true)],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,4'],
            'lines.*.tax_id' => [
                'nullable',
                'integer',
                Rule::exists(Tax::class, 'id')->where('is_active', true)->whereIn('scope', [TaxScope::Sales->value, TaxScope::Both->value]),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('lines') as $index => $line) {
                    if (is_array($line) && blank($line['item_id'] ?? null) && blank($line['description'] ?? null)) {
                        $validator->errors()->add("lines.{$index}.description", __('Choose an item or write a description for every line.'));
                    }
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
            'contact_id' => __('Customer'),
            'valid_until' => __('Valid until'),
            'currency_id' => __('Currency'),
            'lines.*.item_id' => __('Item'),
            'lines.*.description' => __('Description'),
            'lines.*.quantity' => __('Quantity'),
            'lines.*.unit_price' => __('Price'),
            'lines.*.discount_percent' => __('Discount'),
            'lines.*.tax_id' => __('Tax'),
        ];
    }

    public function toData(): QuotationData
    {
        return QuotationData::from([
            ...$this->validated(),
            'prices_include_tax' => $this->boolean('prices_include_tax'),
        ]);
    }
}
