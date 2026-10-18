<?php

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Inventory\Support\StockLedger;

/**
 * Invoices and payments get their new rate; the stock cost recorded on
 * invoice lines is a base amount.
 */
class ConvertInvoicingToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $conversion = $event->conversion;

        $conversion->convertExchangeRates('invoices');
        $conversion->convertExchangeRates('payments');
        $conversion->convertColumns('invoice_lines', ['unit_cost' => StockLedger::COST_SCALE]);
    }
}
