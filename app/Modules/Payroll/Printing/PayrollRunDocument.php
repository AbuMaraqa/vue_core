<?php

namespace App\Modules\Payroll\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Foundation\Printing\Orientation;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;

/**
 * The summary of a month's payroll: every payslip of the run on one wide
 * table with the totals, for approval and the accounting file.
 */
class PayrollRunDocument extends FinancialDocument
{
    public function __construct(protected PayrollRun $run)
    {
        $run->loadMissing(['payslips.employee', 'payslips.costCenter', 'bankAccount']);
    }

    /**
     * The badge of a payroll that is not final: drafts can still change and
     * cancelled ones are out of the books.
     *
     * @return array{label: string, tone: 'neutral'|'success'|'warning'|'danger'}|null
     */
    public static function runStatus(PayrollRunStatus $status): ?array
    {
        return match ($status) {
            PayrollRunStatus::Draft => ['label' => $status->label(), 'tone' => 'warning'],
            PayrollRunStatus::Cancelled => ['label' => $status->label(), 'tone' => 'danger'],
            PayrollRunStatus::Posted, PayrollRunStatus::Paid => null,
        };
    }

    public function title(): string
    {
        return __('Payroll summary');
    }

    public function number(): ?string
    {
        return $this->run->label();
    }

    public function status(): ?array
    {
        return self::runStatus($this->run->status);
    }

    public function details(): array
    {
        return [
            __('Period') => self::date($this->run->period_start).' – '.self::date($this->run->period_end),
            __('Payment date') => self::date($this->run->payment_date),
            __('Paid from') => $this->run->bankAccount?->label(),
            __('Employees') => (string) $this->run->payslips->count(),
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Approved by')];
    }

    public function orientation(): Orientation
    {
        return Orientation::Landscape;
    }

    protected function view(): string
    {
        return 'payroll::print.payroll-run';
    }

    protected function data(): array
    {
        return [
            'run' => $this->run,
            'payslips' => $this->run->payslips->sortBy(fn (Payslip $payslip): string => $payslip->employee->code)->values(),
            'currency' => app(Currencies::class)->base(),
        ];
    }
}
