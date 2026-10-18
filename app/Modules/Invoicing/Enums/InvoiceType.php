<?php

namespace App\Modules\Invoicing\Enums;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Foundation\Concerns\EnumOptions;
use Illuminate\Http\Request;

/**
 * The four documents of the sales and purchases cycles. A credit note
 * reverses a sale (a customer return or a price correction); a debit note
 * reverses a purchase.
 *
 * Each knows its side of the contact's account: sales invoices and debit
 * notes increase what the contact owes us (a debit), credit notes and bills
 * increase what we owe them (a credit).
 */
enum InvoiceType: string
{
    use EnumOptions;

    case SalesInvoice = 'sales_invoice';
    case SalesCreditNote = 'sales_credit_note';
    case PurchaseBill = 'purchase_bill';
    case PurchaseDebitNote = 'purchase_debit_note';

    public function label(): string
    {
        return match ($this) {
            self::SalesInvoice => __('Sales invoice'),
            self::SalesCreditNote => __('Credit note'),
            self::PurchaseBill => __('Purchase bill'),
            self::PurchaseDebitNote => __('Debit note'),
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::SalesInvoice => __('Sales Invoices'),
            self::SalesCreditNote => __('Credit Notes'),
            self::PurchaseBill => __('Purchase Bills'),
            self::PurchaseDebitNote => __('Debit Notes'),
        };
    }

    public function isSales(): bool
    {
        return $this === self::SalesInvoice || $this === self::SalesCreditNote;
    }

    /**
     * Credit and debit notes undo part of an invoice or bill.
     */
    public function isReturn(): bool
    {
        return $this === self::SalesCreditNote || $this === self::PurchaseDebitNote;
    }

    /**
     * The document a note reverses, or the note of a document.
     */
    public function counterpart(): self
    {
        return match ($this) {
            self::SalesInvoice => self::SalesCreditNote,
            self::SalesCreditNote => self::SalesInvoice,
            self::PurchaseBill => self::PurchaseDebitNote,
            self::PurchaseDebitNote => self::PurchaseBill,
        };
    }

    /**
     * Whether the document debits the contact's account (they owe us more).
     */
    public function debitsContact(): bool
    {
        return $this === self::SalesInvoice || $this === self::PurchaseDebitNote;
    }

    /**
     * Whether its stock items come into the warehouse (otherwise they leave).
     */
    public function receivesStock(): bool
    {
        return $this === self::SalesCreditNote || $this === self::PurchaseBill;
    }

    /**
     * The payment that settles it: a receipt for a sales invoice, a vendor
     * payment for a bill. Notes are settled by applying them instead.
     */
    public function paymentType(): ?PaymentType
    {
        return match ($this) {
            self::SalesInvoice => PaymentType::Receipt,
            self::PurchaseBill => PaymentType::Payment,
            default => null,
        };
    }

    public function documentType(): DocumentType
    {
        return match ($this) {
            self::SalesInvoice => DocumentType::SalesInvoice,
            self::SalesCreditNote => DocumentType::SalesCreditNote,
            self::PurchaseBill => DocumentType::PurchaseBill,
            self::PurchaseDebitNote => DocumentType::PurchaseDebitNote,
        };
    }

    public function taxScope(): TaxScope
    {
        return $this->isSales() ? TaxScope::Sales : TaxScope::Purchases;
    }

    /**
     * The first part of its permissions: `Sales Invoice Create`...
     */
    public function permissionPrefix(): string
    {
        return $this->isSales() ? 'Sales Invoice' : 'Purchase Bill';
    }

    /**
     * The name prefix of its routes, e.g. `admin.sales.invoices`.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::SalesInvoice => 'admin.sales.invoices',
            self::SalesCreditNote => 'admin.sales.credit-notes',
            self::PurchaseBill => 'admin.purchases.bills',
            self::PurchaseDebitNote => 'admin.purchases.debit-notes',
        };
    }

    /**
     * Which contacts it is made for: `customers` or `vendors`.
     */
    public function contactType(): string
    {
        return $this->isSales() ? 'customers' : 'vendors';
    }

    /**
     * The type of the current route, e.g. `admin.sales.invoices.show` is a
     * sales invoice.
     */
    public static function fromRoute(Request $request): self
    {
        foreach (self::cases() as $type) {
            if ($request->routeIs($type->routeName().'.*')) {
                return $type;
            }
        }

        abort(404);
    }
}
