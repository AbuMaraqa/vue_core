<?php

namespace App\Modules\Invoicing\Enums;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Foundation\Concerns\EnumOptions;
use Illuminate\Http\Request;

/**
 * Money received from a customer, or paid to a vendor.
 */
enum PaymentType: string
{
    use EnumOptions;

    case Receipt = 'receipt';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => __('Customer receipt'),
            self::Payment => __('Vendor payment'),
        };
    }

    public function documentType(): DocumentType
    {
        return match ($this) {
            self::Receipt => DocumentType::Receipt,
            self::Payment => DocumentType::Payment,
        };
    }

    /**
     * The invoices it settles.
     */
    public function invoiceType(): InvoiceType
    {
        return match ($this) {
            self::Receipt => InvoiceType::SalesInvoice,
            self::Payment => InvoiceType::PurchaseBill,
        };
    }

    public function chequeDirection(): ChequeDirection
    {
        return match ($this) {
            self::Receipt => ChequeDirection::Incoming,
            self::Payment => ChequeDirection::Outgoing,
        };
    }

    public function permissionPrefix(): string
    {
        return match ($this) {
            self::Receipt => 'Customer Receipt',
            self::Payment => 'Vendor Payment',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Receipt => 'admin.sales.receipts',
            self::Payment => 'admin.purchases.payments',
        };
    }

    public function contactType(): string
    {
        return match ($this) {
            self::Receipt => 'customers',
            self::Payment => 'vendors',
        };
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
