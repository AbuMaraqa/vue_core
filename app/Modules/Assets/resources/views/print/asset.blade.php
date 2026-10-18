@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Foundation\Printing\Bidi')

<h2 class="text-start">{{ __('Depreciation') }}</h2>

<table class="grid" dir="{{ $direction }}">
    @foreach (array_chunk($depreciation, 2, true) as $pair)
        <tr>
            @foreach ($pair as $label => $value)
                <td class="muted text-start" style="width: 20%">{{ $label }}</td>
                <td class="text-start" style="width: 30%">{{ Bidi::isolate($value) }}</td>
            @endforeach
            @if (count($pair) === 1)
                <td></td>
                <td></td>
            @endif
        </tr>
    @endforeach
</table>

<x-print::totals :rows="[
    ['label' => __('Cost'), 'value' => Format::money($asset->cost, $baseCurrency)],
    ['label' => __('Accumulated depreciation'), 'value' => Format::money($asset->accumulated_depreciation, $baseCurrency)],
    ['label' => __('Book value').' ('.$baseCurrency->code.')', 'value' => Format::money($asset->bookValue(), $baseCurrency), 'emphasis' => true],
]" />

<h2 class="text-start">{{ __('Posted depreciation') }}</h2>

@if ($asset->depreciations->isEmpty())
    <p class="muted text-start">{{ __('Not depreciated yet') }}</p>
@else
    <table class="grid" dir="{{ $direction }}">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th>{{ __('From') }}</th>
                <th>{{ __('To') }}</th>
                <th class="num">{{ __('Months') }}</th>
                <th class="num">{{ __('Amount') }}</th>
                <th>{{ __('Run') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($asset->depreciations as $line)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td class="text-start">{{ Bidi::isolate(Format::date($line->from_date)) }}</td>
                    <td class="text-start">{{ Bidi::isolate(Format::date($line->to_date)) }}</td>
                    <td class="num">{{ $line->months }}</td>
                    <td class="num">{{ Format::money($line->amount, $baseCurrency) }}</td>
                    <td class="text-start">{{ $line->run ? Bidi::isolate($line->run->number) : __('On disposal') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-start">{{ __('Total') }}</td>
                <td class="num">{{ $asset->depreciations->sum('months') }}</td>
                <td class="num">{{ Format::money($postedTotal, $baseCurrency) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endif

<x-print::notes :text="$asset->notes" />
