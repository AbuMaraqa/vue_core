@use('App\Modules\Accounting\Printing\FinancialDocument', 'Format')
@use('App\Modules\Banking\Enums\VoucherDirection')
@php
    $currency = $voucher->currency;
    $bank = $voucher->bankAccount;
    $contact = $voucher->contact;
    $isPayment = $voucher->direction === VoucherDirection::Out;
    $hasTax = (float) $voucher->tax_total > 0;
    $hasCostCenters = $voucher->lines->contains(fn ($line): bool => $line->costCenter !== null);
    $hasDescriptions = $voucher->lines->contains(fn ($line): bool => filled($line->description));

    $parties = [];

    if ($contact || filled($voucher->payee)) {
        $parties[] = [
            'title' => $isPayment ? __('Paid to') : __('Received from'),
            'name' => $contact?->name ?? $voucher->payee,
            'lines' => $contact ? [
                $contact->tax_number ? __('Tax number').': '.$contact->tax_number : null,
                implode(' · ', array_filter([$contact->phone ?? $contact->mobile, $contact->email])),
            ] : [],
        ];
    }

    $parties[] = [
        'title' => $isPayment ? __('Paid from') : __('Deposited to'),
        'name' => $bank->name,
        'lines' => [
            $bank->bank_name,
            $bank->account_number ? __('Account number').': '.$bank->account_number : null,
        ],
    ];

    $totals = [];

    if ($hasTax) {
        $totals[] = ['label' => __('Subtotal'), 'value' => Format::money($voucher->subtotal, $currency)];
        $totals[] = ['label' => __('Tax'), 'value' => Format::money($voucher->tax_total, $currency)];
    }

    $totals[] = ['label' => __('Total').' ('.$currency->code.')', 'value' => Format::money($voucher->total, $currency), 'emphasis' => true];
@endphp

<x-print::parties :parties="$parties" />

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th>{{ __('Account') }}</th>
            @if ($hasDescriptions)
                <th>{{ __('Description') }}</th>
            @endif
            @if ($hasCostCenters)
                <th>{{ __('Cost center') }}</th>
            @endif
            @if ($hasTax)
                <th class="num">{{ __('Tax') }}</th>
            @endif
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($voucher->lines as $line)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td>{{ $line->account->label() }}</td>
                @if ($hasDescriptions)
                    <td>{{ $line->description }}</td>
                @endif
                @if ($hasCostCenters)
                    <td>{{ $line->costCenter?->label() }}</td>
                @endif
                @if ($hasTax)
                    <td class="num">
                        @if ($line->tax)
                            {{ Format::money($line->tax_amount, $currency) }}
                            <div class="muted" style="font-size: 8pt">{{ Format::percent($line->tax->rate) }}</div>
                        @endif
                    </td>
                @endif
                <td class="num">{{ Format::money($line->amount, $currency) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<x-print::totals :rows="$totals" />

<x-print::amount-in-words :amount="$voucher->total" :currency="$currency->translate('name')" :decimals="$currency->decimal_places" />

<x-print::notes :text="$voucher->description" :title="__('Description')" />
