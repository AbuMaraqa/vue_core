<?php

namespace App\Modules\Banking\Calendar;

use App\Models\User;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use Carbon\CarbonInterface;

/**
 * The day each cheque falls due (تاريخ الاستحقاق). Cheques still waiting for
 * the bank stand out, in red once their day has passed; settled ones stay
 * visible in grey, as history.
 */
class ChequeDueDates extends CalendarSource
{
    public function key(): string
    {
        return 'cheque_due';
    }

    public function label(): string
    {
        return __('Cheque due dates');
    }

    public function color(): string
    {
        return 'warning';
    }

    public function permission(): string
    {
        return 'Cheque View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $today = now()->startOfDay();

        return Cheque::query()
            ->with(['contact', 'currency'])
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ChequeStatus::Cancelled)
            ->get()
            ->map(fn (Cheque $cheque): CalendarEntry => $this->entry($cheque, $cheque->status->isOpen() && $cheque->due_date->lessThan($today)));
    }

    public function overdue(CarbonInterface $today, User $user): iterable
    {
        return Cheque::query()
            ->with(['contact', 'currency'])
            ->open()
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn (Cheque $cheque): CalendarEntry => $this->entry($cheque, true));
    }

    protected function entry(Cheque $cheque, bool $overdue): CalendarEntry
    {
        return new CalendarEntry(
            id: "cheque-due-{$cheque->id}",
            type: $this->key(),
            date: $cheque->due_date,
            title: __(':kind :number due', ['kind' => $cheque->direction->label(), 'number' => $cheque->number]),
            subtitle: $cheque->contact?->name,
            url: route('admin.banking.cheques.show', $cheque),
            status: $cheque->status->label(),
            amount: (string) $cheque->amount,
            currency: $cheque->currency->code,
            decimals: $cheque->currency->decimal_places,
            color: match (true) {
                $overdue, $cheque->status === ChequeStatus::Bounced => 'error',
                ! $cheque->status->isOpen() => 'gray',
                default => null,
            },
            overdue: $overdue,
        );
    }
}
