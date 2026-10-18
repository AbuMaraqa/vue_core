<?php

namespace App\Modules\Invoicing\Actions\Invoices;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a posted invoice: its entries and stock movements are reversed, on
 * its own date while that period is open, else today. Payments and notes
 * applied to it must be removed first.
 */
class CancelInvoice
{
    public function __construct(
        protected Ledger $ledger,
        protected StockLedger $stock,
    ) {}

    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== DocumentStatus::Posted) {
                throw new AccountingException(__('Only posted documents can be cancelled.'));
            }

            if (Decimal::isPositive($invoice->paid_amount) || $invoice->allocations()->exists() || $invoice->appliedTo()->exists()) {
                throw new AccountingException(__('Remove the payments and notes applied to :number first.', ['number' => $invoice->number]));
            }

            $date = $this->ledger->openDateFor($invoice->date);

            $this->stock->reverse($invoice, $date);
            $this->ledger->reverseEntriesOf($invoice, $date, __('Cancellation of :number', ['number' => $invoice->number]));

            $invoice->update(['status' => DocumentStatus::Cancelled]);

            return $invoice;
        });
    }
}
