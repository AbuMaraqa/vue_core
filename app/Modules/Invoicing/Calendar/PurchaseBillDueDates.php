<?php

namespace App\Modules\Invoicing\Calendar;

use App\Modules\Invoicing\Enums\InvoiceType;

/**
 * When the company should pay its vendors' bills.
 */
class PurchaseBillDueDates extends InvoiceDueDates
{
    public function key(): string
    {
        return 'purchase_bill_due';
    }

    public function label(): string
    {
        return __('Bills to pay');
    }

    public function color(): string
    {
        return 'orange';
    }

    protected function invoiceType(): InvoiceType
    {
        return InvoiceType::PurchaseBill;
    }
}
