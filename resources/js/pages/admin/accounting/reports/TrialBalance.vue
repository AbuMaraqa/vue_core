<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { generalLedger, trialBalance } from '@/routes/admin/accounting/reports';
import type { ComboboxOption } from '@/types';

type Row = {
    account_id: number;
    code: string;
    name: string;
    opening_debit: string;
    opening_credit: string;
    debit: string;
    credit: string;
    closing_debit: string;
    closing_credit: string;
};

const props = defineProps<{
    report: {
        rows: Row[];
        totals: Omit<Row, 'account_id' | 'code' | 'name'>;
        balanced: boolean;
    };
    filters: {
        from: string;
        to: string;
        cost_center_id: number | null;
        include_zero: boolean;
    };
    costCenters: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const { filters, apply } = useReportFilters(trialBalance, { ...props.filters });

const amount = (value: string): string =>
    Number(value) === 0
        ? ''
        : formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const columns = [
    'opening_debit',
    'opening_credit',
    'debit',
    'credit',
    'closing_debit',
    'closing_credit',
] as const;
</script>

<template>
    <ReportShell
        :title="t('Trial Balance')"
        :route="trialBalance"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('From')" for="from"
                ><BaseInput id="from" v-model="filters.from" type="date"
            /></BaseField>
            <BaseField :label="t('To')" for="to"
                ><BaseInput id="to" v-model="filters.to" type="date"
            /></BaseField>
            <BaseField :label="t('Cost center')" class="w-56">
                <BaseCombobox
                    v-model="filters.cost_center_id"
                    :options="costCenters"
                    clearable
                    :placeholder="t('All')"
                />
            </BaseField>
            <BaseCheckbox v-model="filters.include_zero" class="mb-3">{{
                t('Show accounts without balance')
            }}</BaseCheckbox>
        </template>

        <DataCard>
            <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                <span class="text-theme-sm text-gray-500 dark:text-gray-400">{{
                    t('Amounts in :currency', { currency: baseCurrency.code })
                }}</span>
                <BaseBadge :color="report.balanced ? 'success' : 'error'">
                    {{ report.balanced ? t('Balanced') : t('Not balanced') }}
                </BaseBadge>
            </div>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="report.rows.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell" rowspan="2">
                                {{ t('Account') }}
                            </th>
                            <th class="th-cell text-center" colspan="2">
                                {{ t('Opening balance') }}
                            </th>
                            <th class="th-cell text-center" colspan="2">
                                {{ t('Movements') }}
                            </th>
                            <th class="th-cell text-center" colspan="2">
                                {{ t('Closing balance') }}
                            </th>
                        </tr>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <template v-for="column in 3" :key="column">
                                <th class="th-cell text-end">
                                    {{ t('Debit') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Credit') }}
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="row in report.rows" :key="row.account_id">
                            <td class="td-cell">
                                <Link
                                    :href="
                                        generalLedger.url(undefined, {
                                            query: {
                                                account_id: row.account_id,
                                                from: filters.from,
                                                to: filters.to,
                                            },
                                        })
                                    "
                                    class="hover:text-brand-500"
                                >
                                    <span
                                        class="font-mono text-theme-xs text-gray-500"
                                        dir="ltr"
                                        >{{ row.code }}</span
                                    >
                                    {{ row.name }}
                                </Link>
                            </td>
                            <td
                                v-for="column in columns"
                                :key="column"
                                class="td-cell text-end font-mono"
                                dir="ltr"
                            >
                                {{ amount(row[column]) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3">{{ t('Total') }}</td>
                            <td
                                v-for="column in columns"
                                :key="column"
                                class="px-5 py-3 text-end font-mono"
                                dir="ltr"
                            >
                                {{
                                    formatDecimal(
                                        report.totals[column],
                                        locale,
                                        baseCurrency.decimal_places,
                                    )
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <EmptyState
                    v-else
                    :message="t('No postings in this period.')"
                />
            </div>
        </DataCard>
    </ReportShell>
</template>
