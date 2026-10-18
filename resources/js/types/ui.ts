export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/**
 * An option of a select, e.g. from an enum's `options()` on the server.
 */
export type SelectOption = {
    value: string | number;
    label: string;
};

/**
 * An option of BaseCombobox.
 */
export type ComboboxOption = {
    value: string | number;
    label: string;
    hint?: string;
    disabled?: boolean;
};

/**
 * A translatable attribute: one value per language code, e.g. `{ ar, en }`.
 */
export type Translations = Record<string, string>;
