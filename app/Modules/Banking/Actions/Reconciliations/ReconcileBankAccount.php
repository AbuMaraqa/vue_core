<?php

namespace App\Modules\Banking\Actions\Reconciliations;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankReconciliation;
use App\Modules\Banking\Support\BankBalances;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Match a bank statement against the books: the ticked lines are marked as
 * cleared, and what has been cleared so far must equal the statement's
 * closing balance. Only the latest reconciliation can be undone.
 */
class ReconcileBankAccount
{
    public function __construct(protected BankBalances $balances) {}

    /**
     * The total of every line already reconciled on the account, in its
     * currency: the opening balance of the next statement.
     */
    public function reconciledBalance(BankAccount $bankAccount): string
    {
        $total = DB::query()
            ->fromSub($this->balances->lines($bankAccount)->whereNotNull('journal_lines.reconciliation_id'), 'reconciled')
            ->sum('amount');

        return Decimal::round((string) $total);
    }

    /**
     * @param  list<int>  $lineIds
     */
    public function handle(BankAccount $bankAccount, string $statementDate, string $statementBalance, array $lineIds): BankReconciliation
    {
        return DB::transaction(function () use ($bankAccount, $statementDate, $statementBalance, $lineIds): BankReconciliation {
            $lines = $this->balances->lines($bankAccount, null, $statementDate)
                ->whereIn('journal_lines.id', $lineIds)
                ->whereNull('journal_lines.reconciliation_id')
                ->lockForUpdate()
                ->get();

            if ($lines->count() !== count(array_unique($lineIds))) {
                throw new AccountingException(__('Some of the chosen lines are not open lines of this account up to the statement date.'));
            }

            $cleared = Decimal::of($this->reconciledBalance($bankAccount))->plus(Decimal::sum($lines->pluck('amount')->map(fn (mixed $amount): string => (string) $amount)));

            if (! $cleared->isEqualTo(Decimal::of($statementBalance))) {
                throw new AccountingException(__('The cleared balance :cleared does not match the statement balance :statement.', [
                    'cleared' => Decimal::round($cleared),
                    'statement' => Decimal::round($statementBalance),
                ]));
            }

            $userId = Auth::id();
            $reconciliation = BankReconciliation::query()->create([
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $statementDate,
                'statement_balance' => Decimal::round($statementBalance),
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            JournalLine::query()->whereKey($lines->pluck('id'))->update(['reconciliation_id' => $reconciliation->id]);

            return $reconciliation;
        });
    }

    /**
     * Undo the latest reconciliation of the account: its lines are open
     * again.
     */
    public function undo(BankReconciliation $reconciliation): void
    {
        DB::transaction(function () use ($reconciliation): void {
            $latest = BankReconciliation::query()->where('bank_account_id', $reconciliation->bank_account_id)->latest('id')->lockForUpdate()->first();

            if ($latest?->id !== $reconciliation->id) {
                throw new AccountingException(__('Only the latest reconciliation can be undone.'));
            }

            JournalLine::query()->where('reconciliation_id', $reconciliation->id)->update(['reconciliation_id' => null]);
            $reconciliation->delete();
        });
    }
}
