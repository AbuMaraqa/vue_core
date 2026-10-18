@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Banking\Enums\ChequeDirection')
@php
    $currency = $cheque->currency;
    $contact = $cheque->contact;
    $isIncoming = $cheque->direction === ChequeDirection::Incoming;

    $parties = [];

    if ($contact) {
        $parties[] = [
            'title' => $isIncoming ? __('Received from') : __('Paid to'),
            'name' => $contact->name,
            'lines' => [
                $contact->tax_number ? __('Tax number').': '.$contact->tax_number : null,
                implode(' · ', array_filter([$contact->phone ?? $contact->mobile, $contact->email])),
            ],
        ];
    }

    if ($cheque->endorsedTo) {
        $parties[] = ['title' => __('Endorsed to'), 'name' => $cheque->endorsedTo->name, 'lines' => []];
    }
@endphp

@if ($parties !== [])
    <x-print::parties :parties="$parties" />
@endif

<x-print::totals :rows="[
    ['label' => __('Amount').' ('.$currency->code.')', 'value' => Format::money($cheque->amount, $currency), 'emphasis' => true],
]" />

<x-print::amount-in-words :amount="$cheque->amount" :currency="$currency->translate('name')" :decimals="$currency->decimal_places" />

<x-print::notes :text="$cheque->description" :title="__('Description')" />

@if ($cheque->events->isNotEmpty())
    <h2 class="text-start">{{ __('History') }}</h2>

    <table class="grid" dir="{{ $direction }}">
        <thead>
            <tr>
                <th style="width: 16%">{{ __('Date') }}</th>
                <th>{{ __('Step') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Note') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cheque->events as $event)
                <tr @if ($event->is_undone) class="muted" @endif>
                    <td><span dir="ltr">{{ Format::date($event->date) }}</span></td>
                    <td>
                        {{ $event->action->label() }}
                        @if ($event->is_undone)
                            <div style="font-size: 8pt">{{ __('Undone') }}</div>
                        @endif
                    </td>
                    <td>{{ $event->to_status->label() }}</td>
                    <td>{{ $event->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
