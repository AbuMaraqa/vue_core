<?php

namespace App\Modules\Banking\Support;

use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * The money in bank and cash accounts, read from the posted lines of their
 * accounts. An account in the base currency is counted in base amounts; an
 * account in a foreign currency only takes entries in that currency, so it
 * is counted in the entries' own amounts.
 */
class BankBalances
{
    public function __construct(
        protected AccountBalances $balances,
        protected Currencies $currencies,
    ) {}

    /**
     * The balance of each bank account, in its own currency and in the base
     * currency, keyed by bank account id.
     *
     * @param  Collection<int, BankAccount>  $bankAccounts
     * @return array<int, array{balance: string, base_balance: string}>
     */
    public function of(Collection $bankAccounts, CarbonInterface|string|null $asOf = null): array
    {
        if ($bankAccounts->isEmpty()) {
            return [];
        }

        $rows = $this->balances->lines(null, $asOf)
            ->whereIn('journal_lines.account_id', $bankAccounts->pluck('account_id'))
            ->groupBy('journal_lines.account_id')
            ->select('journal_lines.account_id')
            ->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as amount')
            ->selectRaw('SUM(journal_lines.base_debit) - SUM(journal_lines.base_credit) as base_amount')
            ->get()
            ->keyBy('account_id');

        $balances = [];

        foreach ($bankAccounts as $bankAccount) {
            $row = $rows[$bankAccount->account_id] ?? null;
            $base = Decimal::round((string) ($row->base_amount ?? '0'));

            $balances[$bankAccount->id] = [
                'balance' => $this->isBase($bankAccount) ? $base : Decimal::round((string) ($row->amount ?? '0')),
                'base_balance' => $base,
            ];
        }

        return $balances;
    }

    /**
     * The balance of one bank account in its own currency.
     */
    public function balance(BankAccount $bankAccount, CarbonInterface|string|null $asOf = null): string
    {
        return $this->of(collect([$bankAccount]), $asOf)[$bankAccount->id]['balance'];
    }

    /**
     * The posted lines of the bank account between the dates, with the amount
     * of each in the account's currency as `amount` (positive money in).
     */
    public function lines(BankAccount $bankAccount, CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): Builder
    {
        [$debit, $credit] = $this->isBase($bankAccount)
            ? ['journal_lines.base_debit', 'journal_lines.base_credit']
            : ['journal_lines.debit', 'journal_lines.credit'];

        return $this->balances->lines($from, $to)
            ->where('journal_lines.account_id', $bankAccount->account_id)
            ->addSelect([
                'journal_lines.id', 'journal_lines.description', 'journal_lines.reconciliation_id',
                'journal_entries.id as entry_id', 'journal_entries.date', 'journal_entries.number',
                'journal_entries.reference', 'journal_entries.description as entry_description',
            ])
            ->selectRaw("{$debit} - {$credit} as amount");
    }

    public function isBase(BankAccount $bankAccount): bool
    {
        return $this->currencies->isBase($bankAccount->currency_id);
    }
}
