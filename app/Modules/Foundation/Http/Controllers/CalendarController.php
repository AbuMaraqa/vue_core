<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Foundation\Http\Requests\SaveReminderRequest;
use App\Modules\Foundation\Models\Reminder;
use App\Modules\Foundation\Support\Calendar\Calendar;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The calendar of the whole system: every module's dates on one month grid
 * (cheque due dates, invoices to collect or pay, payroll days, production
 * orders...), the overdue items, the coming two weeks, and the reminders
 * staff add themselves.
 */
class CalendarController extends Controller
{
    /**
     * Saturday, as in most Arab countries.
     */
    public const int WEEK_STARTS_ON = CarbonInterface::SATURDAY;

    public const int UPCOMING_DAYS = 14;

    public function index(Request $request, Calendar $calendar): Response
    {
        Gate::authorize('Calendar View');

        /** @var User $user */
        $user = $request->user();
        $month = $this->month($request->string('month')->value());
        $today = now()->startOfDay();

        $from = $month->copy()->startOfMonth()->startOfWeek(self::WEEK_STARTS_ON);
        $to = $month->copy()->endOfMonth()->endOfWeek((self::WEEK_STARTS_ON + 6) % 7);

        return Inertia::render('admin/calendar/Index', [
            'month' => $month->format('Y-m'),
            'today' => $today->toDateString(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'weekStartsOn' => self::WEEK_STARTS_ON,
            'sources' => array_map(fn (CalendarSource $source): array => [
                'key' => $source->key(),
                'label' => $source->label(),
                'color' => $source->color(),
            ], $calendar->sourcesFor($user)),
            'entries' => $this->toArrays($calendar->entries($user, $from, $to)),
            'upcoming' => $this->toArrays($calendar->entries($user, $today, $today->copy()->addDays(self::UPCOMING_DAYS - 1))),
            'overdue' => $this->toArrays($calendar->overdue($user, $today)),
            'reminderColors' => Reminder::COLORS,
        ]);
    }

    public function storeReminder(SaveReminderRequest $request): RedirectResponse
    {
        Reminder::query()->create([...$request->attributesToSave(), 'user_id' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder added.')]);

        return back();
    }

    public function updateReminder(SaveReminderRequest $request, Reminder $reminder): RedirectResponse
    {
        $reminder->update($request->attributesToSave());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder updated.')]);

        return back();
    }

    public function destroyReminder(Reminder $reminder): RedirectResponse
    {
        Gate::authorize('delete', $reminder);

        $reminder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder deleted.')]);

        return back();
    }

    /**
     * The requested month (`Y-m`), else the current one.
     */
    protected function month(string $value): CarbonInterface
    {
        try {
            return preg_match('/^\d{4}-\d{2}$/', $value) === 1
                ? Carbon::createFromFormat('!Y-m', $value) ?? now()->startOfMonth()
                : now()->startOfMonth();
        } catch (Throwable) {
            return now()->startOfMonth();
        }
    }

    /**
     * @param  list<CalendarEntry>  $entries
     * @return list<array<string, mixed>>
     */
    protected function toArrays(array $entries): array
    {
        return array_map(fn (CalendarEntry $entry): array => $entry->toArray(), $entries);
    }
}
