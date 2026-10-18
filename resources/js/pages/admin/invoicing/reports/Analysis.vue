<script setup lang="ts">
import { computed } from 'vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { analysis } from '@/routes/admin/invoicing/reports';
import type { AnalysisReport } from '@/types';

const props = defineProps<{
    report: AnalysisReport;
    filters: {
        side: 'sales' | 'purchases';
        group_by: 'contact' | 'item';
        from: string;
        to: string;
    };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const { filters, apply } = useReportFilters(analysis, { ...props.filters });

const isSales = computed(() => props.filters.side === 'sales');
const byItem = computed(() => props.filters.group_by === 'item');
const withMargin = computed(() => isSales.value && byItem.value);

const title = computed(() => {
    if (isSales.value) {
        return byItem.value ? t('Sales by Item') : t('Sales by Customer');
    }

    return byItem.value ? t('Purchases by Item') : t('Purchases by Vendor');
});

const amount = (value: string | null): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);

/**
 * The gross margin as a share of net sales.
 */
function marginPercent(net: string, margin: string | null): string {
    const sales = Number(net);

    if (margin === null || !sales) {
        return '—';
    }

    return `${formatDecimal((Number(margin) / sales) * 100, locale.value, 1)}%`;
}
</script>

<template>
    <ReportShell
        :title="title"
        :description="
            isSales
                ? t('Net sales before tax: invoices minus credit notes.')
                : t('Net purchases before tax: bills minus debit notes.')
        "
        :route="analysis"
        export-permission="Invoicing Report Export"
        :section="isSales ? t('Sales') : t('Purchases')"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('Report')" for="side">
                <BaseSelect id="side" v-model="filters.side" class="w-40">
                    <option value="sales">{{ t('Sales') }}</option>
                    <option value="purchases">{{ t('Purchases') }}</option>
                </BaseSelect>
            </BaseField>
            <BaseField :label="t('Group by')" for="group_by">
                <BaseSelect
                    id="group_by"
                    v-model="filters.group_by"
                    class="w-40"
                >
                    <option value="contact">{{ t('Contact') }}</option>
                    <option value="item">{{ t('Item') }}</option>
                </BaseSelect>
            </BaseField>
            <BaseField :label="t('From')" for="from">
                <BaseInput id="from" v-model="filters.from" type="date" />
            </BaseField>
            <BaseField :label="t('To')" for="to">
                <BaseInput id="to" v-model="filters.to" type="date" />
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
                            <th class="th-cell">{{ t('Code') }}</th>
                            <th class="th-cell">
                                {{
                                    byItem
                                        ? t('Item')
                                        : isSales
                                          ? t('Customer')
                                          : t('Vendor')
                                }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Documents') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Net amount') }}
                            </th>
                            <template v-if="withMargin">
                                <th class="th-cell text-end">
                                    {{ t('Cost') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Gross margin') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Margin %') }}
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="row in report.rows" :key="row.key">
                            <td class="td-cell font-mono" dir="ltr">
                                {{ row.code }}
                            </td>
                            <td
                                class="td-cell text-gray-800 dark:text-white/90"
                            >
                                {{ row.name }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ row.documents }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ quantity(row.quantity) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(row.net) }}
                            </td>
                            <template v-if="withMargin">
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.cost) }}
                                </td>
                                <td
                                    :class="[
                                        'td-cell text-end font-mono',
                                        Number(row.margin) < 0
                                            ? 'text-error-600 dark:text-error-500'
                                            : '',
                                    ]"
                                    dir="ltr"
                                >
                                    {{ amount(row.margin) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ marginPercent(row.net, row.margin) }}
                                </td>
                            </template>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="3">
                                {{ t('Total') }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ quantity(report.totals.quantity) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.totals.net) }}
                            </td>
                            <template v-if="withMargin">
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(report.totals.cost) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(report.totals.margin) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        marginPercent(
                                            report.totals.net,
                                            report.totals.margin,
                                        )
                                    }}
                                </td>
                            </template>
                        </tr>
                    </tfoot>
                </table>
                <EmptyState
                    v-else
                    :message="t('No posted documents in this period.')"
                />
            </div>
        </DataCard>
    </ReportShell>
</template>
