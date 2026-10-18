<?php

namespace App\Modules\Payroll\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Payroll\Enums\PayCalculation;
use Illuminate\Database\Query\Builder;

/**
 * Salaries are paid in the base currency. Components worked out as a percent
 * of the basic salary keep their percent; fixed ones are converted.
 */
class ConvertPayrollToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $conversion = $event->conversion;

        $conversion->convertColumns('employees', ['basic_salary' => 4]);
        $conversion->convertColumns('pay_components', ['default_value' => 4], fn (Builder $query) => $query->where('calculation', PayCalculation::Fixed->value));
        $conversion->convertColumns('employee_pay_components', ['value' => 4], fn (Builder $query) => $query->whereIn(
            'pay_component_id',
            fn (Builder $components) => $components->select('id')->from('pay_components')->where('calculation', PayCalculation::Fixed->value),
        ));
        $conversion->convertColumns('payroll_runs', ['gross_total' => 4, 'deductions_total' => 4, 'employer_total' => 4, 'net_total' => 4]);
        $conversion->convertColumns('payslips', ['basic' => 4, 'earnings_total' => 4, 'deductions_total' => 4, 'employer_total' => 4, 'net_pay' => 4]);
        $conversion->convertColumns('payslip_lines', ['amount' => 4]);
    }
}
