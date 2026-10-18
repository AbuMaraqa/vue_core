<?php

namespace App\Modules\Banking\Http\Requests;

use App\Modules\Banking\Data\BankTransferData;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BankTransfer::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $active = Rule::exists(BankAccount::class, 'id')->where('is_active', true);

        return [
            'date' => ['required', 'date'],
            'from_bank_account_id' => ['required', 'integer', $active],
            'to_bank_account_id' => ['required', 'integer', 'different:from_bank_account_id', $active],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'to_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'fees' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_bank_account_id' => __('From account'),
            'to_bank_account_id' => __('To account'),
            'to_amount' => __('Amount received'),
            'exchange_rate' => __('Exchange rate'),
        ];
    }

    public function toData(): BankTransferData
    {
        return BankTransferData::from($this->validated());
    }
}
