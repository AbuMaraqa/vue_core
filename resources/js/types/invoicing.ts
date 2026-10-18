import type {
    ContactOption,
    CurrencyFormat,
    DocumentJournalEntry,
} from './banking';
import type { ItemOption } from './inventory';

export type InvoiceTypeValue =
    | 'sales_invoice'
    | 'sales_credit_note'
    | 'purchase_bill'
    | 'purchase_debit_note';

/**
 * The route name prefix of a document type, e.g. `admin.sales.invoices`.
 */
export type InvoiceRouteName =
    | 'admin.sales.invoices'
    | 'admin.sales.credit-notes'
    | 'admin.purchases.bills'
    | 'admin.purchases.debit-notes';

/**
 * The route name prefix of a payment type.
 */
export type PaymentRouteName =
    | 'admin.sales.receipts'
    | 'admin.purchases.payments';

export type InvoiceContactType = 'customers' | 'vendors';

export type PaymentTypeValue = 'receipt' | 'payment';

/**
 * The document type a page serves (InvoiceController::typeProps).
 */
export type InvoiceTypeProps = {
    value: InvoiceTypeValue;
    label: string;
    plural: string;
    is_sales: boolean;
    /**
     * Credit and debit notes.
     */
    is_return: boolean;
    route: InvoiceRouteName;
    /**
     * The first part of its permissions, e.g. `Sales Invoice`.
     */
    permission: 'Sales Invoice' | 'Purchase Bill';
    contact_type: InvoiceContactType;
    /**
     * The note of an invoice, or the invoice of a note.
     */
    counterpart: {
        value: InvoiceTypeValue;
        label: string;
        route: InvoiceRouteName;
    };
};

/**
 * The payment type a page serves (PaymentController::typeProps).
 */
export type PaymentTypeProps = {
    value: PaymentTypeValue;
    label: string;
    route: PaymentRouteName;
    permission: 'Customer Receipt' | 'Vendor Payment';
    contact_type: InvoiceContactType;
    /**
     * The documents it settles: sales invoices or purchase bills.
     */
    invoice_route: InvoiceRouteName;
};

export type InvoiceStatus = 'draft' | 'posted' | 'cancelled';

export type InvoiceStatusColor = 'light' | 'success' | 'error';

export type InvoicePaymentStatus = 'unpaid' | 'partially_paid' | 'paid';

export type InvoicePaymentStatusColor = 'warning' | 'info' | 'success';

export type InvoiceLine = {
    id: number;
    item_id: number | null;
    item: {
        code: string;
        type: 'stock' | 'service';
        unit: string | null;
    } | null;
    description: string | null;
    account_id: number | null;
    account: string | null;
    cost_center_id: number | null;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_id: number | null;
    tax_rate: string;
    discount_amount: string;
    subtotal: string;
    tax_amount: string;
    total: string;
    unit_cost: string | null;
};

type DocumentReference<TType> = {
    id: number;
    number: string | null;
    type: TType;
};

/**
 * A payment or note settling an invoice.
 */
export type InvoiceAllocation = {
    id: number;
    date: string;
    amount: string;
    payment: DocumentReference<PaymentTypeValue> | null;
    credit_note: DocumentReference<InvoiceTypeValue> | null;
    exchange_difference: { id: number; number: string | null } | null;
};

/**
 * An invoice a note was applied to.
 */
export type InvoiceAppliedTo = {
    id: number;
    date: string;
    amount: string;
    invoice: DocumentReference<InvoiceTypeValue>;
};

/**
 * A sales invoice, credit note, purchase bill or debit note
 * (InvoiceResource).
 */
export type Invoice = {
    id: number;
    type: InvoiceTypeValue;
    type_label: string;
    /**
     * Null while it is a draft.
     */
    number: string | null;
    contact_id: number;
    contact?: {
        id: number;
        name: string;
        code: string;
        tax_number: string | null;
        address: string | null;
        /**
         * The mobile, else the phone.
         */
        phone: string | null;
    };
    date: string;
    due_date: string;
    reference: string | null;
    currency_id: number;
    currency?: CurrencyFormat;
    exchange_rate: string;
    warehouse_id: number | null;
    warehouse?: string | null;
    cost_center_id: number | null;
    original_invoice?: { id: number; number: string | null } | null;
    prices_include_tax: boolean;
    is_opening: boolean;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    total: string;
    paid_amount: string;
    amount_due: string;
    status: InvoiceStatus;
    status_label: string;
    status_color: InvoiceStatusColor;
    payment_status: InvoicePaymentStatus;
    payment_status_label: string;
    payment_status_color: InvoicePaymentStatusColor;
    is_overdue: boolean;
    notes: string | null;
    created_by?: string | null;
    posted_at: string | null;
    lines?: InvoiceLine[];
    allocations?: InvoiceAllocation[];
    applied_to?: InvoiceAppliedTo[];
    journal_entries?: Array<
        DocumentJournalEntry & { description: string | null }
    >;
    can: {
        update: boolean;
        delete: boolean;
        post: boolean;
        cancel: boolean;
        settle: boolean;
    };
};

/**
 * Base-currency totals of an invoice list (InvoiceController::index).
 */
export type InvoiceSummary = {
    open: string;
    overdue: string;
    drafts: number;
};

/**
 * A line the Create form starts with, e.g. copied from the original invoice
 * of a note.
 */
