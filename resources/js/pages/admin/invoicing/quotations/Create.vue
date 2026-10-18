<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import QuotationForm from '@/components/invoicing/QuotationForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/sales/quotations';
import type { CurrencyOption, QuotationDefaults, TaxOption } from '@/types';

defineProps<{
    currencies: CurrencyOption[];
    taxes: TaxOption[];
    hasItems: boolean;
    defaults: QuotationDefaults;
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('New quotation')"
            :description="
                t(
                    'Prices you offer a customer. Nothing is recorded in the accounts until it becomes an invoice.',
                )
            "
            :breadcrumbs="[
                { title: t('Sales Quotations'), href: index() },
                { title: t('New quotation') },
            ]"
        />

        <QuotationForm
            :currencies="currencies"
            :taxes="taxes"
            :has-items="hasItems"
            :defaults="defaults"
        />
    </div>
</template>
