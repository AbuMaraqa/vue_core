<?php

namespace App\Modules\Invoicing\Actions\Invoices;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

/**
 * Post a draft invoice: number it, post its entry, move its stock and post
 * the cost of that stock.
 *
 * The entry, in the invoice's currency (sales invoice shown; the other
 * documents swap sides):
 *
 *   Dr customer receivable   total
 *   Cr each line's account   subtotal (after discount)
 *   Cr output tax            tax
 *
 * Stock, at cost, in a second entry in the base currency:
 *
 * - sales invoice: stock leaves at average cost; Dr cost of sales, Cr inventory.
 * - credit note: stock returns at the cost it left with on the original
 *   invoice (else the current average); Dr inventory, Cr cost of sales.
 * - purchase bill: stock arrives at the line's value in the base currency,
 *   which the main entry already debited to inventory.
 * - debit note: stock leaves at average cost; the main entry credited
 *   inventory with the returned value, so the difference goes to cost of
 *   sales.
 */
class PostInvoice
{
    public function __construct(
        protected Ledger $ledger,
        protected DocumentNumbers $numbers,
        protected StockLedger $stock,
    ) {}

    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->with(['lines.item.category', 'lines.tax', 'contact', 'currency'])->findOrFail($invoice->id);

            if (! $invoice->isDraft()) {
                throw new AccountingException(__('Only drafts can be posted.'));
            }

            if ($invoice->lines->isEmpty() || ! Decimal::isPositive($invoice->total)) {
                throw new AccountingException(__('The document has no amount to post.'));
            }

            $this->ledger->ensureDateIsOpen($invoice->date);

            $invoice->number = $this->numbers->next($invoice->type->documentType(), $invoice->date);

            $this->postEntry($invoice);
            $this->moveStock($invoice);

            $invoice->forceFill(['status' => DocumentStatus::Posted, 'posted_at' => now()])->save();

            return $invoice;
        });
    }

    protected function postEntry(Invoice $invoice): void
    {
        $type = $invoice->type;
        $contactSign = $type->debitsContact() ? 1 : -1;
        $contactAccount = $type->isSales() ? $invoice->contact->receivableAccountId() : $invoice->contact->payableAccountId();

        $entry = JournalEntryBuilder::make($invoice->date, "{$type->label()} {$invoice->number} — {$invoice->contact->name}")
            ->currency($invoice->currency, (string) $invoice->exchange_rate)
            ->reference($invoice->reference ?? $invoice->number)
            ->source($invoice)
            ->debit($contactAccount, Decimal::of($invoice->total)->multipliedBy($contactSign), partner: $invoice->contact);

        foreach ($invoice->lines as $line) {
            $entry->debit($line->account_id, Decimal::of($line->subtotal)->multipliedBy(-$contactSign), $line->description, $invoice->contact, $line->cost_center_id);

            if ($line->tax !== null && Decimal::isPositive($line->tax_amount)) {
                $taxAccount = ($type->isSales() ? $line->tax->sales_account_id : $line->tax->purchase_account_id)
                    ?? throw new AccountingException(__('Choose the accounts of the tax :tax first.', ['tax' => $line->tax->translate('name')]));

                $entry->debit($taxAccount, Decimal::of($line->tax_amount)->multipliedBy(-$contactSign), $line->tax->translate('name'), $invoice->contact);
            }
        }

        $entry->post();
    }

    protected function moveStock(Invoice $invoice): void
    {
        $stockLines = $invoice->lines->filter(fn (InvoiceLine $line): bool => $line->item?->isStock() ?? false);

        if ($stockLines->isEmpty() || $invoice->is_opening) {
            return;
        }

        $warehouseId = $invoice->warehouse_id ?? throw new AccountingException(__('Choose the warehouse of the stock items.'));
        $cost = JournalEntryBuilder::make($invoice->date, __('Cost of :document :number', ['document' => $invoice->type->label(), 'number' => $invoice->number]))
            ->reference($invoice->number)
            ->source($invoice);

        foreach ($stockLines as $line) {
            $item = $line->item;
            $baseValue = Decimal::multiply($line->subtotal, (string) $invoice->exchange_rate);

            $movement = match ($invoice->type) {
                InvoiceType::SalesInvoice => $this->stock->issue($item, $warehouseId, $line->quantity, $invoice->date, StockMovementType::Sale, $invoice, $invoice->number),
                InvoiceType::PurchaseDebitNote => $this->stock->issue($item, $warehouseId, $line->quantity, $invoice->date, StockMovementType::PurchaseReturn, $invoice, $invoice->number),
                InvoiceType::PurchaseBill => $this->stock->receive($item, $warehouseId, $line->quantity, Decimal::divide($baseValue, $line->quantity, StockLedger::COST_SCALE), $invoice->date, StockMovementType::Purchase, $invoice, $invoice->number, $baseValue),
                InvoiceType::SalesCreditNote => $this->stock->receive($item, $warehouseId, $line->quantity, $this->returnCost($invoice, $line, $warehouseId), $invoice->date, StockMovementType::SaleReturn, $invoice, $invoice->number),
            };

            $line->update(['unit_cost' => $movement->unit_cost]);
            $stockValue = Decimal::of($movement->value)->abs();

            match ($invoice->type) {
                InvoiceType::SalesInvoice => $cost->debit($item->costOfSalesAccountId(), $stockValue)->credit($item->inventoryAccountId(), $stockValue),
                InvoiceType::SalesCreditNote => $cost->debit($item->inventoryAccountId(), $stockValue)->credit($item->costOfSalesAccountId(), $stockValue),
                InvoiceType::PurchaseDebitNote => $cost
                    ->debit($item->inventoryAccountId(), Decimal::of($baseValue)->minus($stockValue))
                    ->credit($item->costOfSalesAccountId(), Decimal::of($baseValue)->minus($stockValue)),
                InvoiceType::PurchaseBill => null,
            };
        }

        if ($cost->hasLines()) {
            $cost->post();
        }
    }

    /**
     * Returned goods come back at the cost they left with on the original
     * invoice, else the warehouse's average, else the purchase price.
     */
    protected function returnCost(Invoice $invoice, InvoiceLine $line, int $warehouseId): string
    {
        $original = $invoice->original_invoice_id === null ? null : InvoiceLine::query()
            ->where('invoice_id', $invoice->original_invoice_id)
            ->where('item_id', $line->item_id)
            ->whereNotNull('unit_cost')
            ->value('unit_cost');

        if ($original !== null) {
            return (string) $original;
        }

        $average = StockLevel::query()->where('item_id', $line->item_id)->where('warehouse_id', $warehouseId)->value('average_cost');

        return Decimal::isPositive((string) $average) ? (string) $average : (string) $line->item?->purchase_price;
    }
}
