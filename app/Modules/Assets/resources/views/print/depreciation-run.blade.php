@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th style="width: 13%">{{ __('Number') }}</th>
            <th>{{ __('Asset') }}</th>
            <th>{{ __('Category') }}</th>
            <th>{{ __('From') }}</th>
            <th>{{ __('To') }}</th>
            <th class="num">{{ __('Months') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($run->depreciations as $line)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td class="text-start">{{ Bidi::isolate($line->asset->number) }}</td>
                <td>{{ $line->asset->name }}</td>
                <td>{{ $line->asset->category->translate('name') }}</td>
                <td class="text-start">{{ Bidi::isolate(Format::date($line->from_date)) }}</td>
                <td class="text-start">{{ Bidi::isolate(Format::date($line->to_date)) }}</td>
                <td class="num">{{ $line->months }}</td>
                <td class="num">{{ Format::money($line->amount, $baseCurrency) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="text-start">{{ __('Total') }} ({{ $baseCurrency->code }})</td>
            <td class="num">{{ Format::money($run->total, $baseCurrency) }}</td>
        </tr>
    </tfoot>
</table>
