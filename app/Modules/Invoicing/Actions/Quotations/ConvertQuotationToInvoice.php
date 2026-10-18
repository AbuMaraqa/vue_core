<?php

namespace App\Modules\Invoicing\Actions\Quotations;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Invoicing\Actions\Invoices\SaveInvoice;
use App\Modules\Invoicing\Data\InvoiceData;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Models\QuotationLine;
use Illuminate\Support\Facades\DB;

/**
 * Turn a quotation into a draft sales invoice dated today, with the same
 * lines, prices and taxes. The invoice is left as a draft so the user can
 * check the warehouse and post it; the quotation is marked accepted.
 */
class ConvertQuotationToInvoice
{
    public function __construct(
        protected SaveInvoice $saveInvoice,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(Quotation $quotation): Invoice
    {
        return DB::transaction(function () use ($quotation): Invoice {
            $quotation = Quotation::query()->with('lines.item')->lockForUpdate()->findOrFail($quotation->id);

            if (! $quotation->isConvertible()) {
                throw new AccountingException($quotation->isInvoiced()
                    ? __('This quotation was already converted to an invoice.')
                    : __('A rejected quotation cannot be invoiced. Mark it as sent again first.'));
            }

            $hasStockItems = $quotation->lines->contains(fn (QuotationLine $line): bool => $line->item?->type === ItemType::Stock);

            $invoice = $this->saveInvoice->handle(new Invoice, new InvoiceData(
                type: InvoiceType::SalesInvoice,
                contact_id: $quotation->contact_id,
                date: now()->toDateString(),
                currency_id: $quotation->currency_id,
                lines: array_values($quotation->lines->map(fn (QuotationLine $line): array => [
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    // A line without an item is sold to the default sales account.
                    'account_id' => $line->item_id === null ? $this->defaults->id(AccountRole::Sales) : null,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $line->unit_price,
                    'discount_percent' => (string) $line->discount_percent,
                    'tax_id' => $line->tax_id,
                ])->all()),
                reference: $quotation->reference ?? $quotation->number,
                warehouse_id: $hasStockItems
                    ? Warehouse::query()->where('is_active', true)->orderBy('code')->value('id')
                    : null,
                prices_include_tax: $quotation->prices_include_tax,
                notes: $quotation->notes,
            ));

            $quotation->update(['status' => QuotationStatus::Accepted, 'invoice_id' => $invoice->id]);

            return $invoice;
        });
    }
}
