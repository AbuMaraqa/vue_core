@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')

@if ($item->isStock())
    <h2 class="text-start">{{ __('Stock by warehouse') }}</h2>

    @if ($levels->isEmpty())
        <p class="muted text-start">{{ __('This item has no stock yet.') }}</p>
    @else
        <table class="grid" dir="{{ $direction }}">
            <thead>
                <tr>
                    <th>{{ __('Warehouse') }}</th>
                    <th class="num">{{ __('Quantity') }}</th>
                    <th class="num">{{ __('Average cost') }}</th>
                    <th class="num">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($levels as $level)
                    <tr>
                        <td class="text-start">{{ $level->warehouse->label() }}</td>
                        <td class="num">{{ Format::quantity($level->quantity) }} {{ $item->unit?->translate('name') }}</td>
                        <td class="num">{{ Format::money($level->average_cost, 4) }}</td>
                        <td class="num">{{ Format::money($level->value, $currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-start">{{ __('Total') }} ({{ $currency->code }})</td>
                    <td class="num">{{ Format::quantity($totals['quantity']) }} {{ $item->unit?->translate('name') }}</td>
                    <td></td>
                    <td class="num">{{ Format::money($totals['value'], $currency) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2 class="text-start">{{ __('Recent movements') }}</h2>

    @if ($movements->isEmpty())
        <p class="muted text-start">{{ __('No stock movements yet.') }}</p>
    @else
        <table class="grid" dir="{{ $direction }}">
            <thead>
                <tr>
                    <th style="width: 13%">{{ __('Date') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Document') }}</th>
                    <th>{{ __('Warehouse') }}</th>
                    <th class="num">{{ __('Quantity') }}</th>
                    <th class="num">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td class="text-start">{{ Bidi::isolate(Format::date($movement->date)) }}</td>
                        <td class="text-start">{{ $movement->type->label() }}</td>
                        <td class="text-start">{{ Bidi::isolate($movement->source?->getAttribute('number')) }}</td>
                        <td class="text-start">{{ $movement->warehouse->label() }}</td>
                        <td class="num">{{ (float) $movement->quantity > 0 ? '+' : '' }}{{ Format::quantity($movement->quantity) }}</td>
                        <td class="num">{{ Format::money($movement->value, $currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

<x-print::notes :text="$item->description" :title="__('Description')" />
