<?php

namespace App\Modules\Foundation\Support\Calendar;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A kind of dated thing a module puts on the calendar, e.g. "cheque due
 * dates". Each module registers its sources with `Calendar::register()` in
 * its service provider; the calendar shows a source only to users who hold
 * its permission.
 */
abstract class CalendarSource
{
    /**
     * A stable key, e.g. `cheque_due`; the frontend filters by it.
     */
    abstract public function key(): string;

    /**
     * The translated name shown in the legend, e.g. "Cheque due dates".
     */
    abstract public function label(): string;

    /**
     * One of: brand, info, success, warning, error, orange, purple, pink, gray.
     */
    abstract public function color(): string;

    /**
     * The entries dated between `$from` and `$to`, both included.
     *
     * @return iterable<CalendarEntry>
     */
    abstract public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable;

    /**
     * Entries dated before `$today` that still need action, e.g. cheques not
     * yet collected; shown at the top of the agenda whatever their date.
     *
     * @return iterable<CalendarEntry>
     */
    public function overdue(CarbonInterface $today, User $user): iterable
    {
        return [];
    }

    /**
     * The permission needed to see this source, or null for every staff
     * member who can open the calendar.
     */
    public function permission(): ?string
    {
        return null;
    }

    public function visibleTo(User $user): bool
    {
        $permission = $this->permission();

        return $permission === null || $user->can($permission);
    }
}
