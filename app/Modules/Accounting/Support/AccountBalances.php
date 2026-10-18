<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Foundation\Support\Filters\DateFilter;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Base-currency balances of accounts, read from the posted journal lines.
 * A balance is `debits - credits`: positive for a debit balance.
 *
 * Reports of a period's activity (trial balance, income statement, cash flow,
 * budgets) leave out year-end closing entries, otherwise a closed year would
 * show no revenue or expenses. The balance sheet and ledgers include them.
 */
class AccountBalances
{
    /**
     * The net movement of each account between the dates (both optional and
     * inclusive), keyed by account id.
     *
     * @param  array<mixed>|null  $accountIds
     * @return array<int, array{debit: string, credit: string, balance: string}>
     */
    public function movements(
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        ?array $accountIds = null,
        ?int $costCenterId = null,
        bool $excludeClosing = false,
    ): array {
        $rows = $this->lines($from, $to, $excludeClosing)
            ->when($accountIds !== null, fn (Builder $query) => $query->whereIn('journal_lines.account_id', $accountIds))
            ->when($costCenterId !== null, fn (Builder $query) => $query->where('journal_lines.cost_center_id', $costCenterId))
            ->groupBy('journal_lines.account_id')
            ->select('journal_lines.account_id')
            ->selectRaw('SUM(journal_lines.base_debit) as debit, SUM(journal_lines.base_credit) as credit')
            ->get();

        $movements = [];

        foreach ($rows as $row) {
            $movements[(int) $row->account_id] = [
                'debit' => Decimal::round((string) $row->debit),
                'credit' => Decimal::round((string) $row->credit),
                'balance' => Decimal::round(Decimal::of((string) $row->debit)->minus((string) $row->credit)),
            ];
        }

        return $movements;
    }

    /**
     * The balance of each account at the end of the day, from the beginning.
     *
     * @return array<int, string>
     */
    public function asOf(CarbonInterface|string $date, ?int $costCenterId = null, bool $excludeClosing = false): array
    {
        return array_map(
            fn (array $movement): string => $movement['balance'],
            $this->movements(null, $date, null, $costCenterId, $excludeClosing),
        );
    }

    /**
     * The net movement of each account per month (`2026-03`), between the dates.
     *
     * @param  array<mixed>|null  $accountIds
     * @return array<int, array<string, string>> account id => month => balance
     */
    public function monthly(CarbonInterface|string $from, CarbonInterface|string $to, ?array $accountIds = null, ?int $costCenterId = null): array
    {
        $month = SqlDate::yearMonth('journal_entries.date');

        $rows = $this->lines($from, $to, excludeClosing: true)
            ->when($accountIds !== null, fn (Builder $query) => $query->whereIn('journal_lines.account_id', $accountIds))
            ->when($costCenterId !== null, fn (Builder $query) => $query->where('journal_lines.cost_center_id', $costCenterId))
            ->groupBy('journal_lines.account_id', DB::raw($month))
            ->select('journal_lines.account_id')
            ->selectRaw("{$month} as month, SUM(journal_lines.base_debit) - SUM(journal_lines.base_credit) as balance")
            ->get();

        $monthly = [];

        foreach ($rows as $row) {
            $monthly[(int) $row->account_id][(string) $row->month] = Decimal::round((string) $row->balance);
        }

        return $monthly;
    }

    /**
     * Add the balances of every account to its ancestors, so group accounts
     * show the total of their branch.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  array<int, string>  $balances
     * @return array<int, string>
     */
    public function rollUp(Collection $accounts, array $balances): array
    {
        $totals = [];
        $parents = $accounts->pluck('parent_id', 'id')->all();

        foreach ($accounts as $account) {
            $totals[$account->id] ??= '0';
        }

        foreach ($balances as $accountId => $balance) {
            $current = $accountId;

            while ($current !== null && array_key_exists($current, $parents)) {
                $totals[$current] = Decimal::round(Decimal::of($totals[$current] ?? '0')->plus($balance));
                $current = $parents[$current];
            }
        }

        return $totals;
    }

    /**
     * The posted lines between the dates, joined to their entries.
     */
    public function lines(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null, bool $excludeClosing = false): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
            ->when($from !== null, fn (Builder $query) => DateFilter::onOrAfter($query, 'journal_entries.date', $from))
            ->when($to !== null, fn (Builder $query) => DateFilter::onOrBefore($query, 'journal_entries.date', $to))
            ->when($excludeClosing, fn (Builder $query) => $query->where(fn (Builder $entries) => $entries
                ->whereNull('journal_entries.source_type')
                ->orWhere('journal_entries.source_type', '!=', (new FiscalYear)->getMorphClass())));
    }
}
