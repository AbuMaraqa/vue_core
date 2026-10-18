<?php

namespace App\Modules\Invoicing\Actions\Payments;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Invoicing\Models\Payment;
use App\Modules\Invoicing\Support\Allocator;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a receipt or payment: its allocations are removed and its entry
 * reversed. A payment by cheque is undone through the cheque instead
 * (returned or cancelled), so the cheque register stays right.
 */
class CancelPayment
{
    public function __construct(
        protected Ledger $ledger,
        protected Allocator $allocator,
    ) {}

    public function handle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $payment = Payment::query()->lockForUpdate()->with('allocations')->findOrFail($payment->id);

            if ($payment->status !== DocumentStatus::Posted) {
                throw new AccountingException(__('Only posted documents can be cancelled.'));
            }

            if ($payment->cheque_id !== null) {
                throw new AccountingException(__('This payment was made by cheque. Return or cancel the cheque instead.'));
            }

            foreach ($payment->allocations as $allocation) {
                $this->allocator->remove($allocation);
            }

            $this->ledger->reverseEntriesOf($payment, $this->ledger->openDateFor($payment->date), __('Cancellation of :number', ['number' => $payment->number]));

            $payment->update(['status' => DocumentStatus::Cancelled]);

            return $payment;
        });
    }
}
