<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import ItemForm from '@/components/inventory/ItemForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/inventory/items';
import type {
    ComboboxOption,
    Item,
    Resource,
    SelectOption,
    TaxOption,
} from '@/types';

defineProps<{
    item: Resource<Item>;
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
            :title="t('Edit Item')"
            :breadcrumbs="[
                { title: t('Items'), href: index() },
                { title: item.data.name, href: show({ item: item.data.id }) },
                { title: t('Edit') },
            ]"
        />

        <ItemForm
            :item="item.data"
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
