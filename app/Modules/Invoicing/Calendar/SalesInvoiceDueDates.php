<?php

namespace App\Modules\Invoicing\Calendar;

use App\Modules\Invoicing\Enums\InvoiceType;

/**
 * When customers should pay their invoices.
 */
class SalesInvoiceDueDates extends InvoiceDueDates
{
    public function key(): string
    {
        return 'sales_invoice_due';
    }

    public function label(): string
    {
        return __('Invoices to collect');
    }

    public function color(): string
    {
        return 'success';
    }

    protected function invoiceType(): InvoiceType
    {
        return InvoiceType::SalesInvoice;
    }
}
