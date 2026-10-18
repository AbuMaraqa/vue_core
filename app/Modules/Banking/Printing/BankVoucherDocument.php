<?php

namespace App\Modules\Banking\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Banking\Models\BankVoucher;

/**
 * The printed payment or receipt voucher: the bank or cash account, who was
 * paid or who paid, the accounts it was split over and the total in words.
 */
class BankVoucherDocument extends FinancialDocument
{
    public function __construct(protected BankVoucher $voucher)
    {
        $voucher->loadMissing(['bankAccount.currency', 'contact', 'currency', 'lines.account', 'lines.costCenter', 'lines.tax']);
    }

    public function title(): string
    {
        return $this->voucher->direction->label();
    }

    public function number(): ?string
    {
        return $this->voucher->number;
    }

    public function status(): ?array
    {
        return $this->documentStatus($this->voucher->status);
    }

    public function details(): array
    {
        $isForeign = (float) $this->voucher->exchange_rate !== 1.0;

        return [
            __('Date') => self::date($this->voucher->date),
            __('Bank account') => $this->voucher->bankAccount->label(),
            __('Currency') => $this->voucher->currency->code,
            __('Exchange rate') => $isForeign ? self::quantity($this->voucher->exchange_rate) : null,
            __('Reference') => $this->voucher->reference,
        ];
    }

    public function signatures(): array
    {
        return $this->voucher->direction === VoucherDirection::Out
            ? [__('Prepared by'), __('Accountant'), __('Received by')]
            : [__('Prepared by'), __('Accountant'), __('Cashier')];
    }

    public function filename(): string
    {
        return str_replace('/', '-', $this->voucher->number).'.pdf';
    }

    protected function view(): string
    {
        return 'banking::print.voucher';
    }

    protected function data(): array
    {
        return ['voucher' => $this->voucher];
    }
}
