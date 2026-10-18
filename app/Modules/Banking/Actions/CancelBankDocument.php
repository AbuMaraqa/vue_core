<?php

namespace App\Modules\Banking\Actions;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Banking\Models\BankTransfer;
use App\Modules\Banking\Models\BankVoucher;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a posted transfer or voucher: its entries are reversed, on its own
 * date while that period is open, else today.
 */
class CancelBankDocument
{
    public function __construct(protected Ledger $ledger) {}

    public function handle(BankTransfer|BankVoucher $document): BankTransfer|BankVoucher
    {
        return DB::transaction(function () use ($document): BankTransfer|BankVoucher {
            $document = $document->newQuery()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($document->status !== DocumentStatus::Posted) {
                throw new AccountingException(__('Only posted documents can be cancelled.'));
            }

            $this->ledger->reverseEntriesOf(
                $document,
                $this->ledger->openDateFor($document->date),
                __('Cancellation of :number', ['number' => $document->number]),
            );

            $document->update(['status' => DocumentStatus::Cancelled]);

            return $document;
        });
    }
}
