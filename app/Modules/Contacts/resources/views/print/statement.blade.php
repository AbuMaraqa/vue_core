@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')
@php
    $isOwedToUs = (float) $statement['closing'] >= 0;
@endphp

<x-print::parties :parties="[[
    'title' => match (true) {
        $contact->is_customer && $contact->is_vendor => __('Contact'),
        $contact->is_vendor => __('Vendor'),
        default => __('Customer'),
    },
    'name' => $contact->name,
    'lines' => [
        $contact->contact_person,
        $contact->tax_number ? __('Tax number').': '.$contact->tax_number : null,
        implode(' · ', array_filter([$contact->phone ?? $contact->mobile, $contact->email])),
        implode(', ', array_filter([$contact->address, $contact->city, $contact->country])),
    ],
]]" />

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 13%">{{ __('Date') }}</th>
            <th style="width: 17%">{{ __('Number') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="3" class="strong">{{ __('Opening balance') }}</td>
            <td class="num"></td>
            <td class="num"></td>
            <td class="num strong">{{ Format::money($statement['opening'], $currency) }}</td>
        </tr>
        @forelse ($statement['lines'] as $line)
            <tr>
                <td style="white-space: nowrap">{{ $line['date'] }}</td>
                <td style="white-space: nowrap">{{ Bidi::isolate($line['number']) }}</td>
                <td>
                    {{ $line['description'] }}
                    @if ($line['reference'])
                        <div class="muted" style="font-size: 8pt">{{ __('Reference') }}: {{ Bidi::isolate($line['reference']) }}</div>
                    @endif
                    @if ($line['foreign'])
                        <div class="muted" style="font-size: 8pt">{{ Bidi::isolate($line['foreign']['currency'].' '.Format::money($line['foreign']['amount'], $line['foreign']['decimal_places'])) }}</div>
                    @endif
                </td>
                <td class="num">{{ (float) $line['debit'] != 0 ? Format::money($line['debit'], $currency) : '' }}</td>
                <td class="num">{{ (float) $line['credit'] != 0 ? Format::money($line['credit'], $currency) : '' }}</td>
                <td class="num">{{ Format::money($line['balance'], $currency) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="muted text-center">{{ __('No movements in this period.') }}</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">{{ __('Closing balance') }}</td>
            <td class="num">{{ Format::money($statement['total_debit'], $currency) }}</td>
            <td class="num">{{ Format::money($statement['total_credit'], $currency) }}</td>
            <td class="num">{{ Format::money($statement['closing'], $currency) }}</td>
        </tr>
    </tfoot>
</table>

@if ($statement['truncated'])
    <div class="notice text-start">{{ __('Only the first lines are shown. Narrow the period or export the report.') }}</div>
@endif

<x-print::totals :rows="[
    ['label' => __('Opening balance'), 'value' => Format::money($statement['opening'], $currency)],
    ['label' => __('Closing balance').' ('.$currency->code.')', 'value' => Format::money($statement['closing'], $currency), 'emphasis' => true],
    ['label' => $isOwedToUs ? __('Owed to us') : __('We owe them'), 'value' => Format::money(ltrim($statement['closing'], '-'), $currency)],
]" />
