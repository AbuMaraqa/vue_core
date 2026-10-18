@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Accounting\Support\Decimal')
@use('App\Modules\Foundation\Printing\Bidi')
@php
    $sum = fn (string $column): string => Decimal::round(Decimal::sum($payslips->pluck($column)));
@endphp

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 4%">#</th>
            <th style="width: 7%">{{ __('Code') }}</th>
            <th>{{ __('Employee') }}</th>
            <th>{{ __('Department') }}</th>
            <th class="num">{{ __('Days') }}</th>
            <th class="num">{{ __('Basic') }}</th>
            <th class="num">{{ __('Earnings') }}</th>
            <th class="num">{{ __('Deductions') }}</th>
            <th class="num">{{ __('Employer') }}</th>
            <th class="num">{{ __('Net pay') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($payslips as $payslip)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td>{{ Bidi::isolate($payslip->employee->code) }}</td>
                <td>
                    {{ $payslip->employee->name }}
                    @if ($payslip->employee->position)
                        <div class="muted" style="font-size: 8pt">{{ $payslip->employee->position }}</div>
                    @endif
                </td>
                <td>{{ $payslip->costCenter?->translate('name') }}</td>
                <td class="num">{{ $payslip->days_worked }}/{{ $payslip->days_in_period }}</td>
                <td class="num">{{ Format::money($payslip->basic, $currency) }}</td>
                <td class="num">{{ Format::money($payslip->earnings_total, $currency) }}</td>
                <td class="num">{{ Format::money($payslip->deductions_total, $currency) }}</td>
                <td class="num">{{ Format::money($payslip->employer_total, $currency) }}</td>
                <td class="num strong">{{ Format::money($payslip->net_pay, $currency) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-start">{{ __('Total') }} ({{ $currency->code }})</td>
            <td class="num">{{ Format::money($sum('basic'), $currency) }}</td>
            <td class="num">{{ Format::money($sum('earnings_total'), $currency) }}</td>
            <td class="num">{{ Format::money($sum('deductions_total'), $currency) }}</td>
            <td class="num">{{ Format::money($sum('employer_total'), $currency) }}</td>
            <td class="num">{{ Format::money($sum('net_pay'), $currency) }}</td>
        </tr>
    </tfoot>
</table>

<x-print::totals :rows="[
    ['label' => __('Gross pay'), 'value' => Format::money($run->gross_total, $currency)],
    ['label' => __('Deductions'), 'value' => Format::money($run->deductions_total, $currency)],
    ['label' => __('Employer contributions'), 'value' => Format::money($run->employer_total, $currency)],
    ['label' => __('Net pay').' ('.$currency->code.')', 'value' => Format::money($run->net_total, $currency), 'emphasis' => true],
]" />

<x-print::amount-in-words :amount="$run->net_total" :currency="$currency->translate('name')" :decimals="$currency->decimal_places" />

<x-print::notes :text="$run->notes" />
