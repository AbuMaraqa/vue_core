@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@php
    $currency = $quotation->currency;
    $contact = $quotation->contact;
    $totals = [['label' => __('Subtotal'), 'value' => Format::money($quotation->subtotal, $currency)]];

    if ((float) $quotation->discount_total > 0) {
        $totals[] = ['label' => __('Discount'), 'value' => Format::money($quotation->discount_total, $currency)];
    }

    $totals[] = ['label' => __('Tax'), 'value' => Format::money($quotation->tax_total, $currency)];
    $totals[] = ['label' => __('Total').' ('.$currency->code.')', 'value' => Format::money($quotation->total, $currency), 'emphasis' => true];
@endphp

<x-print::parties :parties="[[
    'title' => __('Quotation for'),
    'name' => $contact->name,
    'lines' => [
        $contact->tax_number ? __('Tax number').': '.$contact->tax_number : null,
        implode(' · ', array_filter([$contact->phone ?? $contact->mobile, $contact->email])),
        implode(', ', array_filter([$contact->address, $contact->city, $contact->country])),
    ],
]]" />

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Quantity') }}</th>
            <th class="num">{{ __('Price') }}</th>
            <th class="num">{{ __('Discount') }}</th>
            <th class="num">{{ __('Tax') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($quotation->lines as $line)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td>
                    {{ $line->description }}
                    @if ($line->item)
                        <div class="muted" style="font-size: 8pt">{{ $line->item->code }}</div>
                    @endif
                </td>
                <td class="num">{{ Format::quantity($line->quantity) }} {{ $line->item?->unit?->translate('name') }}</td>
                <td class="num">{{ Format::money($line->unit_price, $currency) }}</td>
                <td class="num">{{ Format::percent($line->discount_percent) }}</td>
                <td class="num">{{ Format::percent($line->tax_rate) }}</td>
                <td class="num">{{ Format::money($line->subtotal, $currency) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<x-print::totals :rows="$totals" />

<x-print::amount-in-words :amount="$quotation->total" :currency="$currency->translate('name')" :decimals="$currency->decimal_places" />

<x-print::notes :text="$quotation->notes" />
