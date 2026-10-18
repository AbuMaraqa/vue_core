<?php

namespace App\Modules\Banking\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Banking\Models\BankTransfer;

/**
 * The printed transfer between two of the company's accounts: the amount
 * that left, the fees and the amount that arrived, with the sent amount in words.
 */
class BankTransferDocument extends FinancialDocument
{
    public function __construct(protected BankTransfer $transfer)
    {
        $transfer->loadMissing(['fromAccount.currency', 'toAccount.currency']);
    }

    public function title(): string
    {
        return __('Bank transfer');
    }

    public function number(): ?string
    {
        return $this->transfer->number;
    }

    public function status(): ?array
    {
        return $this->documentStatus($this->transfer->status);
    }

    public function details(): array
    {
        return [
            __('Date') => self::date($this->transfer->date),
            __('Reference') => $this->transfer->reference,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Accountant'), __('Approved by')];
    }

    public function filename(): string
    {
        return str_replace('/', '-', $this->transfer->number).'.pdf';
    }

    protected function view(): string
    {
        return 'banking::print.transfer';
    }

    protected function data(): array
    {
        return ['transfer' => $this->transfer];
    }
}
