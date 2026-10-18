<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import ProductionOrderForm from '@/components/production/ProductionOrderForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/production/orders';
import type {
    BillOfMaterialsOption,
    ComboboxOption,
    ProductionCurrency,
    ProductionOrder,
    Resource,
} from '@/types';

defineProps<{
    order: Resource<ProductionOrder>;
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
            :title="
                t('Edit production order :number', {
                    number: order.data.number,
                })
            "
            :breadcrumbs="[
                { title: t('Production Orders'), href: index() },
                {
                    title: order.data.number,
                    href: show({ order: order.data.id }),
                },
                { title: t('Edit') },
            ]"
        />

        <ProductionOrderForm
            :order="order.data"
            :warehouses="warehouses"
            :bills-of-materials="billsOfMaterials"
            :accounts="accounts"
            :cost-centers="costCenters"
            :default-cost-account-id="defaultCostAccountId"
            :base-currency="baseCurrency"
        />
    </div>
</template>
