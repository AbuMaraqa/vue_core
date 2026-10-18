import type { ComboboxOption, Translations } from './ui';

export type AssetStatus =
    | 'active'
    | 'fully_depreciated'
    | 'disposed'
    | 'cancelled';

export type AssetStatusColor = 'success' | 'info' | 'light' | 'error';

export type DepreciationMethod = 'straight_line' | 'declining_balance';

export type DepreciationRunStatus = 'draft' | 'posted' | 'cancelled';

export type DepreciationRunStatusColor = 'light' | 'success' | 'error';

/**
 * The amounts of the asset pages, in the base currency.
 */
export type BaseCurrency = {
    code: string;
    decimal_places: number;
};

/**
 * Depreciation posted for an asset by a run.
 */
export type AssetDepreciationLine = {
    id: number;
    from_date: string;
    to_date: string;
    months: number;
    amount: string;
    run: { id: number; number: string } | null;
};

/**
 * A fixed asset (AssetResource on the server).
 */
export type Asset = {
    id: number;
    number: string;
    name: string;
    category_id: number;
    category?: string;
    serial_number: string | null;
    location: string | null;
    cost_center_id: number | null;
    cost_center?: string | null;
    acquisition_date: string;
    depreciation_start_date: string;
    cost: string;
    salvage_value: string;
    method: DepreciationMethod;
    method_label: string;
    useful_life_months: number;
    declining_rate: string | null;
    opening_depreciation: string;
    accumulated_depreciation: string;
    book_value: string;
    depreciated_until: string | null;
    funding_account?: string | null;
    status: AssetStatus;
    status_label: string;
    status_color: AssetStatusColor;
    disposal_date: string | null;
    disposal_proceeds: string | null;
    notes: string | null;
    depreciations?: AssetDepreciationLine[];
    journal_entries?: Array<{
        id: number;
        number: string | null;
        description: string | null;
    }>;
    can: { update: boolean; dispose: boolean; cancel: boolean };
};

/**
 * A month of an asset's depreciation schedule (DepreciationSchedule::rows).
 */
export type ScheduleRow = {
    /**
     * The month, as YYYY-MM.
     */
    month: string;
    amount: string;
    accumulated: string;
    book_value: string;
    posted: boolean;
};

/**
 * A category option of the asset form, with its default depreciation.
 */
export type AssetCategoryOption = ComboboxOption & {
    value: number;
    method: DepreciationMethod;
    useful_life_months: number;
};

/**
 * A row of the asset categories page.
 */
export type AssetCategoryRow = {
    id: number;
    name: string;
    name_translations: Translations;
    asset_account_id: number;
    asset_account: string;
    accumulated_depreciation_account_id: number;
    accumulated_depreciation_account: string;
    depreciation_expense_account_id: number;
    depreciation_expense_account: string;
    method: DepreciationMethod;
    method_label: string;
    useful_life_months: number;
    assets_count: number;
};

/**
 * An asset the next depreciation run will depreciate, and by how much.
 */
export type DepreciationPreviewRow = {
    id: number;
    number: string;
    name: string;
    amount: string;
    months: number;
    from: string | null;
    to: string | null;
};

/**
 * A row of the depreciation runs history.
 */
export type DepreciationRunRow = {
    id: number;
    number: string;
    period_end: string;
    total: string;
    assets: number;
    status: DepreciationRunStatus;
    status_label: string;
    status_color: DepreciationRunStatusColor;
    created_by: string | null;
    can_cancel: boolean;
};

/**
 * The depreciation of one asset in a run.
 */
export type DepreciationRunLine = {
    asset_id: number;
    asset: string;
    category: string;
    from_date: string;
    to_date: string;
    months: number;
    amount: string;
};

/**
 * A depreciation run with its lines.
 */
export type DepreciationRun = Omit<
    DepreciationRunRow,
    'assets' | 'can_cancel'
> & {
    journal_entries: Array<{ id: number; number: string | null }>;
    lines: DepreciationRunLine[];
};

/**
 * Laravel's paginator serialized as is (not a Resource collection).
 */
export type LengthAwarePage<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: Array<{
        url: string | null;
        label: string;
        page?: number | null;
        active: boolean;
    }>;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

/**
 * A row of the fixed asset register.
 */
export type AssetRegisterRow = {
    id: number;
    number: string;
    name: string;
    category: string;
    acquisition_date: string;
    cost: string;
    accumulated: string;
    book_value: string;
};
