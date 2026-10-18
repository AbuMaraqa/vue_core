<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import ProductionOrderForm from '@/components/production/ProductionOrderForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/production/orders';
import type {
    BillOfMaterialsOption,
    ComboboxOption,
    ProductionCurrency,
} from '@/types';

defineProps<{
    defaults: {
        date: string;
        materials_warehouse_id: number | null;
        product_warehouse_id: number | null;
        bill_of_materials_id: number | null;
    };
    warehouses: ComboboxOption[];
    billsOfMaterials: BillOfMaterialsOption[];
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    defaultCostAccountId: number | null;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('New Production Order')"
            :breadcrumbs="[
                { title: t('Production Orders'), href: index() },
                { title: t('New Production Order') },
            ]"
        />

        <ProductionOrderForm
            :defaults="defaults"
            :warehouses="warehouses"
            :bills-of-materials="billsOfMaterials"
            :accounts="accounts"
            :cost-centers="costCenters"
            :default-cost-account-id="defaultCostAccountId"
            :base-currency="baseCurrency"
        />
    </div>
</template>
