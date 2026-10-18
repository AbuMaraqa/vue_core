<?php

namespace App\Modules\Foundation\Support\Calendar\Sources;

use App\Models\User;
use App\Modules\Foundation\Models\Reminder;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use Carbon\CarbonInterface;

/**
 * The reminders staff add to the calendar themselves.
 */
class ReminderDates extends CalendarSource
{
    public function key(): string
    {
        return 'reminder';
    }

    public function label(): string
    {
        return __('Reminders');
    }

    public function color(): string
    {
        return 'brand';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        return Reminder::query()
            ->with('user')
            ->visibleTo($user)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(fn (Reminder $reminder): CalendarEntry => new CalendarEntry(
                id: "reminder-{$reminder->id}",
                type: $this->key(),
                date: $reminder->date,
                title: $reminder->title,
                subtitle: $reminder->user_id === $user->id ? null : $reminder->user->name,
                color: $reminder->color,
                time: $reminder->time === null ? null : substr($reminder->time, 0, 5),
                data: [
                    'reminder_id' => $reminder->id,
                    'notes' => $reminder->notes,
                    'is_shared' => $reminder->is_shared,
                    'editable' => $user->can('update', $reminder),
                ],
            ));
    }
}
