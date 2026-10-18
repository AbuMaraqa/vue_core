@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Accounting\Enums\DocumentStatus')
@php
    $currency = $payment->currency;
    $contact = $payment->contact;
    $allocated = $payment->allocatedAmount();
@endphp

<x-print::parties :parties="[[
    'title' => $isReceipt ? __('Received from') : __('Paid to'),
    'name' => $contact->name,
    'lines' => [
        $contact->tax_number ? __('Tax number').': '.$contact->tax_number : null,
        implode(' · ', array_filter([$contact->phone ?? $contact->mobile, $contact->email])),
        implode(', ', array_filter([$contact->address, $contact->city, $contact->country])),
    ],
]]" />

<x-print::totals :rows="[
    ['label' => __('Amount').' ('.$currency->code.')', 'value' => Format::money($payment->amount, $currency), 'emphasis' => true],
]" />

<x-print::amount-in-words :amount="$payment->amount" :currency="$currency->translate('name')" :decimals="$currency->decimal_places" />

<x-print::notes :text="$payment->description" :title="__('Description')" />

@if ($payment->allocations->isNotEmpty())
    <h2 class="text-start">{{ $isReceipt ? __('Invoices settled') : __('Bills settled') }}</h2>

    <table class="grid" dir="{{ $direction }}">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th>{{ __('Number') }}</th>
                <th>{{ __('Document date') }}</th>
                <th>{{ __('Allocated on') }}</th>
                <th class="num">{{ __('Document total') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td>{{ \App\Modules\Foundation\Printing\Bidi::isolate($allocation->invoice->number) }}</td>
                    <td>{{ Format::date($allocation->invoice->date) }}</td>
                    <td>{{ Format::date($allocation->date) }}</td>
                    <td class="num">{{ Format::money($allocation->invoice->total, $currency) }}</td>
                    <td class="num">{{ Format::money($allocation->amount, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <x-print::totals :rows="array_values(array_filter([
        ['label' => __('Allocated'), 'value' => Format::money($allocated, $currency)],
        $payment->status === DocumentStatus::Posted ? ['label' => __('Unallocated'), 'value' => Format::money($payment->unallocatedAmount(), $currency)] : null,
    ]))" />
@endif
