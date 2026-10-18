<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Support\PayslipCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Start the payroll of a month as a draft, with a payslip for every
 * employee employed in it; or prepare an existing draft again after
 * employees changed. Payslips can then be adjusted before posting.
 */
class PreparePayrollRun
{
    public function __construct(protected PayslipCalculator $calculator) {}

    public function handle(CarbonInterface $month, ?PayrollRun $run = null): PayrollRun
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return DB::transaction(function () use ($start, $end, $run): PayrollRun {
            if ($run === null) {
                $exists = PayrollRun::query()
                    ->whereDate('period_start', $start->toDateString())
                    ->where('status', '!=', PayrollRunStatus::Cancelled)
                    ->exists();

                if ($exists) {
                    throw new AccountingException(__('The payroll of :month already exists.', ['month' => $start->format('Y-m')]));
                }

                $userId = Auth::id();
                $run = PayrollRun::query()->create([
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'status' => PayrollRunStatus::Draft,
                    'created_by' => is_int($userId) ? $userId : null,
                ]);
            } elseif ($run->status !== PayrollRunStatus::Draft) {
                throw new AccountingException(__('Only draft payrolls can be prepared again.'));
            }

            $run->payslips()->delete();

            $employees = Employee::query()->with('components.component')->employedDuring($start, $end)->orderBy('code')->get();

            foreach ($employees as $employee) {
                $pay = $this->calculator->calculate($employee, $start, $end);

                $payslip = $run->payslips()->create([
                    'employee_id' => $employee->id,
                    'cost_center_id' => $employee->cost_center_id,
                    'days_worked' => $pay['days_worked'],
                    'days_in_period' => $pay['days_in_period'],
                    'basic' => $pay['basic'],
                    ...PayslipCalculator::totals($pay['basic'], $pay['lines']),
                ]);

                $payslip->lines()->createMany($pay['lines']);
            }

            self::refreshTotals($run);

            return $run;
        });
    }

    /**
     * Add up the payslips into the run.
     */
    public static function refreshTotals(PayrollRun $run): void
    {
        $payslips = $run->payslips()->get();

        $run->update([
            'gross_total' => Decimal::round(Decimal::sum($payslips->map(fn (Payslip $payslip): string => Decimal::round(Decimal::of($payslip->basic)->plus($payslip->earnings_total))))),
            'deductions_total' => Decimal::round(Decimal::sum($payslips->pluck('deductions_total'))),
            'employer_total' => Decimal::round(Decimal::sum($payslips->pluck('employer_total'))),
            'net_total' => Decimal::round(Decimal::sum($payslips->pluck('net_pay'))),
        ]);
    }
}
