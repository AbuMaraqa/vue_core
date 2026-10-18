<?php

namespace App\Modules\Payroll\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Payroll\Enums\PayComponentType;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipLine;

/**
 * One employee's payslip for a payroll run: the basic salary and earnings
 * beside the deductions, the net pay in figures and in words, and the
 * employer contributions for information.
 */
class PayslipDocument extends FinancialDocument
{
    public function __construct(protected Payslip $payslip)
    {
        $payslip->loadMissing(['run', 'employee', 'costCenter', 'lines.component']);
    }

    public function title(): string
    {
        return __('Payslip');
    }

    public function number(): ?string
    {
        return $this->payslip->run->label().' / '.$this->payslip->employee->code;
    }

    public function status(): ?array
    {
        return PayrollRunDocument::runStatus($this->payslip->run->status);
    }

    public function details(): array
    {
        $run = $this->payslip->run;

        return [
            __('Period') => self::date($run->period_start).' – '.self::date($run->period_end),
            __('Payment date') => self::date($run->payment_date),
            __('Days worked') => $this->payslip->days_worked.' / '.$this->payslip->days_in_period,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Employee signature')];
    }

    public function filename(): string
    {
        return str_replace('/', '-', $this->payslip->run->label()).'-'.$this->payslip->employee->code.'.pdf';
    }

    protected function view(): string
    {
        return 'payroll::print.payslip';
    }

    protected function data(): array
    {
        $lines = $this->payslip->lines;
        $ofType = fn (PayComponentType $type) => $lines->filter(fn (PayslipLine $line): bool => $line->type === $type)->values();

        return [
            'payslip' => $this->payslip,
            'earnings' => $ofType(PayComponentType::Earning),
            'deductions' => $ofType(PayComponentType::Deduction),
            'contributions' => $ofType(PayComponentType::EmployerContribution),
            'currency' => app(Currencies::class)->base(),
        ];
    }
}
