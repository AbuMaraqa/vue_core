<?php

namespace App\Modules\Banking\Support;

use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\ChequeBook;

/**
 * Option lists for banking forms.
 */
class BankingOptions
{
    /**
     * Active bank and cash accounts with their currency, so forms can show
     * amounts in it and ask for a rate when it is foreign.
     *
     * @return list<array{value: int, label: string, hint: string, type: string, currency_id: int, currency: string, decimal_places: int}>
     */
    public function bankAccounts(): array
    {
        return array_values(BankAccount::query()
            ->with('currency')
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (BankAccount $bankAccount): array => [
                'value' => $bankAccount->id,
                'label' => $bankAccount->label(),
                'hint' => $bankAccount->type->label(),
                'type' => $bankAccount->type->value,
                'currency_id' => $bankAccount->currency_id,
                'currency' => $bankAccount->currency->code,
                'decimal_places' => $bankAccount->currency->decimal_places,
            ])
            ->all());
    }

    /**
     * Cheque books with numbers left.
     *
     * @return list<array{value: int, label: string, bank_account_id: int, next_number: int}>
     */
    public function chequeBooks(): array
    {
        return array_values(ChequeBook::query()
            ->where('is_active', true)
            ->whereColumn('next_number', '<=', 'last_number')
            ->orderBy('bank_account_id')
            ->get()
            ->map(fn (ChequeBook $book): array => [
                'value' => $book->id,
                'label' => "{$book->first_number} – {$book->last_number}",
                'bank_account_id' => $book->bank_account_id,
                'next_number' => $book->next_number,
            ])
            ->all());
    }
}
