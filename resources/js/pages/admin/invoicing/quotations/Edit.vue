<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import QuotationForm from '@/components/invoicing/QuotationForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/sales/quotations';
import type {
    ContactOption,
    CurrencyOption,
    ItemOption,
    Quotation,
    Resource,
    TaxOption,
} from '@/types';

defineProps<{
    currencies: CurrencyOption[];
    taxes: TaxOption[];
    hasItems: boolean;
    quotation: Resource<Quotation>;
    contact: ContactOption;
    lineItems: Record<string, ItemOption | null>;
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="
                t('Edit quotation :number', { number: quotation.data.number })
            "
            :breadcrumbs="[
                { title: t('Sales Quotations'), href: index() },
                {
                    title: quotation.data.number,
                    href: show({ quotation: quotation.data.id }),
                },
                { title: t('Edit') },
            ]"
        />

        <QuotationForm
            :currencies="currencies"
            :taxes="taxes"
            :has-items="hasItems"
            :quotation="quotation.data"
            :contact="contact"
            :line-items="lineItems"
        />
    </div>
</template>
