<?php

namespace App\Modules\Payroll\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * What a pay component does to a payslip:
 *
 * - earning: added to pay (allowances, overtime, bonuses), an expense;
 * - deduction: taken from pay (employee social security, income tax, an
 *   advance being recovered), owed to someone else or recovered;
 * - employer contribution: paid by the company on top of pay (employer
 *   social security), an expense owed to the authority; not in net pay.
 */
enum PayComponentType: string
{
    use EnumOptions;

    case Earning = 'earning';
    case Deduction = 'deduction';
    case EmployerContribution = 'employer_contribution';

    public function label(): string
    {
        return match ($this) {
            self::Earning => __('Earning'),
            self::Deduction => __('Deduction'),
            self::EmployerContribution => __('Employer contribution'),
        };
    }
}
