<?php

namespace App\Modules\Invoicing\Http\Requests;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\ChequeBook;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Data\PaymentData;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A customer receipt or vendor payment of the route's type, with the
 * invoices it settles. A received cheque needs no bank account until it is
 * deposited; an issued cheque is drawn on one.
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can("{$this->type()->permissionPrefix()} Create");
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->type();
        $isCheque = $this->input('method') === PaymentMethod::Cheque->value;

        return [
            'contact_id' => ['required', 'integer', Rule::exists(Contact::class, 'id')->where($type === PaymentType::Receipt ? 'is_customer' : 'is_vendor', true)],
            'date' => ['required', 'date'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'bank_account_id' => [
                Rule::requiredIf(! $isCheque || $type === PaymentType::Payment),
                'nullable',
                'integer',
                Rule::exists(BankAccount::class, 'id')->where('is_active', true),
            ],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'cheque_number' => [Rule::requiredIf($isCheque && ! $this->filled('cheque_book_id')), 'nullable', 'string', 'max:30'],
            'cheque_due_date' => [Rule::requiredIf($isCheque), 'nullable', 'date'],
            'drawee_bank' => ['nullable', 'string', 'max:150'],
            'cheque_book_id' => [Rule::prohibitedIf($type === PaymentType::Receipt), 'nullable', 'integer', Rule::exists(ChequeBook::class, 'id')->where('is_active', true)],
            'allocations' => ['array', 'max:200'],
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct', Rule::exists(Invoice::class, 'id')->where('type', $type->invoiceType()->value)->where('contact_id', $this->integer('contact_id'))],
            'allocations.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allocated = Decimal::sum(array_map(
                    fn (mixed $allocation): string => is_array($allocation) ? (string) ($allocation['amount'] ?? '0') : '0',
                    (array) $this->input('allocations'),
                ));

                if (is_numeric($this->input('amount')) && $allocated->isGreaterThan((string) $this->input('amount'))) {
                    $validator->errors()->add('allocations', __('The allocations are more than the amount.'));
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
            'contact_id' => $this->type() === PaymentType::Receipt ? __('Customer') : __('Vendor'),
            'bank_account_id' => __('Bank account'),
            'currency_id' => __('Currency'),
            'cheque_number' => __('Cheque number'),
            'cheque_due_date' => __('Due date'),
            'cheque_book_id' => __('Cheque book'),
            'allocations.*.amount' => __('Amount'),
        ];
    }

    public function toData(): PaymentData
    {
        return PaymentData::from([...$this->validated(), 'type' => $this->type()->value]);
    }

    public function type(): PaymentType
    {
        return PaymentType::fromRoute($this);
    }
}
