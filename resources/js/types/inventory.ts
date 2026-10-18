import type { ResourceAbilities } from './admin';
import type { ComboboxOption, Translations } from './ui';

/**
 * An item or a service (ItemResource on the server).
 */
export type Item = {
    id: number;
    code: string;
    barcode: string | null;
    name: string;
    name_translations: Translations;
    description: string | null;
    type: 'stock' | 'service';
    type_label: string;
    category_id: number | null;
    category?: string | null;
    unit_id: number | null;
    unit?: string | null;
    sale_price: string;
    purchase_price: string;
    sales_tax_id: number | null;
    purchase_tax_id: number | null;
    sales_account_id: number | null;
    purchase_account_id: number | null;
    inventory_account_id: number | null;
    cost_of_sales_account_id: number | null;
    reorder_level: string | null;
    is_active: boolean;
    /**
     * Quantity and value in all warehouses. Only on lists.
     */
    on_hand?: string;
    stock_value?: string;
    created_at: string | null;
    can: ResourceAbilities;
};

/**
 * An item of the item picker (InventoryOptions::itemOption on the server).
 */
export type ItemOption = ComboboxOption & {
    value: number;
    type: 'stock' | 'service';
    unit: string | null;
    sale_price: string;
    purchase_price: string;
    sales_tax_id: number | null;
    purchase_tax_id: number | null;
    /**
     * The quantity in all warehouses; null for services.
     */
    on_hand: string | null;
};

export type StockDocumentStatus = 'draft' | 'posted' | 'cancelled';

export type StockDocumentStatusColor = 'light' | 'success' | 'error';

export type StockAdjustmentLine = {
    id: number;
    item_id: number;
    item: string | null;
    quantity: string;
    unit_cost: string;
    value: string;
};

/**
 * A stock adjustment (StockAdjustmentResource on the server).
 */
export type StockAdjustment = {
    id: number;
    number: string;
    date: string;
    warehouse?: string;
    account?: string;
    description: string | null;
    status: StockDocumentStatus;
    status_label: string;
    status_color: StockDocumentStatusColor;
    created_by?: string | null;
    total?: string;
    lines?: StockAdjustmentLine[];
    journal_entries?: Array<{ id: number; number: string | null }>;
    can: { cancel: boolean };
};

export type StockTransferLine = {
    id: number;
    item_id: number;
    item: string | null;
    unit: string | null;
    quantity: string;
};

/**
 * A stock transfer between warehouses (StockTransferResource on the server).
 */
export type StockTransfer = {
    id: number;
    number: string;
    date: string;
    from_warehouse?: string;
    to_warehouse?: string;
    description: string | null;
    status: StockDocumentStatus;
    status_label: string;
    status_color: StockDocumentStatusColor;
    created_by?: string | null;
    lines_count?: number;
    lines?: StockTransferLine[];
    can: { cancel: boolean };
};
