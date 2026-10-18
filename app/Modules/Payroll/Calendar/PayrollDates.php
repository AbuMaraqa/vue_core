<?php

namespace App\Modules\Payroll\Calendar;

use App\Models\User;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Salary days: each payroll on the day it was paid, or at the end of its
 * month while unpaid (red once that day has passed), and the end of every
 * coming month that has employees but no payroll yet.
 */
class PayrollDates extends CalendarSource
{
    public function __construct(protected Currencies $currencies) {}

    public function key(): string
    {
        return 'payroll';
    }

    public function label(): string
    {
        return __('Salaries');
    }

    public function color(): string
    {
        return 'purple';
    }

    public function permission(): string
    {
        return 'Payroll Run View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $today = now()->startOfDay();
        $entries = [];

        $runs = PayrollRun::query()
            ->where('status', '!=', PayrollRunStatus::Cancelled)
            ->where(fn ($query) => $query
                ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
                ->orWhere(fn ($unpaid) => $unpaid->whereNull('payment_date')->whereBetween('period_end', [$from->toDateString(), $to->toDateString()])))
            ->get();

        foreach ($runs as $run) {
            $entries[] = $this->entry($run, $run->status !== PayrollRunStatus::Paid && $run->period_end->lessThan($today));
        }

        foreach ($this->monthsWithoutPayroll($from, $to, $today) as $monthEnd) {
            $entries[] = new CalendarEntry(
                id: "payroll-due-{$monthEnd->format('Y-m')}",
                type: $this->key(),
                date: $monthEnd,
                title: __('Salaries of :month are due', ['month' => $monthEnd->format('Y-m')]),
                url: route('admin.payroll.runs.index'),
                status: __('Not prepared'),
            );
        }

        return $entries;
    }

    public function overdue(CarbonInterface $today, User $user): iterable
    {
        return PayrollRun::query()
            ->whereIn('status', [PayrollRunStatus::Draft, PayrollRunStatus::Posted])
            ->whereDate('period_end', '<', $today->toDateString())
            ->orderBy('period_end')
            ->get()
            ->map(fn (PayrollRun $run): CalendarEntry => $this->entry($run, true));
    }

    protected function entry(PayrollRun $run, bool $overdue): CalendarEntry
    {
        $base = $this->currencies->base();

        return new CalendarEntry(
            id: "payroll-{$run->id}",
            type: $this->key(),
            date: $run->payment_date ?? $run->period_end,
            title: __('Payroll :period', ['period' => $run->period_start->format('Y-m')]),
            subtitle: $run->number,
            url: route('admin.payroll.runs.show', $run),
            status: $run->status->label(),
            amount: (string) $run->net_total,
            currency: $base->code,
            decimals: $base->decimal_places,
            color: $overdue ? 'error' : ($run->status === PayrollRunStatus::Paid ? 'gray' : null),
            overdue: $overdue,
        );
    }

    /**
     * The month ends in the period, from this month on, that have employees
     * but no payroll.
     *
     * @return list<CarbonInterface>
     */
    protected function monthsWithoutPayroll(CarbonInterface $from, CarbonInterface $to, CarbonInterface $today): array
    {
        $months = [];
        $month = Carbon::parse($from)->startOfMonth();

        while ($month->lessThanOrEqualTo($to)) {
            $monthEnd = $month->copy()->endOfMonth()->startOfDay();
            $isCovered = $monthEnd->greaterThanOrEqualTo($from->copy()->startOfDay()) && $monthEnd->lessThanOrEqualTo($to);

            if ($isCovered && $monthEnd->greaterThanOrEqualTo($today->copy()->startOfMonth())
                && ! PayrollRun::query()->where('status', '!=', PayrollRunStatus::Cancelled)->whereDate('period_start', $month->toDateString())->exists()
                && Employee::query()->employedDuring($month, $monthEnd)->exists()) {
                $months[] = $monthEnd;
            }

            $month = $month->addMonth();
        }

        return $months;
    }
}
