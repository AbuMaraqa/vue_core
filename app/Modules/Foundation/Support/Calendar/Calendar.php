<?php

namespace App\Modules\Foundation\Support\Calendar;

use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The calendar of the whole system. Modules register the kinds of dates they
 * own (cheque due dates, invoice due dates, payroll days...) and the
 * calendar gathers them for a period, keeping only what the user may see.
 *
 * Only class names are kept here, so the singleton holds no request state;
 * sources are resolved from the container each time.
 */
class Calendar
{
    /**
     * @var list<class-string<CalendarSource>>
     */
    protected array $sources = [];

    /**
     * @param  class-string<CalendarSource>  ...$sources
     */
    public function register(string ...$sources): void
    {
        foreach ($sources as $source) {
            if (! is_subclass_of($source, CalendarSource::class)) {
                throw new InvalidArgumentException("{$source} must extend ".CalendarSource::class.'.');
            }

            if (! in_array($source, $this->sources, true)) {
                $this->sources[] = $source;
            }
        }
    }

    /**
     * The sources the user may see, in the order they were registered.
     *
     * @return list<CalendarSource>
     */
    public function sourcesFor(User $user): array
    {
        return array_values(array_filter(
            array_map(fn (string $source): CalendarSource => app($source), $this->sources),
            fn (CalendarSource $source): bool => $source->visibleTo($user),
        ));
    }

    /**
     * Every entry the user may see between two days, both included, ordered
     * by day and time.
     *
     * @return list<CalendarEntry>
     */
    public function entries(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $entries = [];

        foreach ($this->sourcesFor($user) as $source) {
            foreach ($source->entries($from->copy()->startOfDay(), $to->copy()->endOfDay(), $user) as $entry) {
                $entries[] = $entry;
            }
        }

        return $this->sort($entries);
    }

    /**
     * What is past its date and still open, oldest first.
     *
     * @return list<CalendarEntry>
     */
    public function overdue(User $user, CarbonInterface $today): array
    {
        $entries = [];

        foreach ($this->sourcesFor($user) as $source) {
            foreach ($source->overdue($today->copy()->startOfDay(), $user) as $entry) {
                $entries[] = $entry;
            }
        }

        return $this->sort($entries);
    }

    /**
     * @param  list<CalendarEntry>  $entries
     * @return list<CalendarEntry>
     */
    protected function sort(array $entries): array
    {
        usort($entries, fn (CalendarEntry $a, CalendarEntry $b): int => [$a->date, $a->time ?? '', $a->title] <=> [$b->date, $b->time ?? '', $b->title]);

        return $entries;
    }
}
