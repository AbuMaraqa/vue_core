import purchaseBills from '@/routes/admin/purchases/bills';
import purchaseDebitNotes from '@/routes/admin/purchases/debit-notes';
import vendorPayments from '@/routes/admin/purchases/payments';
import salesCreditNotes from '@/routes/admin/sales/credit-notes';
import salesInvoices from '@/routes/admin/sales/invoices';
import customerReceipts from '@/routes/admin/sales/receipts';
import type {
    InvoiceRouteName,
    InvoiceTypeValue,
    PaymentRouteName,
    PaymentTypeValue,
} from '@/types';

/**
 * The Wayfinder routes of a document type. The four types share one shape,
 * so pages serving all of them call e.g. `documentRoutes(type.route).show()`.
 */
export type DocumentRoutes = typeof salesInvoices;

/**
 * The Wayfinder routes of a payment type (receipts or vendor payments).
 */
export type PaymentRoutes = typeof customerReceipts;

const documents: Record<InvoiceRouteName, DocumentRoutes> = {
    'admin.sales.invoices': salesInvoices,
    'admin.sales.credit-notes': salesCreditNotes,
    'admin.purchases.bills': purchaseBills,
    'admin.purchases.debit-notes': purchaseDebitNotes,
};

const payments: Record<PaymentRouteName, PaymentRoutes> = {
    'admin.sales.receipts': customerReceipts,
    'admin.purchases.payments': vendorPayments,
};

/**
 * The routes of a document type from its route name prefix, e.g.
 * `admin.sales.invoices`.
 */
export function documentRoutes(route: InvoiceRouteName): DocumentRoutes {
    return documents[route];
}

/**
 * The routes of a payment type from its route name prefix, e.g.
 * `admin.sales.receipts`.
 */
export function paymentRoutes(route: PaymentRouteName): PaymentRoutes {
    return payments[route];
}

const documentRouteNames: Record<InvoiceTypeValue, InvoiceRouteName> = {
    sales_invoice: 'admin.sales.invoices',
    sales_credit_note: 'admin.sales.credit-notes',
    purchase_bill: 'admin.purchases.bills',
    purchase_debit_note: 'admin.purchases.debit-notes',
};

/**
 * The routes of a document from its type, e.g. `sales_credit_note`.
 */
export function documentRoutesOf(type: InvoiceTypeValue): DocumentRoutes {
    return documents[documentRouteNames[type]];
}

/**
 * The routes of a payment from its type: `receipt` or `payment`.
 */
export function paymentRoutesOf(type: PaymentTypeValue): PaymentRoutes {
    return type === 'receipt'
        ? payments['admin.sales.receipts']
        : payments['admin.purchases.payments'];
}
