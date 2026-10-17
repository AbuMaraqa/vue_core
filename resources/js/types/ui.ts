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
