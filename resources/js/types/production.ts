import type { ResourceAbilities } from './admin';
import type { ItemOption } from './inventory';
import type { ComboboxOption } from './ui';

/**
 * Another cost of a batch or an order, e.g. labour (on the server:
 * BillOfMaterialsCost, ProductionOrderCost).
 */
export type ProductionCost = {
    id?: number;
    description: string;
    account_id: number | null;
    account?: string | null;
    amount: string;
};

export type BillOfMaterialsComponent = {
    id: number;
    item_id: number;
    item: ItemOption | null;
    quantity: string;
    waste_percent: string;
    /**
     * The quantity with the waste.
     */
    gross_quantity: string;
};

/**
 * A recipe of a product (BillOfMaterialsResource on the server).
 */
export type BillOfMaterials = {
    id: number;
    code: string;
    name: string;
    item_id: number;
    item?: string;
    item_option?: ItemOption;
    unit?: string | null;
    quantity: string;
    notes: string | null;
    is_active: boolean;
    components_count?: number;
    components?: BillOfMaterialsComponent[];
    costs?: ProductionCost[];
    created_at: string | null;
    can: ResourceAbilities;
};

/**
 * The cost of a recipe at today's prices (CostEstimator on the server).
 */
export type BillOfMaterialsEstimate = {
    materials: string;
    other: string;
    total: string;
    unit: string;
};

/**
 * A recipe to copy into a production order (ProductionOptions on the
 * server). Component quantities include the waste.
 */
export type BillOfMaterialsOption = ComboboxOption & {
    value: number;
    item: ItemOption;
    quantity: string;
    components: Array<{ item: ItemOption; quantity: string }>;
    costs: Array<{ description: string; account_id: number; amount: string }>;
};

export type ProductionOrderStatus =
    | 'draft'
    | 'in_progress'
    | 'completed'
    | 'cancelled';

export type ProductionOrderMaterial = {
    id: number;
    item_id: number;
    item: ItemOption | null;
    quantity: string;
    unit_cost: string;
    issued_value: string;
    consumed_quantity: string | null;
    value: string;
};

/**
 * A production order (ProductionOrderResource on the server).
 */
export type ProductionOrder = {
    id: number;
    number: string;
    date: string;
    bill_of_materials_id: number | null;
    bill_of_materials?: string | null;
    item_id: number;
    item?: string;
    item_option?: ItemOption;
    unit?: string | null;
    quantity: string;
    produced_quantity: string | null;
    materials_warehouse_id: number;
    materials_warehouse?: string;
    product_warehouse_id: number;
    product_warehouse?: string;
    contact_id: number | null;
    contact?: ComboboxOption | null;
    cost_center_id: number | null;
    cost_center?: string | null;
    reference: string | null;
    description: string | null;
    status: ProductionOrderStatus;
    status_label: string;
    status_color: 'light' | 'warning' | 'success' | 'error';
    started_on: string | null;
    completed_on: string | null;
    materials_cost: string;
    other_costs: string;
    total_cost: string;
    unit_cost: string;
    created_by?: string | null;
    materials?: ProductionOrderMaterial[];
    costs?: ProductionCost[];
    journal_entries?: Array<{
        id: number;
        number: string | null;
        description: string | null;
    }>;
    can: {
        update: boolean;
        delete: boolean;
        start: boolean;
        complete: boolean;
        reverse: boolean;
    };
};

export type ProductionCurrency = { code: string; decimal_places: number };
