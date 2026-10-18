<?php

namespace App\Modules\Foundation\Support\Calendar;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One dated thing shown on the calendar, e.g. the due date of a cheque or a
 * reminder. Entries are all-day unless they have a `time`.
 *
 * `color` overrides the color of the entry's source, e.g. red for an overdue
 * cheque. `data` carries what a source's own frontend needs, e.g. the fields
 * of a reminder to edit it.
 */
final readonly class CalendarEntry
{
    public string $date;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $id,
        public string $type,
        CarbonInterface|string $date,
        public string $title,
        public ?string $subtitle = null,
        public ?string $url = null,
        public ?string $status = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public int $decimals = 2,
        public ?string $color = null,
        public bool $overdue = false,
        public ?string $time = null,
        public array $data = [],
    ) {
        $this->date = Carbon::parse($date)->toDateString();
    }

    /**
     * @return array{id: string, type: string, date: string, time: string|null, title: string, subtitle: string|null, url: string|null, status: string|null, amount: string|null, currency: string|null, decimals: int, color: string|null, overdue: bool, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'date' => $this->date,
            'time' => $this->time,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'decimals' => $this->decimals,
            'color' => $this->color,
            'overdue' => $this->overdue,
            'data' => $this->data,
        ];
    }
}
