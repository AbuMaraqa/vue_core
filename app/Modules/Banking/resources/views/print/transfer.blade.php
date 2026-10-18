@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Accounting\Support\Decimal')
@php
    $from = $transfer->fromAccount;
    $to = $transfer->toAccount;
    $fromCurrency = $from->currency;
    $toCurrency = $to->currency;
    $isCrossCurrency = $fromCurrency->id !== $toCurrency->id;

    $account = fn ($bankAccount): array => [
        $bankAccount->bank_name,
        $bankAccount->account_number ? __('Account number').': '.$bankAccount->account_number : null,
        $bankAccount->iban ? __('IBAN').': '.$bankAccount->iban : null,
    ];

    $rows = [['label' => __('Amount sent'), 'value' => Format::money($transfer->amount, $fromCurrency), 'currency' => $fromCurrency->code]];

    if (Decimal::isPositive($transfer->fees)) {
        $rows[] = ['label' => __('Bank fees'), 'value' => Format::money($transfer->fees, $fromCurrency), 'currency' => $fromCurrency->code];
    }

    $rows[] = ['label' => __('Amount received'), 'value' => Format::money($transfer->to_amount, $toCurrency), 'currency' => $toCurrency->code];
@endphp

<x-print::parties :parties="[
    ['title' => __('From account'), 'name' => $from->label(), 'lines' => $account($from)],
    ['title' => __('To account'), 'name' => $to->label(), 'lines' => $account($to)],
]" />

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th>{{ __('Description') }}</th>
            <th style="width: 15%">{{ __('Currency') }}</th>
            <th class="num" style="width: 30%">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['currency'] }}</td>
                <td class="num">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($isCrossCurrency && Decimal::isPositive($transfer->amount))
    <div class="muted text-end" style="font-size: 8.5pt; margin-top: 2mm">
        {{ __('Exchange rate') }}:
        <span dir="ltr">1 {{ $fromCurrency->code }} = {{ Format::quantity(Decimal::divide($transfer->to_amount, $transfer->amount, 6)) }} {{ $toCurrency->code }}</span>
    </div>
@endif

<x-print::amount-in-words :amount="$transfer->amount" :currency="$fromCurrency->translate('name')" :decimals="$fromCurrency->decimal_places" />

<x-print::notes :text="$transfer->description" :title="__('Description')" />
