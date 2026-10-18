<script setup lang="ts">
import { computed } from 'vue';
import PageHeader from '@/components/common/PageHeader.vue';
import InvoiceForm from '@/components/invoicing/InvoiceForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { documentRoutes } from '@/lib/invoicing-routes';
import type {
    BankAccountOption,
    ComboboxOption,
    CurrencyOption,
    InvoiceDefaults,
    InvoiceTypeProps,
    TaxOption,
} from '@/types';

const props = defineProps<{
    type: InvoiceTypeProps;
    currencies: CurrencyOption[];
    taxes: TaxOption[];
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    warehouses: ComboboxOption[];
    hasItems: boolean;
    bankAccounts: BankAccountOption[];
    units: ComboboxOption[];
    defaults: InvoiceDefaults;
}>();

const { t } = useTranslations();

const title = computed(() =>
    props.defaults.is_opening
        ? t('Opening balance')
        : t('New :document', { document: props.type.label }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                {
                    title: type.plural,
                    href: documentRoutes(type.route).index(),
                },
                { title },
            ]"
        />

        <InvoiceForm
            :type="type"
            :currencies="currencies"
            :taxes="taxes"
            :accounts="accounts"
            :cost-centers="costCenters"
            :warehouses="warehouses"
            :has-items="hasItems"
            :bank-accounts="bankAccounts"
            :units="units"
            :defaults="defaults"
        />
    </div>
</template>
