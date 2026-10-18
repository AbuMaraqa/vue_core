<?php

namespace App\Modules\Payroll\Calendar;

use App\Models\User;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use App\Modules\Payroll\Models\Employee;
use Carbon\CarbonInterface;

/**
 * When employees join and when they leave.
 */
class EmployeeDates extends CalendarSource
{
    public function key(): string
    {
        return 'employee';
    }

    public function label(): string
    {
        return __('Employees joining and leaving');
    }

    public function color(): string
    {
        return 'pink';
    }

    public function permission(): string
    {
        return 'Employee View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $entries = [];

        $employees = Employee::query()
            ->where(fn ($query) => $query->whereBetween('hire_date', $range)->orWhereBetween('termination_date', $range))
            ->get();

        foreach ($employees as $employee) {
            if ($employee->hire_date->between($from, $to)) {
                $entries[] = new CalendarEntry(
                    id: "employee-joins-{$employee->id}",
                    type: $this->key(),
                    date: $employee->hire_date,
                    title: __(':name joins', ['name' => $employee->name]),
                    subtitle: $employee->position,
                    url: route('admin.payroll.employees.show', $employee),
                );
            }

            if ($employee->termination_date?->between($from, $to)) {
                $entries[] = new CalendarEntry(
                    id: "employee-leaves-{$employee->id}",
                    type: $this->key(),
                    date: $employee->termination_date,
                    title: __(':name leaves', ['name' => $employee->name]),
                    subtitle: $employee->position,
                    url: route('admin.payroll.employees.show', $employee),
                    color: 'gray',
                );
            }
        }

        return $entries;
    }
}
