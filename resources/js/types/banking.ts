import type { ResourceAbilities } from './admin';
import type { ComboboxOption } from './ui';

/**
 * How amounts of a currency are shown.
 */
export type CurrencyFormat = {
    code: string;
    decimal_places: number;
};

export type BankAccountType = 'cash' | 'bank';

/**
 * A row of the treasury overview (BankAccountController::index).
 */
export type BankAccountRow = {
    id: number;
    name: string;
    type: BankAccountType;
    type_label: string;
    /**
     * The label of its account in the chart of accounts.
     */
    account: string;
    currency_id: number;
    currency: CurrencyFormat;
    bank_name: string | null;
    branch: string | null;
    account_number: string | null;
    iban: string | null;
    is_active: boolean;
    /**
     * In the account's own currency.
     */
    balance: string;
    /**
     * In the base currency.
     */
    base_balance: string;
    can: ResourceAbilities;
};

/**
 * The bank account of its register page (BankAccountController::show).
 */
export type BankAccountDetails = {
    id: number;
    name: string;
    type_label: string;
    account: string;
    account_id: number;
    currency: CurrencyFormat;
    bank_name: string | null;
    branch: string | null;
    account_number: string | null;
    iban: string | null;
    is_active: boolean;
    balance: string;
};

/**
 * An active bank or cash account of a form (BankingOptions::bankAccounts).
 */
export type BankAccountOption = ComboboxOption & {
    value: number;
    hint: string;
    type: BankAccountType;
    currency_id: number;
    currency: string;
    decimal_places: number;
};

/**
 * A cheque book with numbers left (BankingOptions::chequeBooks).
 */
export type ChequeBookOption = ComboboxOption & {
    value: number;
    bank_account_id: number;
    next_number: number;
};

export type ChequeBook = {
    id: number;
    first_number: number;
    last_number: number;
    next_number: number;
    remaining: number;
    is_active: boolean;
};

export type BankReconciliationSummary = {
    id: number;
    statement_date: string;
    statement_balance: string;
    created_by: string | null;
};

export type BankRegisterLine = {
    id: number;
    entry_id: number;
    date: string;
    number: string | null;
    reference: string | null;
    description: string | null;
    in: string | null;
    out: string | null;
    balance: string;
    reconciled: boolean;
};

/**
 * The register of a bank account for a period (BankAccountRegister).
 */
export type BankRegister = {
    opening: string;
    closing: string;
    total_in: string;
    total_out: string;
    lines: BankRegisterLine[];
    truncated: boolean;
};

/**
 * A journal line on the bank account not reconciled yet. A positive amount
 * is money in.
 */
export type ReconciliationLine = {
    id: number;
    entry_id: number;
    date: string;
    number: string | null;
    reference: string | null;
    description: string | null;
    amount: string;
};

export type BankDocumentStatus = 'draft' | 'posted' | 'cancelled';

export type BankDocumentStatusColor = 'light' | 'success' | 'error';

export type DocumentJournalEntry = {
    id: number;
    number: string | null;
    status: string;
};

export type BankTransferAccount = {
    label: string;
    currency: string;
    decimal_places: number;
};

/**
 * A transfer between bank and cash accounts (BankTransferResource).
 */
export type BankTransfer = {
    id: number;
    number: string;
    date: string;
    from_account?: BankTransferAccount;
    to_account?: BankTransferAccount;
    amount: string;
    to_amount: string;
    fees: string;
    reference: string | null;
    description: string | null;
    status: BankDocumentStatus;
    status_label: string;
    status_color: BankDocumentStatusColor;
    created_by?: string | null;
    journal_entries?: DocumentJournalEntry[];
    can: { cancel: boolean };
};

export type VoucherDirection = 'out' | 'in';

export type BankVoucherLine = {
    id: number;
    account: string | null;
    cost_center: string | null;
    tax: string | null;
    description: string | null;
    amount: string;
    tax_amount: string;
};

/**
 * A payment or receipt voucher (BankVoucherResource).
 */
export type BankVoucher = {
    id: number;
    number: string;
    direction: VoucherDirection;
    direction_label: string;
    date: string;
    bank_account?: string;
    contact?: string | null;
    /**
     * The contact's mobile, else their phone.
     */
    contact_phone?: string | null;
    payee: string | null;
    currency?: CurrencyFormat;
    exchange_rate: string;
    reference: string | null;
    description: string | null;
    subtotal: string;
    tax_total: string;
    total: string;
    status: BankDocumentStatus;
    status_label: string;
    status_color: BankDocumentStatusColor;
    created_by?: string | null;
    lines?: BankVoucherLine[];
    journal_entries?: DocumentJournalEntry[];
    can: { cancel: boolean };
};

export type ChequeDirection = 'incoming' | 'outgoing';

export type ChequeStatus =
    | 'in_hand'
    | 'under_collection'
    | 'collected'
    | 'endorsed'
    | 'returned'
    | 'issued'
    | 'cleared'
    | 'cancelled'
    | 'bounced';

export type ChequeStatusColor =
    | 'primary'
    | 'success'
    | 'error'
    | 'warning'
    | 'info'
    | 'light';

/**
 * A step the user may take with a cheque in its current status.
 */
export type ChequeActionOption = {
    value: string;
    label: string;
    needs_bank_account: boolean;
    needs_contact: boolean;
};

/**
 * A step in the life of a cheque. Statuses and the action are labels.
 */
export type ChequeEvent = {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string;
    date: string;
    journal_entry: { id: number; number: string | null } | null;
    note: string | null;
    is_undone: boolean;
    created_by: string | null;
};

/**
 * A received or issued cheque (ChequeResource).
 */
export type Cheque = {
    id: number;
    direction: ChequeDirection;
    direction_label: string;
    number: string;
    status: ChequeStatus;
    status_label: string;
    status_color: ChequeStatusColor;
    contact_id: number;
    contact?: string | null;
    bank_account?: string | null;
    drawee_bank: string | null;
    amount: string;
    currency_id: number;
    currency?: CurrencyFormat;
    exchange_rate: string;
    issue_date: string;
    due_date: string;
    is_overdue: boolean;
    counter_account?: string;
    endorsed_to?: string | null;
    description: string | null;
    events?: ChequeEvent[];
    actions: ChequeActionOption[];
    can: { manage: boolean };
};

/**
 * Base-currency totals of the open cheques (ChequeController::summary).
 */
export type ChequeSummary = {
    in_hand: string;
    under_collection: string;
    issued: string;
    due_this_week: string;
    overdue: number;
};

/**
 * A contact of the contact picker (ContactController::option).
 */
export type ContactOption = ComboboxOption & {
    value: number;
    hint: string;
    currency_id: number | null;
    payment_terms_days: number;
};
