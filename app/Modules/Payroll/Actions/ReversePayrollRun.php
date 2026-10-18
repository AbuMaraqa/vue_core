<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

/**
 * Step a payroll back: a paid payroll's payment is reversed (it is posted
 * again); a posted payroll's accrual is reversed (it is cancelled); a draft
 * is deleted.
 */
class ReversePayrollRun
{
    public function __construct(protected Ledger $ledger) {}

    public function handle(PayrollRun $run): ?PayrollRun
    {
        return DB::transaction(function () use ($run): ?PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->status === PayrollRunStatus::Draft) {
                $run->delete();

                return null;
            }

            if ($run->status === PayrollRunStatus::Cancelled) {
                throw new AccountingException(__('The payroll is already cancelled.'));
            }

            $latest = $run->journalEntries()->where('status', JournalEntryStatus::Posted)->whereNull('reversal_of_id')->orderByDesc('id')->first();

            if ($latest !== null) {
                $this->ledger->reverse($latest, $this->ledger->openDateFor($latest->date), __('Reversal of :number', ['number' => $run->number]));
            }

            $run->update($run->status === PayrollRunStatus::Paid
                ? ['status' => PayrollRunStatus::Posted, 'payment_date' => null, 'bank_account_id' => null]
                : ['status' => PayrollRunStatus::Cancelled]);

            return $run;
        });
    }
}
