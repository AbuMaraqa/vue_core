<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Payroll\Enums\PayComponentType;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Accrue a payroll at the end of its period:
 *
 *   Dr salaries expense            basic, by cost center
 *   Dr each earning's account      by cost center
 *   Dr each contribution's account by cost center, Cr its liability account
 *   Cr each deduction's account    (social security, tax, advances...)
 *   Cr salaries payable            net pay, per employee
 *
 * Expenses are grouped by account and cost center; what is owed is kept per
 * employee (as the lines' partner).
 */
class PostPayrollRun
{
    public function __construct(
        protected Ledger $ledger,
        protected DocumentNumbers $numbers,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->with(['payslips.employee', 'payslips.lines.component'])->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::Draft) {
                throw new AccountingException(__('Only draft payrolls can be posted.'));
            }

            if ($run->payslips->isEmpty()) {
                throw new AccountingException(__('The payroll has no payslips.'));
            }

            $this->ledger->ensureDateIsOpen($run->period_end);
            $run->number = $this->numbers->next(DocumentType::PayrollRun, $run->period_end);

            $salaries = $this->defaults->id(AccountRole::SalariesExpense);
            $payable = $this->defaults->id(AccountRole::SalariesPayable);

            /** @var array<string, array{account: int, cost_center: int|null, amount: BigDecimal}> $expenses */
            $expenses = [];
            $add = function (int $account, ?int $costCenter, string $amount) use (&$expenses): void {
                $key = "{$account}-{$costCenter}";
                $expenses[$key] ??= ['account' => $account, 'cost_center' => $costCenter, 'amount' => BigDecimal::zero()];
                $expenses[$key]['amount'] = $expenses[$key]['amount']->plus($amount);
            };

            $entry = JournalEntryBuilder::make($run->period_end, __('Payroll :month', ['month' => $run->period_start->format('Y-m')]))
                ->reference($run->number)
                ->source($run);

            $credits = [];

            foreach ($run->payslips as $payslip) {
                $add($salaries, $payslip->cost_center_id, $payslip->basic);

                foreach ($payslip->lines as $line) {
                    $component = $line->component;

                    if ($line->type !== PayComponentType::Deduction) {
                        $add($component->account_id, $payslip->cost_center_id, $line->amount);
                    }

                    if ($line->type === PayComponentType::Deduction) {
                        $credits[] = [$component->account_id, $line->amount, $payslip->employee];
                    }

                    if ($line->type === PayComponentType::EmployerContribution) {
                        $credits[] = [
                            $component->liability_account_id ?? throw new AccountingException(__('Choose the liability account of :component.', ['component' => $component->translate('name')])),
                            $line->amount,
                            $payslip->employee,
                        ];
                    }
                }

                $credits[] = [$payable, $payslip->net_pay, $payslip->employee];
            }

            foreach ($expenses as $expense) {
                $entry->debit($expense['account'], $expense['amount'], costCenterId: $expense['cost_center']);
            }

            foreach ($credits as [$account, $amount, $employee]) {
                $entry->credit($account, $amount, partner: $employee);
            }

            $entry->post();

            $run->status = PayrollRunStatus::Posted;
            $run->save();

            return $run;
        });
    }

    /**
     * The sum of what the run owes its employees.
     */
    public static function netTotal(PayrollRun $run): string
    {
        return Decimal::round(Decimal::sum($run->payslips->pluck('net_pay')));
    }
}
