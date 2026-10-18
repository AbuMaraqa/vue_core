<?php

namespace App\Modules\Invoicing\Actions\Quotations;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Quotation;

/**
 * Record the customer's answer: the quotation was sent, accepted or
 * rejected. Only the moves listed by QuotationStatus::transitions() are
 * allowed.
 */
class ChangeQuotationStatus
{
    public function handle(Quotation $quotation, QuotationStatus $status): Quotation
    {
        if ($quotation->isInvoiced()) {
            throw new AccountingException(__('This quotation was already converted to an invoice.'));
        }

        if (! in_array($status, $quotation->status->transitions(), true)) {
            throw new AccountingException(__('A :from quotation cannot be marked :to.', [
                'from' => $quotation->status->label(),
                'to' => $status->label(),
            ]));
        }

        $quotation->update(['status' => $status]);

        return $quotation;
    }
}
