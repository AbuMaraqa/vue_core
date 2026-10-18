<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { create, index, show } from '@/routes/admin/accounting/journal-entries';
import { create as openingBalances } from '@/routes/admin/accounting/opening-balances';
import type { JournalEntry, Paginated, SelectOption } from '@/types';

const props = defineProps<{
    entries: Paginated<JournalEntry>;
    statuses: SelectOption[];
    filters: {
        search: string | null;
        status: string | null;
        origin: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    status: props.filters.status,
    origin: props.filters.origin,
    from: props.filters.from,
    to: props.filters.to,
});
</script>

<template>
    <div>
        <PageHeader
            :title="t('Journal Entries')"
            :breadcrumbs="[{ title: t('Journal Entries') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="
                        can('Journal Entry Create') && can('Journal Entry Post')
                    "
                    variant="outline"
                    :href="openingBalances()"
                >
                    {{ t('Opening balances') }}
                </BaseButton>
                <BaseButton v-if="can('Journal Entry Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('New journal entry') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="entries">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by number, reference or description...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.status" class="sm:w-36">
                    <option value="">{{ t('All statuses') }}</option>
                    <option
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.origin" class="sm:w-36">
                    <option value="">{{ t('All entries') }}</option>
                    <option value="manual">{{ t('Manual') }}</option>
                    <option value="documents">{{ t('From documents') }}</option>
                </BaseSelect>
                <BaseInput
                    v-model="filters.from"
                    type="date"
                    class="sm:w-40"
                    :title="t('From')"
                />
                <BaseInput
                    v-model="filters.to"
                    type="date"
                    class="sm:w-40"
                    :title="t('To')"
                />
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="entries.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Description') }}</th>
                            <th class="th-cell">{{ t('Source') }}</th>
                            <th class="th-cell text-end">{{ t('Total') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ journalEntry: entry.id })"
                                    class="font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ entry.number ?? t('Draft') }}
                                </Link>
                            </td>
                            <td class="td-cell">
                                {{ formatDate(entry.date, locale) }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ entry.description ?? '—' }}
                                <span
                                    v-if="entry.reference"
                                    class="block text-theme-xs"
                                    >{{ entry.reference }}</span
                                >
                            </td>
                            <td class="td-cell">
                                {{
                                    entry.source
                                        ? `${t(entry.source.type)} ${entry.source.label ?? ''}`
                                        : t('Manual')
                                }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatDecimal(
                                        entry.total,
                                        locale,
                                        entry.currency?.decimal_places ?? 2,
                                    )
                                }}
                                <span class="text-theme-xs text-gray-400">{{
                                    entry.currency?.code
                                }}</span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="entry.status_color">{{
                                    entry.status_label
                                }}</BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No journal entries found.')" />
            </div>
        </DataCard>
    </div>
</template>
