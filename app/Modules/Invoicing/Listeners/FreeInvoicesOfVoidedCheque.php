<?php

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Banking\Events\ChequeVoided;
use App\Modules\Invoicing\Models\Payment;
use App\Modules\Invoicing\Support\Allocator;

/**
 * A payment whose cheque bounced or was cancelled no longer settles its
 * invoices: they are open again. The cheque's own entry already put the
 * amount back on the contact.
 */
class FreeInvoicesOfVoidedCheque
{
    public function __construct(protected Allocator $allocator) {}

    public function handle(ChequeVoided $event): void
    {
        $payments = Payment::query()->with('allocations')->where('cheque_id', $event->cheque->id)->get();

        foreach ($payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                $this->allocator->remove($allocation);
            }
        }
    }
}
