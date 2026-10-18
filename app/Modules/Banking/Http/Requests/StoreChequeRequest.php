<?php

namespace App\Modules\Banking\Http\Requests;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Banking\Data\ChequeData;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Models\ChequeBook;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Receive a cheque from a contact or write one to a contact. An issued
 * cheque is drawn on one of the company's bank accounts; its number is
 * entered or taken from a cheque book, and is unique for the account.
 */
class StoreChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Cheque::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $outgoing = $this->input('direction') === ChequeDirection::Outgoing->value;

        return [
            'direction' => ['required', Rule::enum(ChequeDirection::class)],
            'number' => [Rule::requiredIf(! $outgoing || ! $this->filled('cheque_book_id')), 'nullable', 'string', 'max:30'],
            'contact_id' => ['required', 'integer', Rule::exists(Contact::class, 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'drawee_bank' => ['nullable', 'string', 'max:150'],
            'bank_account_id' => [Rule::requiredIf($outgoing), 'nullable', 'integer', Rule::exists(BankAccount::class, 'id')->where('is_active', true)],
            'cheque_book_id' => ['nullable', 'integer', Rule::exists(ChequeBook::class, 'id')->where('is_active', true)],
            'counter_account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('direction') !== ChequeDirection::Outgoing->value || ! $this->filled('number')) {
                    return;
                }

                $taken = Cheque::query()
                    ->where('direction', ChequeDirection::Outgoing)
                    ->where('bank_account_id', $this->integer('bank_account_id'))
                    ->where('number', $this->string('number')->value())
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('number', __('A cheque with this number was already issued from this account.'));
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
            'number' => __('Cheque number'),
            'contact_id' => __('Contact'),
            'currency_id' => __('Currency'),
            'issue_date' => __('Cheque date'),
            'due_date' => __('Due date'),
            'drawee_bank' => __('Bank'),
            'bank_account_id' => __('Bank account'),
            'cheque_book_id' => __('Cheque book'),
            'counter_account_id' => __('Account'),
        ];
    }

    public function toData(): ChequeData
    {
        return ChequeData::from($this->validated());
    }
}
