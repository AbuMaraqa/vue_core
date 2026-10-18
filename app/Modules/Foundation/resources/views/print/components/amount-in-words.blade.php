{{--
    An amount written in words, as on receipts and vouchers.

    <x-print::amount-in-words :amount="$payment->amount" :currency="$payment->currency->translate('name')" :decimals="2" />
--}}
@props(['amount', 'currency', 'decimals' => 2])

<div class="words text-start">
    <span class="muted">{{ __('Amount in words') }}:</span>
    <strong>{{ \App\Modules\Foundation\Printing\AmountInWords::spell($amount, $currency, $decimals) }}</strong>
</div>
