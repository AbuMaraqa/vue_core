<?php

namespace App\Modules\Invoicing\Http\Requests;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Invoicing\Data\InvoiceData;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a draft invoice of the route's type. The contact must be
 * a customer (sales) or vendor (purchases); stock items need a warehouse;
 * a note may point to the invoice it reverses. An invoice or bill being
 * posted may also be paid in full at once (`paid_now`).
 */
class SaveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->invoice();

        return $invoice
            ? $this->user()->can('update', $invoice)
            : $this->user()->can("{$this->type()->permissionPrefix()} Create");
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->type();
        $opening = $this->boolean('is_opening');

        return [
            'contact_id' => ['required', 'integer', Rule::exists(Contact::class, 'id')->where($type->isSales() ? 'is_customer' : 'is_vendor', true)],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'warehouse_id' => ['nullable', 'integer', Rule::exists(Warehouse::class, 'id')->where('is_active', true)],
            'cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'original_invoice_id' => [
                Rule::excludeIf(! $type->isReturn()),
                'nullable',
                'integer',
                Rule::exists(Invoice::class, 'id')->where('type', $type->counterpart()->value)->where('contact_id', $this->integer('contact_id')),
            ],
            'prices_include_tax' => ['boolean'],
            'is_opening' => [Rule::excludeIf($type->isReturn()), 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_now' => ['boolean'],
            'payment_account_id' => [
                Rule::excludeIf(! $this->paysNow()),
                'required',
                'integer',
                Rule::exists(BankAccount::class, 'id')->where('is_active', true),
            ],
            'lines' => ['required', 'array', 'min:1', 'max:300'],
            'lines.*.item_id' => [Rule::prohibitedIf($opening), 'nullable', 'integer', Rule::exists(Item::class, 'id')->where('is_active', true)],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)->where('is_active', true)],
            'lines.*.cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,4'],
            'lines.*.tax_id' => [
                Rule::prohibitedIf($opening),
                'nullable',
                'integer',
                Rule::exists(Tax::class, 'id')->where('is_active', true)->whereIn('scope', [$type->taxScope()->value, 'both']),
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
                $lines = (array) $this->input('lines');
                $itemIds = array_filter(array_map(fn (mixed $line): mixed => is_array($line) ? ($line['item_id'] ?? null) : null, $lines));
                $stockItems = Item::query()->whereKey($itemIds)->where('type', ItemType::Stock)->exists();

                if ($stockItems && ! $this->filled('warehouse_id')) {
                    $validator->errors()->add('warehouse_id', __('Choose the warehouse of the stock items.'));
                }

                foreach ($lines as $index => $line) {
                    if (is_array($line) && ! $this->boolean('is_opening') && blank($line['item_id'] ?? null) && blank($line['account_id'] ?? null)) {
                        $validator->errors()->add("lines.{$index}.account_id", __('Choose an item or an account for every line.'));
                    }
                }

                $paymentAccount = $this->paysNow() ? BankAccount::query()->find($this->integer('payment_account_id')) : null;

                if ($paymentAccount !== null && $paymentAccount->currency_id !== $this->integer('currency_id')) {
                    $validator->errors()->add('payment_account_id', __('Choose an account in the currency of the document.'));
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
            'contact_id' => $this->type()->isSales() ? __('Customer') : __('Vendor'),
            'due_date' => __('Due date'),
            'currency_id' => __('Currency'),
            'exchange_rate' => __('Exchange rate'),
            'warehouse_id' => __('Warehouse'),
            'original_invoice_id' => __('Original document'),
            'lines.*.item_id' => __('Item'),
            'lines.*.account_id' => __('Account'),
            'lines.*.quantity' => __('Quantity'),
            'lines.*.unit_price' => __('Price'),
            'lines.*.discount_percent' => __('Discount'),
            'lines.*.tax_id' => __('Tax'),
            'payment_account_id' => $this->type()->isSales() ? __('Received into') : __('Paid from'),
        ];
    }

    /**
     * Whether the document is paid in full as it is posted, from the chosen
     * cash or bank account. Only invoices and bills can be, not notes or
     * opening balances, and a draft ignores it.
     */
    public function paysNow(): bool
    {
        return $this->boolean('paid_now')
            && $this->boolean('post')
            && $this->type()->paymentType() !== null
            && ! $this->boolean('is_opening');
    }

    public function toData(): InvoiceData
    {
        return InvoiceData::from([
            ...$this->validated(),
            'type' => $this->type()->value,
            'prices_include_tax' => $this->boolean('prices_include_tax'),
            'is_opening' => $this->boolean('is_opening') && ! $this->type()->isReturn(),
        ]);
    }

    public function type(): InvoiceType
    {
        return InvoiceType::fromRoute($this);
    }

    public function invoice(): ?Invoice
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice ? $invoice : null;
    }
}
