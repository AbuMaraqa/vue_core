<script setup lang="ts">
import JournalEntryForm from '@/components/accounting/JournalEntryForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/accounting/journal-entries';
import type {
    ComboboxOption,
    CurrencyOption,
    JournalEntry,
    Resource,
} from '@/types';

defineProps<{
    entry: Resource<JournalEntry>;
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    currencies: CurrencyOption[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Edit journal entry')"
            :breadcrumbs="[
                { title: t('Journal Entries'), href: index() },
                {
                    title: t('Draft'),
                    href: show({ journalEntry: entry.data.id }),
                },
                { title: t('Edit') },
            ]"
        />

        <JournalEntryForm
            :entry="entry.data"
            :accounts="accounts"
            :cost-centers="costCenters"
            :currencies="currencies"
        />
    </div>
</template>
