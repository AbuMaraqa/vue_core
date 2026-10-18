import type { ResourceAbilities } from './admin';
import type { ComboboxOption, Translations } from './ui';

/**
 * An account of the chart of accounts (AccountResource), as a row of the tree.
 */
export type Account = {
    id: number;
    parent_id: number | null;
    code: string;
    name: string;
    name_translations: Translations;
    label: string;
    type: string;
    type_label: string;
    subtype: string;
    subtype_label: string;
    is_group: boolean;
    is_active: boolean;
    currency_id: number | null;
    description: string | null;
    can: ResourceAbilities;
};

export type AccountRow = Account & {
    depth: number;
    has_children: boolean;
    /**
     * Base-currency balance including children: positive is a debit balance.
     */
    balance: string;
};

export type CurrencyOption = ComboboxOption & {
    code: string;
    decimal_places: number;
    is_base: boolean;
};

export type TaxOption = ComboboxOption & {
    rate: string;
};

export type SubtypeOption = ComboboxOption & {
    type: string;
};

export type JournalLine = {
    id?: number;
    account_id: number | null;
    account?: string | null;
    cost_center_id: number | null;
    cost_center?: string | null;
    description: string | null;
    debit: string;
    credit: string;
    base_debit?: string;
    base_credit?: string;
};

/**
 * A journal entry (JournalEntryResource).
 */
export type JournalEntry = {
    id: number;
    number: string | null;
    date: string;
    reference: string | null;
    description: string | null;
    status: 'draft' | 'posted' | 'reversed';
    status_label: string;
    status_color: 'light' | 'success' | 'warning';
    currency_id: number;
    currency?: { code: string; decimal_places: number };
    exchange_rate: string;
    total: string;
    base_total: string;
    source: {
        type: string;
        id: number;
        label: string | null;
        /**
         * The document behind the entry, when its module registered it.
         */
        document?: { label: string; url: string | null } | null;
    } | null;
    reversal_of?: { id: number; number: string | null } | null;
    reversed_by?: { id: number; number: string | null } | null;
    created_by?: string | null;
    posted_by?: string | null;
    posted_at: string | null;
    lines?: JournalLine[];
    can: ResourceAbilities & { post: boolean; reverse: boolean };
};
