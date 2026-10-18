<?php

namespace App\Modules\Banking\Http\Requests;

use App\Modules\Banking\Enums\ChequeAction;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A step in the life of a cheque. Whether the step is allowed from the
 * cheque's status is checked by the ChequeRegister.
 */
class ChequeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cheque = $this->route('cheque');

        return $cheque instanceof Cheque && $this->user()->can('manage', $cheque);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(ChequeAction::class)->except([ChequeAction::Receive, ChequeAction::Issue])],
            'date' => ['required', 'date'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists(BankAccount::class, 'id')->where('is_active', true)],
            'endorse_to_contact_id' => [Rule::requiredIf($this->input('action') === ChequeAction::Endorse->value), 'nullable', 'integer', Rule::exists(Contact::class, 'id')],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bank_account_id' => __('Bank account'),
            'endorse_to_contact_id' => __('Endorse to'),
        ];
    }
}
