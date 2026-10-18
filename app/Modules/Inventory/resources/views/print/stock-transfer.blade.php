@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th style="width: 16%">{{ __('Code') }}</th>
            <th>{{ __('Item') }}</th>
            <th style="width: 12%">{{ __('Unit') }}</th>
            <th class="num" style="width: 15%">{{ __('Quantity') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transfer->lines as $line)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td class="text-start">{{ Bidi::isolate($line->item->code) }}</td>
                <td class="text-start">{{ $line->item->translate('name') }}</td>
                <td class="text-start muted">{{ $line->item->unit?->translate('name') }}</td>
                <td class="num">{{ Format::quantity($line->quantity) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-start">{{ __('Total') }}</td>
            <td class="num">{{ Format::quantity($totalQuantity) }}</td>
        </tr>
    </tfoot>
</table>

<x-print::notes :text="$transfer->description" :title="__('Description')" />
