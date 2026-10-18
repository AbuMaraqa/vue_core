<?php

namespace App\Modules\Accounting\Calendar;

use App\Models\User;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use Carbon\CarbonInterface;

/**
 * The start and end of each fiscal year; an open year past its end waits
 * to be closed.
 */
class FiscalYearDates extends CalendarSource
{
    public function key(): string
    {
        return 'fiscal_year';
    }

    public function label(): string
    {
        return __('Fiscal years');
    }

    public function color(): string
    {
        return 'info';
    }

    public function permission(): string
    {
        return 'Fiscal Year View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $today = now()->startOfDay();
        $entries = [];

        $years = FiscalYear::query()
            ->where(fn ($query) => $query->whereBetween('start_date', $range)->orWhereBetween('end_date', $range))
            ->get();

        foreach ($years as $year) {
            if ($year->start_date->between($from, $to)) {
                $entries[] = new CalendarEntry(
                    id: "fiscal-year-start-{$year->id}",
                    type: $this->key(),
                    date: $year->start_date,
                    title: __('Fiscal year :name starts', ['name' => $year->name]),
                    url: route('admin.accounting.fiscal-years.index'),
                );
            }

            if ($year->end_date->between($from, $to)) {
                $entries[] = $this->endEntry($year, $year->status === FiscalYearStatus::Open && $year->end_date->lessThan($today));
            }
        }

        return $entries;
    }

    public function overdue(CarbonInterface $today, User $user): iterable
    {
        return FiscalYear::query()
            ->where('status', FiscalYearStatus::Open)
            ->whereDate('end_date', '<', $today->toDateString())
            ->orderBy('end_date')
            ->get()
            ->map(fn (FiscalYear $year): CalendarEntry => $this->endEntry($year, true));
    }

    protected function endEntry(FiscalYear $year, bool $overdue): CalendarEntry
    {
        return new CalendarEntry(
            id: "fiscal-year-end-{$year->id}",
            type: $this->key(),
            date: $year->end_date,
            title: __('Fiscal year :name ends', ['name' => $year->name]),
            url: route('admin.accounting.fiscal-years.index'),
            status: $year->status->label(),
            color: $overdue ? 'error' : null,
            overdue: $overdue,
        );
    }
}
