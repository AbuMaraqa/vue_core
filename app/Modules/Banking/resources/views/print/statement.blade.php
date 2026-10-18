@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@php
    $currency = $bankAccount->currency;
@endphp

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 14%">{{ __('Date') }}</th>
            <th style="width: 18%">{{ __('Number') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num" style="width: 13%">{{ __('Money in') }}</th>
            <th class="num" style="width: 13%">{{ __('Money out') }}</th>
            <th class="num" style="width: 14%">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td></td>
            <td></td>
            <td class="strong">{{ __('Opening balance') }}</td>
            <td></td>
            <td></td>
            <td class="num strong">{{ Format::money($register['opening'], $currency) }}</td>
        </tr>
        @foreach ($register['lines'] as $line)
            <tr>
                <td style="white-space: nowrap"><span dir="ltr">{{ $line['date'] }}</span></td>
                <td style="white-space: nowrap">{{ \App\Modules\Foundation\Printing\Bidi::isolate($line['number']) }}</td>
                <td>
                    {{ $line['description'] }}
                    @if (filled($line['reference']))
                        <div class="muted" style="font-size: 8pt">{{ \App\Modules\Foundation\Printing\Bidi::isolate($line['reference']) }}</div>
                    @endif
                </td>
                <td class="num">{{ $line['in'] !== null ? Format::money($line['in'], $currency) : '' }}</td>
                <td class="num">{{ $line['out'] !== null ? Format::money($line['out'], $currency) : '' }}</td>
                <td class="num">{{ Format::money($line['balance'], $currency) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-start">{{ __('Closing balance') }}</td>
            <td class="num">{{ Format::money($register['total_in'], $currency) }}</td>
            <td class="num">{{ Format::money($register['total_out'], $currency) }}</td>
            <td class="num">{{ Format::money($register['closing'], $currency) }}</td>
        </tr>
    </tfoot>
</table>

@if ($register['truncated'])
    <div class="notice text-start">{{ __('Only the first lines are shown. Narrow the period or export the report.') }}</div>
@endif

<x-print::totals :rows="[
    ['label' => __('Opening balance'), 'value' => Format::money($register['opening'], $currency)],
    ['label' => __('Money in'), 'value' => Format::money($register['total_in'], $currency)],
    ['label' => __('Money out'), 'value' => Format::money($register['total_out'], $currency)],
    ['label' => __('Closing balance').' ('.$currency->code.')', 'value' => Format::money($register['closing'], $currency), 'emphasis' => true],
]" />
