<script setup lang="ts">
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { budgetVsActual } from '@/routes/admin/accounting/reports';
import type { ComboboxOption } from '@/types';

type Row = {
    account_id: number;
    account: string;
    type: string;
    budget: string;
    actual: string;
    variance: string;
    percent: string | null;
};

const props = defineProps<{
    report: { rows: Row[]; totals: { budget: string; actual: string } } | null;
    filters: { budget_id: number | null; month: number };
    budgets: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const { filters, apply } = useReportFilters(budgetVsActual, {
    ...props.filters,
});

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * Spending above budget, or revenue below it, is shown in red.
 */
function isUnfavourable(row: Row): boolean {
    const variance = Number(row.variance);

    return row.type === 'revenue' ? variance < 0 : variance > 0;
}

const months = Array.from({ length: 12 }, (_, index) => index + 1);
</script>

<template>
    <ReportShell
        :title="t('Budget vs Actual')"
        :route="report ? budgetVsActual : undefined"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('Budget')" class="w-full sm:w-72">
                <BaseCombobox
                    v-model="filters.budget_id"
                    :options="budgets"
                    :placeholder="t('Choose a budget')"
                />
            </BaseField>
            <BaseField :label="t('Through month')" for="month">
                <BaseSelect id="month" v-model="filters.month" class="w-32">
                    <option v-for="month in months" :key="month" :value="month">
                        {{ month }}
                    </option>
                </BaseSelect>
            </BaseField>
        </template>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="report && report.rows.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Account') }}</th>
                            <th class="th-cell text-end">{{ t('Budget') }}</th>
                            <th class="th-cell text-end">{{ t('Actual') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Variance') }}
                            </th>
                            <th class="th-cell text-end">%</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="row in report.rows" :key="row.account_id">
                            <td
                                class="td-cell text-gray-800 dark:text-white/90"
                            >
                                {{ row.account }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(row.budget) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(row.actual) }}
                            </td>
                            <td
                                :class="[
                                    'td-cell text-end font-mono',
                                    isUnfavourable(row)
                                        ? 'text-error-600'
                                        : 'text-success-600',
                                ]"
                                dir="ltr"
                            >
                                {{ amount(row.variance) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    row.percent === null
                                        ? '—'
                                        : `${row.percent}%`
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState
                    v-else
                    :message="
                        report
                            ? t('This budget has no lines up to this month.')
                            : t(
                                  'Choose a budget to compare it with the actual amounts.',
                              )
                    "
                />
            </div>
        </DataCard>
    </ReportShell>
</template>
