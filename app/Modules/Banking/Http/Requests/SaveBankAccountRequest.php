<?php

namespace App\Modules\Banking\Http\Requests;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a bank or cash account. A new one may use an existing
 * cash or bank account of the chart that no other bank account uses. The
 * currency cannot change once money has moved through the account.
 */
class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bankAccount = $this->bankAccount();

        return $bankAccount ? $this->user()->can('update', $bankAccount) : $this->user()->can('create', BankAccount::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(BankAccountType::class)],
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'account_id' => [
                Rule::prohibitedIf($this->bankAccount() !== null),
                'nullable',
                'integer',
                Rule::exists(Account::class, 'id')->where('is_group', false)->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value]),
                Rule::unique(BankAccount::class, 'account_id'),
            ],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'branch' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
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
                $bankAccount = $this->bankAccount();

                if ($bankAccount && $bankAccount->currency_id !== $this->integer('currency_id') && $bankAccount->account->journalLines()->exists()) {
                    $validator->errors()->add('currency_id', __('The currency cannot change once money has moved through the account.'));
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
            'currency_id' => __('Currency'),
            'account_id' => __('Account'),
            'bank_name' => __('Bank'),
            'account_number' => __('Account number'),
            'iban' => __('IBAN'),
        ];
    }

    public function toData(): BankAccountData
    {
        return BankAccountData::from([...$this->validated(), 'is_active' => $this->boolean('is_active', true)]);
    }

    public function bankAccount(): ?BankAccount
    {
        $bankAccount = $this->route('bankAccount');

        return $bankAccount instanceof BankAccount ? $bankAccount : null;
    }
}
