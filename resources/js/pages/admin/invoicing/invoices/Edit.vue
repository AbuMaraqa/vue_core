<script setup lang="ts">
import { computed } from 'vue';
import PageHeader from '@/components/common/PageHeader.vue';
import InvoiceForm from '@/components/invoicing/InvoiceForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { documentRoutes } from '@/lib/invoicing-routes';
import type {
    BankAccountOption,
    ComboboxOption,
    ContactOption,
    CurrencyOption,
    Invoice,
    InvoiceTypeProps,
    ItemOption,
    Resource,
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
    invoice: Resource<Invoice>;
    contact: ContactOption;
    lineItems: Record<string, ItemOption | null>;
}>();

const { t } = useTranslations();

const routes = computed(() => documentRoutes(props.type.route));
</script>

<template>
    <div>
        <PageHeader
            :title="t('Edit :document', { document: type.label })"
            :breadcrumbs="[
                { title: type.plural, href: routes.index() },
                {
                    title: t('Draft'),
                    href: routes.show({ invoice: invoice.data.id }),
                },
                { title: t('Edit') },
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
            :invoice="invoice.data"
            :contact="contact"
            :line-items="lineItems"
        />
    </div>
</template>
