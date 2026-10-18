<?php

namespace App\Modules\Banking\Calendar;

use App\Models\User;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use Carbon\CarbonInterface;

/**
 * The day each cheque changed hands (تاريخ التسليم): received from a
 * customer, or handed to a vendor.
 */
class ChequeHandovers extends CalendarSource
{
    public function key(): string
    {
        return 'cheque_handover';
    }

    public function label(): string
    {
        return __('Cheques received and handed over');
    }

    public function color(): string
    {
        return 'info';
    }

    public function permission(): string
    {
        return 'Cheque View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        return Cheque::query()
            ->with(['contact', 'currency'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(fn (Cheque $cheque): CalendarEntry => new CalendarEntry(
                id: "cheque-handover-{$cheque->id}",
                type: $this->key(),
                date: $cheque->issue_date,
                title: $cheque->direction === ChequeDirection::Incoming
                    ? __('Cheque :number received', ['number' => $cheque->number])
                    : __('Cheque :number handed over', ['number' => $cheque->number]),
                subtitle: $cheque->contact?->name,
                url: route('admin.banking.cheques.show', $cheque),
                status: __('Due :date', ['date' => $cheque->due_date->toDateString()]),
                amount: (string) $cheque->amount,
                currency: $cheque->currency->code,
                decimals: $cheque->currency->decimal_places,
            ));
    }
}
