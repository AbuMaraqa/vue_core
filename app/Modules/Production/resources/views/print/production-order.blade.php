@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Accounting\Support\Decimal')
@use('App\Modules\Foundation\Printing\Bidi')
@php
    $item = $order->item;
    $unit = $item->unit?->translate('name');
    $contact = $order->contact;

    $parties = [[
        'title' => __('Product'),
        'name' => $item->translate('name'),
        'lines' => [
            __('Code').': '.$item->code,
            ($isCompleted ? __('Planned quantity') : __('Quantity to produce')).': '.Format::quantity($order->quantity).' '.$unit,
            $isCompleted ? __('Produced quantity').': '.Format::quantity($order->produced_quantity).' '.$unit : null,
        ],
    ]];

    if ($contact) {
        $parties[] = [
            'title' => __('Customer'),
            'name' => $contact->name,
            'lines' => [
                implode(' · ', array_filter([$contact->phone ?? $contact->mobile, $contact->email])),
                implode(', ', array_filter([$contact->address, $contact->city, $contact->country])),
            ],
        ];
    }

    $materialsValue = Decimal::round(Decimal::sum($order->materials->pluck($isCompleted ? 'value' : 'issued_value')));
    $costsTotal = Decimal::round(Decimal::sum($order->costs->pluck('amount')));
@endphp

<x-print::parties :parties="$parties" />

<h2 class="text-start">{{ __('Materials') }}</h2>
<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th>{{ __('Material') }}</th>
            <th class="num">{{ $isStarted ? __('Issued quantity') : __('Quantity') }}</th>
            @if ($isCompleted)
                <th class="num">{{ __('Quantity used') }}</th>
            @endif
            @if ($isStarted)
                <th class="num">{{ __('Unit cost') }}</th>
                <th class="num">{{ __('Value') }}</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($order->materials as $material)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td>
                    {{ $material->item->translate('name') }}
                    <div class="muted" style="font-size: 8pt">{{ Bidi::isolate($material->item->code) }}</div>
                </td>
                <td class="num">{{ Format::quantity($material->quantity) }} {{ $material->item->unit?->translate('name') }}</td>
                @if ($isCompleted)
                    <td class="num">{{ Format::quantity($material->consumed_quantity) }}</td>
                @endif
                @if ($isStarted)
                    <td class="num">{{ Format::money($material->unit_cost, 4) }}</td>
                    <td class="num">{{ Format::money($isCompleted ? $material->value : $material->issued_value, $currency) }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
    @if ($isStarted)
        <tfoot>
            <tr>
                <td colspan="{{ $isCompleted ? 5 : 4 }}" class="text-start">{{ __('Total') }} ({{ $currency->code }})</td>
                <td class="num">{{ Format::money($materialsValue, $currency) }}</td>
            </tr>
        </tfoot>
    @endif
</table>

@if ($order->costs->isNotEmpty())
    <h2 class="text-start">{{ __('Other costs') }}</h2>
    <table class="grid" dir="{{ $direction }}">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th>{{ __('Description') }}</th>
                <th>{{ __('Account') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->costs as $cost)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td>{{ $cost->description }}</td>
                    <td>{{ $cost->account->label() }}</td>
                    <td class="num">{{ Format::money($cost->amount, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-start">{{ __('Total') }} ({{ $currency->code }})</td>
                <td class="num">{{ Format::money($costsTotal, $currency) }}</td>
            </tr>
        </tfoot>
    </table>
@endif

@if ($isCompleted)
    <x-print::totals :rows="[
        ['label' => __('Materials cost'), 'value' => Format::money($order->materials_cost, $currency)],
        ['label' => __('Other costs'), 'value' => Format::money($order->other_costs, $currency)],
        ['label' => __('Total cost').' ('.$currency->code.')', 'value' => Format::money($order->total_cost, $currency), 'emphasis' => true],
        ['label' => __('Unit cost'), 'value' => Format::money($order->unit_cost, $currency)],
    ]" />
@endif

<x-print::notes :text="$order->description" :title="__('Description')" />
