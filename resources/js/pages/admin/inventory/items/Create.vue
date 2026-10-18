<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import ItemForm from '@/components/inventory/ItemForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/inventory/items';
import type { ComboboxOption, SelectOption, TaxOption } from '@/types';

defineProps<{
    type: 'stock' | 'service';
    types: SelectOption[];
    categories: ComboboxOption[];
    units: ComboboxOption[];
    salesTaxes: TaxOption[];
    purchaseTaxes: TaxOption[];
    revenueAccounts: ComboboxOption[];
    expenseAccounts: ComboboxOption[];
    inventoryAccounts: ComboboxOption[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="type === 'service' ? t('Add Service') : t('Add Item')"
            :breadcrumbs="[
                { title: t('Items'), href: index() },
                {
                    title:
                        type === 'service' ? t('Add Service') : t('Add Item'),
                },
            ]"
        />

        <ItemForm
            :type="type"
            :types="types"
            :categories="categories"
            :units="units"
            :sales-taxes="salesTaxes"
            :purchase-taxes="purchaseTaxes"
            :revenue-accounts="revenueAccounts"
            :expense-accounts="expenseAccounts"
            :inventory-accounts="inventoryAccounts"
        />
    </div>
</template>
