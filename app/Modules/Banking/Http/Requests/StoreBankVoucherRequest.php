<?php

namespace App\Modules\Banking\Http\Requests;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Banking\Data\BankVoucherData;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A payment or receipt voucher: lines on postable accounts other than the
 * bank account itself.
 */
class StoreBankVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BankVoucher::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(VoucherDirection::class)],
            'date' => ['required', 'date'],
            'bank_account_id' => ['required', 'integer', Rule::exists(BankAccount::class, 'id')->where('is_active', true)],
            'contact_id' => ['nullable', 'integer', Rule::exists(Contact::class, 'id')],
            'payee' => ['nullable', 'string', 'max:150'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)->where('is_active', true)],
            'lines.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.tax_id' => ['nullable', 'integer', Rule::exists(Tax::class, 'id')->where('is_active', true)],
            'lines.*.cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $bankAccountId = BankAccount::query()->whereKey($this->integer('bank_account_id'))->value('account_id');

                foreach ((array) $this->input('lines') as $index => $line) {
                    if (is_array($line) && $bankAccountId !== null && (int) ($line['account_id'] ?? 0) === (int) $bankAccountId) {
                        $validator->errors()->add("lines.{$index}.account_id", __('Choose an account other than the bank account itself.'));
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
            'bank_account_id' => __('Bank account'),
            'contact_id' => __('Contact'),
            'lines.*.account_id' => __('Account'),
            'lines.*.amount' => __('Amount'),
            'lines.*.tax_id' => __('Tax'),
            'lines.*.cost_center_id' => __('Cost center'),
        ];
    }

    public function toData(): BankVoucherData
    {
        return BankVoucherData::from($this->validated());
    }
}
