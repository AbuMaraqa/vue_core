<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { show as showAsset } from '@/routes/admin/assets/assets';
import { index, pdf } from '@/routes/admin/assets/depreciation';
import type { BaseCurrency, DepreciationRun } from '@/types/assets';

const props = defineProps<{
    run: DepreciationRun;
    baseCurrency: BaseCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const periodLabel = computed(() =>
    formatDate(props.run.period_end, locale.value, {
        month: 'long',
        year: 'numeric',
    }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Depreciation :number', { number: run.number })"
            :breadcrumbs="[
                { title: t('Depreciation'), href: index() },
                { title: run.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ run: run.id })" />
            </template>
        </PageHeader>

        <div class="space-y-6">
            <BaseCard>
                <dl
                    class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-4"
                >
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Status') }}
                        </dt>
                        <dd class="mt-1">
                            <BaseBadge :color="run.status_color">
                                {{ run.status_label }}
                            </BaseBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Month') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ periodLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Period end') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ formatDate(run.period_end, locale) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Total') }}
                        </dt>
                        <dd
                            class="mt-1 font-mono font-semibold text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ amount(run.total) }}
                            <span
                                class="text-theme-xs font-normal text-gray-400"
                            >
                                {{ baseCurrency.code }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Created by') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ run.created_by ?? t('System') }}
                        </dd>
                    </div>
                    <div v-if="run.journal_entries.length">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Journal entries') }}
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <template
                                v-for="entry in run.journal_entries"
                                :key="entry.id"
                            >
                                <Link
                                    v-if="can('Journal Entry View List')"
                                    :href="
                                        showEntry({ journalEntry: entry.id })
                                    "
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ entry.number ?? '—' }}
                                </Link>
                                <span
                                    v-else
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ entry.number ?? '—' }}
                                </span>
                            </template>
                        </dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard :title="t('Assets')">
                <div class="custom-scrollbar overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Asset') }}</th>
                                <th class="th-cell">{{ t('Category') }}</th>
                                <th class="th-cell">{{ t('Period') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Months') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="line in run.lines" :key="line.asset_id">
                                <td class="td-cell">
                                    <Link
                                        :href="
                                            showAsset({ asset: line.asset_id })
                                        "
                                        class="text-gray-800 hover:text-brand-500 dark:text-white/90"
                                    >
                                        {{ line.asset }}
                                    </Link>
                                </td>
                                <td class="td-cell">{{ line.category }}</td>
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(line.from_date, locale) }}
                                    –
                                    {{ formatDate(line.to_date, locale) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ line.months }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(line.amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="4">
                                    {{ t('Total') }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        ({{ baseCurrency.code }})
                                    </span>
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(run.total) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </BaseCard>
        </div>
    </div>
</template>
