@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')
@php
    $currency = $entry->currency;
@endphp

@if (filled($entry->description))
    <div class="box text-start" style="margin-bottom: 4mm">
        <div class="box-title">{{ __('Description') }}</div>
        {!! nl2br(e($entry->description)) !!}
    </div>
@endif

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 4%">#</th>
            <th style="width: 10%">{{ __('Code') }}</th>
            <th>{{ __('Account') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Cost center') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            @if ($isForeign)
                <th class="num">{{ __('Base debit') }}</th>
                <th class="num">{{ __('Base credit') }}</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($entry->lines as $line)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td class="text-start">{{ Bidi::isolate($line->account->code) }}</td>
                <td>{{ $line->account->translate('name') }}</td>
                <td>{{ $line->description }}</td>
                <td>{{ $line->costCenter?->translate('name') }}</td>
                <td class="num">{{ (float) $line->debit ? Format::money($line->debit, $currency) : '' }}</td>
                <td class="num">{{ (float) $line->credit ? Format::money($line->credit, $currency) : '' }}</td>
                @if ($isForeign)
                    <td class="num">{{ (float) $line->base_debit ? Format::money($line->base_debit, $baseCurrency) : '' }}</td>
                    <td class="num">{{ (float) $line->base_credit ? Format::money($line->base_credit, $baseCurrency) : '' }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-start">{{ __('Total') }} ({{ $currency->code }})</td>
            <td class="num">{{ Format::money($totals['debit'], $currency) }}</td>
            <td class="num">{{ Format::money($totals['credit'], $currency) }}</td>
            @if ($isForeign)
                <td class="num">{{ Format::money($totals['base_debit'], $baseCurrency) }}</td>
                <td class="num">{{ Format::money($totals['base_credit'], $baseCurrency) }}</td>
            @endif
        </tr>
    </tfoot>
</table>

<x-print::amount-in-words :amount="$words['amount']" :currency="$words['currency']->translate('name')" :decimals="$words['currency']->decimal_places" />
