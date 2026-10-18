<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import ContactForm from '@/components/contacts/ContactForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/contacts';
import type {
    ComboboxOption,
    Contact,
    CurrencyOption,
    Resource,
    SelectOption,
} from '@/types';

defineProps<{
    contact: Resource<Contact>;
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
            :title="t('Edit Contact')"
            :breadcrumbs="[
                { title: t('Customers & Vendors'), href: index() },
                {
                    title: contact.data.name,
                    href: show({ contact: contact.data.id }),
                },
                { title: t('Edit') },
            ]"
        />

        <ContactForm
            :contact="contact.data"
            :kinds="kinds"
            :currencies="currencies"
            :receivable-accounts="receivableAccounts"
            :payable-accounts="payableAccounts"
        />
    </div>
</template>
