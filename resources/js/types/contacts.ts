import type { ResourceAbilities } from './admin';

/**
 * A customer or vendor (ContactResource on the server).
 */
export type Contact = {
    id: number;
    code: string;
    kind: 'company' | 'individual';
    kind_label: string;
    name: string;
    contact_person: string | null;
    is_customer: boolean;
    is_vendor: boolean;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    tax_number: string | null;
    currency_id: number | null;
    currency?: string | null;
    receivable_account_id: number | null;
    receivable_account?: string | null;
    payable_account_id: number | null;
    payable_account?: string | null;
    payment_terms_days: number;
    credit_limit: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    notes: string | null;
    is_active: boolean;
    /**
     * Net balance in the base currency, positive when the contact owes us.
     * Only on lists.
     */
    balance?: string;
    created_at: string | null;
    can: ResourceAbilities;
};

/**
 * What a contact owes us and what we owe them, in the base currency.
 */
export type ContactBalances = {
    receivable: string;
    payable: string;
    balance: string;
};
