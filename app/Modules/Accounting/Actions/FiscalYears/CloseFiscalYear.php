<?php

namespace App\Modules\Accounting\Actions\FiscalYears;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Close a fiscal year: move the year's revenue and expenses into retained
 * earnings with a closing entry dated on the last day, then lock the year.
 * The income statement of the next year then starts from zero.
 */
class CloseFiscalYear
{
    public function __construct(
        protected AccountBalances $balances,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(FiscalYear $year): FiscalYear
    {
        return DB::transaction(function () use ($year): FiscalYear {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($year->id);

            if (! $year->isOpen()) {
                throw new AccountingException(__('The fiscal year :name is already closed.', ['name' => $year->name]));
            }

            $openEarlierYear = FiscalYear::query()
                ->where('status', FiscalYearStatus::Open)
                ->whereDate('end_date', '<', $year->start_date)
                ->exists();

            if ($openEarlierYear) {
                throw new AccountingException(__('Close the earlier fiscal years first.'));
            }

            $year->forceFill([
                'closing_entry_id' => $this->postClosingEntry($year)?->id,
            ])->save();

            $year->forceFill([
                'status' => FiscalYearStatus::Closed,
                'closed_at' => now(),
                'closed_by' => Auth::id(),
            ])->save();

            return $year;
        });
    }

    protected function postClosingEntry(FiscalYear $year): ?JournalEntry
    {
        $incomeStatementAccounts = Account::query()
            ->whereIn('type', [AccountType::Revenue, AccountType::Expense])
            ->pluck('id')
            ->all();

        $movements = $this->balances->movements($year->start_date, $year->end_date, $incomeStatementAccounts);

        $builder = JournalEntryBuilder::make($year->end_date, __('Closing of fiscal year :name', ['name' => $year->name]))->source($year);
        $net = Decimal::of(0);

        foreach ($movements as $accountId => $movement) {
            // Bring every account to zero: a debit balance is credited, a credit balance debited.
            $builder->credit($accountId, $movement['balance']);
            $net = $net->plus($movement['balance']);
        }

        if (! $builder->hasLines()) {
            return null;
        }

        // A profit (credit balances) increases retained earnings; a loss decreases them.
        $builder->debit($this->defaults->get(AccountRole::RetainedEarnings), (string) $net);

        return $builder->post();
    }
}
