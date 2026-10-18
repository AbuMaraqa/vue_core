<?php

namespace App\Modules\Accounting\Http\Requests\JournalEntries;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Decimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A manual journal entry: each line has a debit or a credit, and the entry
 * must balance before it can even be saved as a draft.
 */
class SaveJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->entry();

        return $entry ? $this->user()->can('update', $entry) : $this->user()->can('create', JournalEntry::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $amount = ['nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,4'];

        return [
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'lines' => ['required', 'array', 'min:2', 'max:500'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)->where('is_active', true)],
            'lines.*.cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => $amount,
            'lines.*.credit' => $amount,
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $debits = Decimal::sum([]);
                $credits = Decimal::sum([]);

                foreach ((array) $this->input('lines') as $index => $line) {
                    $debit = Decimal::of($line['debit'] ?? null);
                    $credit = Decimal::of($line['credit'] ?? null);

                    if ($debit->isZero() === $credit->isZero()) {
                        $validator->errors()->add("lines.{$index}.debit", __('Enter either a debit or a credit.'));
                    }

                    $debits = $debits->plus($debit);
                    $credits = $credits->plus($credit);
                }

                if (! $debits->isEqualTo($credits)) {
                    $validator->errors()->add('lines', __('The entry is not balanced: debits :debits, credits :credits.', [
                        'debits' => (string) $debits,
                        'credits' => (string) $credits,
                    ]));
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
            'date' => __('Date'),
            'currency_id' => __('Currency'),
            'exchange_rate' => __('Exchange rate'),
            'lines' => __('Lines'),
            'lines.*.account_id' => __('Account'),
        ];
    }

    public function entry(): ?JournalEntry
    {
        $entry = $this->route('journalEntry');

        return $entry instanceof JournalEntry ? $entry : null;
    }
}
