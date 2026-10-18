<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ReportShell from '@/components/accounting/ReportShell.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show } from '@/routes/admin/assets/assets';
import { register } from '@/routes/admin/assets/reports';
import type { ComboboxOption } from '@/types';
import type { AssetRegisterRow, BaseCurrency } from '@/types/assets';

const props = defineProps<{
    report: {
        rows: AssetRegisterRow[];
        totals: { cost: string; accumulated: string; book_value: string };
    };
    filters: { as_of: string; category_id: number | null };
    categories: ComboboxOption[];
    baseCurrency: BaseCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const { filters, apply } = useReportFilters(register, { ...props.filters });

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);
</script>

<template>
    <ReportShell
        :title="t('Fixed Asset Register')"
        :route="register"
        export-permission="Asset Export"
        :section="t('Fixed Assets')"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('As of')" for="as_of">
                <BaseInput id="as_of" v-model="filters.as_of" type="date" />
            </BaseField>
            <BaseField :label="t('Category')" class="w-56">
                <BaseCombobox
                    v-model="filters.category_id"
                    :options="categories"
                    clearable
                    :placeholder="t('All categories')"
                />
            </BaseField>
        </template>

        <DataCard>
            <div class="px-5 py-3 sm:px-6">
                <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{
                        t('Amounts in :currency', {
                            currency: baseCurrency.code,
                        })
                    }}
                </span>
            </div>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="report.rows.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Asset') }}</th>
                            <th class="th-cell">{{ t('Category') }}</th>
                            <th class="th-cell">{{ t('Acquired') }}</th>
                            <th class="th-cell text-end">{{ t('Cost') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Accumulated depreciation') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Book value') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="row in report.rows" :key="row.id">
                            <td class="td-cell">
                                <Link
                                    :href="show({ asset: row.id })"
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ row.number }}
                                </Link>
                            </td>
                            <td
                                class="td-cell text-gray-800 dark:text-white/90"
                            >
                                {{ row.name }}
                            </td>
                            <td class="td-cell">{{ row.category }}</td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(row.acquisition_date, locale) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(row.cost) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(row.accumulated) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(row.book_value) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="4">
                                {{ t('Total') }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.totals.cost) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.totals.accumulated) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.totals.book_value) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <EmptyState v-else :message="t('No assets on this date.')" />
            </div>
        </DataCard>
    </ReportShell>
</template>
