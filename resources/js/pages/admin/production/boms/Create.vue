<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import BillOfMaterialsForm from '@/components/production/BillOfMaterialsForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/production/boms';
import type { ComboboxOption, ProductionCurrency } from '@/types';

defineProps<{
    accounts: ComboboxOption[];
    defaultCostAccountId: number | null;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('New Bill of Materials')"
            :breadcrumbs="[
                { title: t('Bills of Materials'), href: index() },
                { title: t('New Bill of Materials') },
            ]"
        />

        <BillOfMaterialsForm
            :accounts="accounts"
            :default-cost-account-id="defaultCostAccountId"
            :base-currency="baseCurrency"
        />
    </div>
</template>
