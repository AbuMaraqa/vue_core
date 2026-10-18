<script setup lang="ts">
import Factory from '~icons/solar/toolbox-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import BillOfMaterialsForm from '@/components/production/BillOfMaterialsForm.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/production/boms';
import { create as createOrder } from '@/routes/admin/production/orders';
import type {
    BillOfMaterials,
    BillOfMaterialsEstimate,
    ComboboxOption,
    ProductionCurrency,
    Resource,
} from '@/types';

defineProps<{
    bom: Resource<BillOfMaterials>;
    estimate: BillOfMaterialsEstimate;
    accounts: ComboboxOption[];
    defaultCostAccountId: number | null;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { can } = usePermissions();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Edit Bill of Materials')"
            :breadcrumbs="[
                { title: t('Bills of Materials'), href: index() },
                { title: bom.data.code },
            ]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Production Order Create') && bom.data.is_active"
                    variant="outline"
                    :href="
                        createOrder(undefined, { query: { bom: bom.data.id } })
                    "
                >
                    <Factory class="size-5" />
                    {{ t('New production order') }}
                </BaseButton>
            </template>
        </PageHeader>

        <BillOfMaterialsForm
            :bom="bom.data"
            :estimate="estimate"
            :accounts="accounts"
            :default-cost-account-id="defaultCostAccountId"
            :base-currency="baseCurrency"
        />
    </div>
</template>
