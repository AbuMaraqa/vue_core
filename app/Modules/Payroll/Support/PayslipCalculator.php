<?php

namespace App\Modules\Payroll\Support;

use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Payroll\Enums\PayCalculation;
use App\Modules\Payroll\Enums\PayComponentType;
use App\Modules\Payroll\Models\Employee;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Work out an employee's pay for a period:
 *
 * - basic salary for the days employed in the period (hired or leaving
 *   mid-month), by calendar days;
 * - each component: its fixed amount, or a percent of that basic;
 * - net pay = basic + earnings − deductions. Employer contributions are on
 *   top and not paid to the employee.
 *
 * Amounts are rounded to the base currency.
 */
class PayslipCalculator
{
    public function __construct(protected Currencies $currencies) {}

    /**
     * @return array{days_worked: int, days_in_period: int, basic: string, lines: list<array{pay_component_id: int, type: PayComponentType, amount: string}>}
     */
    public function calculate(Employee $employee, CarbonInterface $start, CarbonInterface $end): array
    {
        $decimals = $this->currencies->base()->decimal_places;
        $daysInPeriod = (int) $start->diffInDays($end) + 1;

        $from = Carbon::parse($employee->hire_date)->max($start);
        $to = $employee->termination_date ? Carbon::parse($employee->termination_date)->min($end) : Carbon::parse($end);
        $daysWorked = max(0, (int) $from->diffInDays($to) + 1);

        $basic = $daysWorked >= $daysInPeriod
            ? Decimal::round($employee->basic_salary, $decimals)
            : Decimal::round(Decimal::of($employee->basic_salary)->multipliedBy($daysWorked)->dividedBy($daysInPeriod, 8, RoundingMode::HalfUp), $decimals);

        $lines = [];

        foreach ($employee->components as $assignment) {
            $component = $assignment->component;

            if (! $component->is_active) {
                continue;
            }

            $amount = $component->calculation === PayCalculation::PercentOfBasic
                ? Decimal::percentOf($basic, (string) $assignment->value, $decimals)
                : Decimal::round($assignment->value, $decimals);

            if (Decimal::isPositive($amount)) {
                $lines[] = ['pay_component_id' => $component->id, 'type' => $component->type, 'amount' => Decimal::round($amount)];
            }
        }

        return [
            'days_worked' => $daysWorked,
            'days_in_period' => $daysInPeriod,
            'basic' => Decimal::round($basic),
            'lines' => $lines,
        ];
    }

    /**
     * The totals of a payslip from its basic and lines.
     *
     * @param  iterable<array{type: PayComponentType, amount: string}>  $lines
     * @return array{earnings_total: string, deductions_total: string, employer_total: string, net_pay: string}
     */
    public static function totals(string $basic, iterable $lines): array
    {
        $lines = collect($lines);
        $sum = fn (PayComponentType $type): string => Decimal::round(Decimal::sum($lines->where('type', $type)->pluck('amount')));

        $earnings = $sum(PayComponentType::Earning);
        $deductions = $sum(PayComponentType::Deduction);

        return [
            'earnings_total' => $earnings,
            'deductions_total' => $deductions,
            'employer_total' => $sum(PayComponentType::EmployerContribution),
            'net_pay' => Decimal::round(Decimal::of($basic)->plus($earnings)->minus($deductions)),
        ];
    }
}
