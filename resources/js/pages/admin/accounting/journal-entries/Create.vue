<script setup lang="ts">
import JournalEntryForm from '@/components/accounting/JournalEntryForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/accounting/journal-entries';
import type { ComboboxOption, CurrencyOption } from '@/types';

defineProps<{
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    currencies: CurrencyOption[];
    defaults: { date: string; currency_id: number };
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('New journal entry')"
            :breadcrumbs="[
                { title: t('Journal Entries'), href: index() },
                { title: t('New journal entry') },
            ]"
        />

        <JournalEntryForm
            :accounts="accounts"
            :cost-centers="costCenters"
            :currencies="currencies"
            :defaults="defaults"
        />
    </div>
</template>
