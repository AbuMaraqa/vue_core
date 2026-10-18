<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import ContactForm from '@/components/contacts/ContactForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/contacts';
import type { ComboboxOption, CurrencyOption, SelectOption } from '@/types';

defineProps<{
    type: 'customer' | 'vendor';
    kinds: SelectOption[];
    currencies: CurrencyOption[];
    receivableAccounts: ComboboxOption[];
    payableAccounts: ComboboxOption[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Add Contact')"
            :breadcrumbs="[
                { title: t('Customers & Vendors'), href: index() },
                { title: t('Add Contact') },
            ]"
        />

        <ContactForm
            :type="type"
            :kinds="kinds"
            :currencies="currencies"
            :receivable-accounts="receivableAccounts"
            :payable-accounts="payableAccounts"
        />
    </div>
</template>