export type InvoiceLineDefaults = {
    item: ItemOption | null;
    item_id: number | null;
    description: string | null;
    account_id: number | null;
    cost_center_id: number | null;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_id: number | null;
};

/**
 * The starting values of a new document (InvoiceController::create).
 */
export type InvoiceDefaults = {
    contact: ContactOption | null;
    date: string;
    currency_id: number;
    warehouse_id: number | null;
    prices_include_tax: boolean;
    original_invoice: { id: number; number: string | null } | null;
    is_opening: boolean;
    lines: InvoiceLineDefaults[];
};

/**
 * An open note that can be applied to an invoice.
 */
export type OpenNoteOption = {
    value: number;
    label: string;
    available: string;
};

export type PaymentMethodValue = 'cash' | 'bank_transfer' | 'card' | 'cheque';

/**
 * An invoice settled by a payment.
 */
export type PaymentAllocation = {
    id: number;
    date: string;
    amount: string;
    invoice: {
        id: number;
        number: string | null;
        date: string;
        total: string;
    } | null;
};

/**
 * A customer receipt or vendor payment (PaymentResource).
 */
export type Payment = {
    id: number;
    type: PaymentTypeValue;
    type_label: string;
    number: string;
    contact_id: number;
    contact?: string;
    /**
     * The contact's mobile, else their phone.
     */
    contact_phone?: string | null;
    date: string;
    method: PaymentMethodValue;
    method_label: string;
    bank_account?: string | null;
    cheque?: {
        id: number;
        number: string;
        due_date: string;
        status_label: string;
        status_color:
            | 'primary'
            | 'success'
            | 'error'
            | 'warning'
            | 'info'
            | 'light';
    } | null;
    amount: string;
    currency_id: number;
    currency?: CurrencyFormat;
    exchange_rate: string;
    reference: string | null;
    description: string | null;
    status: InvoiceStatus;
    status_label: string;
    status_color: InvoiceStatusColor;
    is_effective?: boolean;
    allocated?: string;
    unallocated?: string;
    allocations?: PaymentAllocation[];
    journal_entries?: DocumentJournalEntry[];
    created_by?: string | null;
    can: { allocate: boolean; cancel: boolean };
};

/**
 * A posted invoice of the contact with something still due
 * (PaymentController::openInvoices).
 */
export type OpenInvoice = {
    id: number;
    number: string | null;
    date: string;
    due_date: string;
    currency_id: number;
    currency: string;
    total: string;
    amount_due: string;
    is_overdue: boolean;
};

/**
 * A contact of the aged receivables or payables (AgedBalances).
 */
export type AgingRow = {
    contact_id: number;
    contact: string;
    invoices: number;
    current: string;
    '1_30': string;
    '31_60': string;
    '61_90': string;
    over_90: string;
    total: string;
};

export type AgingBucket = 'current' | '1_30' | '31_60' | '61_90' | 'over_90';

export type AgingReport = {
    contacts: AgingRow[];
    totals: Record<AgingBucket | 'total', string>;
};

/**
 * A contact or item of the sales or purchases analysis (SalesAnalysis).
 */
export type AnalysisRow = {
    key: number;
    code: string;
    name: string;
    documents: number;
    quantity: string;
    net: string;
    /**
     * Only for sales by item.
     */
    cost: string | null;
    margin: string | null;
};

export type AnalysisReport = {
    rows: AnalysisRow[];
    totals: { quantity: string; net: string; cost: string; margin: string };
};

/**
 * The status of a quotation as shown to the user (QuotationStatus).
 * `expired` and `invoiced` are worked out, never chosen.
 */
export type QuotationStatusValue =
    | 'draft'
    | 'sent'
    | 'accepted'
    | 'rejected'
    | 'expired'
    | 'invoiced';

export type QuotationStatusColor =
    | 'light'
    | 'info'
    | 'success'
    | 'error'
    | 'warning'
    | 'primary';

export type QuotationLine = {
    id: number;
    item_id: number | null;
    item: {
        code: string;
        type: 'stock' | 'service';
        unit: string | null;
    } | null;
    description: string | null;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_id: number | null;
    tax_rate: string;
    discount_amount: string;
    subtotal: string;
    tax_amount: string;
    total: string;
};

/**
 * A sales quotation (QuotationResource).
 */
export type Quotation = {
    id: number;
    number: string;
    contact_id: number;
    contact?: {
        id: number;
        name: string;
        code: string;
        tax_number: string | null;
        address: string | null;
    };
    date: string;
    valid_until: string;
    reference: string | null;
    currency_id: number;
    currency?: CurrencyFormat;
    prices_include_tax: boolean;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    total: string;
    status: QuotationStatusValue;
    status_label: string;
    status_color: QuotationStatusColor;
    is_expired: boolean;
    notes: string | null;
    invoice?: {
        id: number;
        number: string | null;
        status: InvoiceStatus;
    } | null;
    created_by?: string | null;
    lines?: QuotationLine[];
    /**
     * The statuses the user may mark it with now.
     */
    transitions: Array<{ value: QuotationStatusValue; label: string }>;
    can: { update: boolean; delete: boolean; convert: boolean };
};

/**
 * Counts of the quotation list (QuotationController::index).
 */
export type QuotationSummary = {
    waiting: number;
    accepted: number;
    expired: number;
};

/**
 * The starting values of a new quotation (QuotationController::create).
 */
export type QuotationDefaults = {
    contact: ContactOption | null;
    date: string;
    currency_id: number;
    prices_include_tax: boolean;
    validity_days: number;
};
