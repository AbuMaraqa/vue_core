@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th style="width: 13%">{{ __('Code') }}</th>
            <th>{{ __('Item') }}</th>
            <th style="width: 9%">{{ __('Unit') }}</th>
            <th class="num">{{ __('Quantity in') }}</th>
            <th class="num">{{ __('Quantity out') }}</th>
            <th class="num">{{ __('Unit cost') }}</th>
            <th class="num">{{ __('Value') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($adjustment->lines as $line)
            @php($isIncrease = (float) $line->quantity > 0)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td class="text-start">{{ Bidi::isolate($line->item->code) }}</td>
                <td class="text-start">{{ $line->item->translate('name') }}</td>
                <td class="text-start muted">{{ $line->item->unit?->translate('name') }}</td>
                <td class="num">{{ $isIncrease ? Format::quantity($line->quantity) : '' }}</td>
                <td class="num">{{ $isIncrease ? '' : Format::quantity(ltrim($line->quantity, '-')) }}</td>
                <td class="num">{{ Format::money($line->unit_cost, 4) }}</td>
                <td class="num">{{ Format::money($line->value, $currency) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-start">{{ __('Total') }} ({{ $currency->code }})</td>
            <td class="num">{{ Format::quantity($totals['in']) }}</td>
            <td class="num">{{ Format::quantity($totals['out']) }}</td>
            <td></td>
            <td class="num">{{ Format::money($totals['value'], $currency) }}</td>
        </tr>
    </tfoot>
</table>

<x-print::notes :text="$adjustment->description" :title="__('Description')" />
