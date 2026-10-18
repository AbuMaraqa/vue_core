<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

/**
 * Pay a posted payroll from a bank or cash account in the base currency:
 *
 *   Dr salaries payable   net pay, per employee
 *   Cr bank account       the total
 */
class PayPayrollRun
{
    public function __construct(
        protected Ledger $ledger,
        protected DefaultAccounts $defaults,
        protected Currencies $currencies,
    ) {}

    public function handle(PayrollRun $run, BankAccount $bankAccount, string $date): PayrollRun
    {
        return DB::transaction(function () use ($run, $bankAccount, $date): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->with('payslips.employee')->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::Posted) {
                throw new AccountingException(__('Only posted payrolls can be paid.'));
            }

            if (! $this->currencies->isBase($bankAccount->currency_id)) {
                throw new AccountingException(__('Salaries are paid from an account in the base currency.'));
            }

            $payable = $this->defaults->id(AccountRole::SalariesPayable);

            $entry = JournalEntryBuilder::make($date, __('Payment of payroll :number', ['number' => $run->number]))
                ->reference($run->number)
                ->source($run);

            foreach ($run->payslips as $payslip) {
                $entry->debit($payable, $payslip->net_pay, partner: $payslip->employee);
            }

            $entry->credit($bankAccount->account_id, PostPayrollRun::netTotal($run))->post();

            $run->update(['status' => PayrollRunStatus::Paid, 'payment_date' => $date, 'bank_account_id' => $bankAccount->id]);

            return $run;
        });
    }
}
