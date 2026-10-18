@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Accounting\Support\Decimal')
@php
    $employee = $payslip->employee;
    $isBank = $employee->payment_method === 'bank';
    $gross = Decimal::round(Decimal::of($payslip->basic)->plus($payslip->earnings_total));

    $earningRows = [
        ['name' => __('Basic salary'), 'amount' => $payslip->basic],
        ...$earnings->map(fn ($line): array => ['name' => $line->component->translate('name'), 'amount' => $line->amount])->all(),
    ];
    $deductionRows = $deductions->map(fn ($line): array => ['name' => $line->component->translate('name'), 'amount' => $line->amount])->all();
    $rowCount = max(count($earningRows), count($deductionRows));
    $divider = 'border-'.$start.': 0.3mm solid #d0d5dd';
@endphp

<x-print::parties :parties="[
    [
        'title' => __('Employee'),
        'name' => $employee->name,
        'lines' => [
            __('Code').': '.$employee->code,
            $employee->position,
            $payslip->costCenter ? __('Department').': '.$payslip->costCenter->label() : null,
            $employee->national_id ? __('National ID').': '.$employee->national_id : null,
        ],
    ],
    [
        'title' => __('Payment method'),
        'name' => $isBank ? __('Bank transfer') : __('Cash'),
        'lines' => $isBank ? [
            $employee->bank_name ? __('Bank name').': '.$employee->bank_name : null,
            $employee->iban ? __('IBAN').': '.$employee->iban : null,
        ] : [],
    ],
]" />

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 32%">{{ __('Earnings') }}</th>
            <th class="num" style="width: 18%">{{ __('Amount') }}</th>
            <th style="width: 32%; {{ $divider }}">{{ __('Deductions') }}</th>
            <th class="num" style="width: 18%">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @for ($row = 0; $row < $rowCount; $row++)
            <tr>
                <td>{{ $earningRows[$row]['name'] ?? '' }}</td>
                <td class="num">{{ isset($earningRows[$row]) ? Format::money($earningRows[$row]['amount'], $currency) : '' }}</td>
                <td style="{{ $divider }}">{{ $deductionRows[$row]['name'] ?? '' }}</td>
                <td class="num">{{ isset($deductionRows[$row]) ? Format::money($deductionRows[$row]['amount'], $currency) : '' }}</td>
            </tr>
        @endfor
    </tbody>
    <tfoot>
        <tr>
            <td>{{ __('Total earnings') }}</td>
            <td class="num">{{ Format::money($gross, $currency) }}</td>
            <td style="{{ $divider }}">{{ __('Total deductions') }}</td>
            <td class="num">{{ Format::money($payslip->deductions_total, $currency) }}</td>
        </tr>
    </tfoot>
</table>

<x-print::totals :rows="[
    ['label' => __('Gross pay'), 'value' => Format::money($gross, $currency)],
    ['label' => __('Deductions'), 'value' => Format::money($payslip->deductions_total, $currency)],
    ['label' => __('Net pay').' ('.$currency->code.')', 'value' => Format::money($payslip->net_pay, $currency), 'emphasis' => true],
]" />

<x-print::amount-in-words :amount="$payslip->net_pay" :currency="$currency->translate('name')" :decimals="$currency->decimal_places" />

@if ($contributions->isNotEmpty())
    <h2 class="text-start">{{ __('Employer contributions') }}</h2>
    <table class="grid" dir="{{ $direction }}" style="width: 50%">
        <tbody>
            @foreach ($contributions as $line)
                <tr>
                    <td>{{ $line->component->translate('name') }}</td>
                    <td class="num">{{ Format::money($line->amount, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="muted text-start" style="font-size: 8pt; margin-top: 1.5mm">{{ __('Paid by the company on top of the salary; not deducted from the net pay.') }}</div>
@endif
