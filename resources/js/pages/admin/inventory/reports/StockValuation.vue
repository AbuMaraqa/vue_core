<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ReportShell from '@/components/accounting/ReportShell.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { stockCard, valuation } from '@/routes/admin/inventory/reports';
import type { ComboboxOption } from '@/types';

type Row = {
    item_id: number;
    code: string;
    name: string;
    category: string | null;
    unit: string | null;
    quantity: string;
    average_cost: string;
    value: string;
    below_reorder: boolean;
};

const props = defineProps<{
    report: { rows: Row[]; total_value: string };
    filters: {
        as_of: string;
        warehouse_id: number | null;
        category_id: number | null;
        include_zero: boolean;
    };
    warehouses: ComboboxOption[];
    categories: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const { filters, apply } = useReportFilters(valuation, { ...props.filters });

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);
</script>

<template>
    <ReportShell
        :title="t('Stock Valuation')"
        :route="valuation"
        export-permission="Inventory Report Export"
        :section="t('Inventory')"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('As of')" for="as_of">
                <BaseInput id="as_of" v-model="filters.as_of" type="date" />
            </BaseField>
            <BaseField :label="t('Warehouse')" class="w-56">
                <BaseCombobox
                    v-model="filters.warehouse_id"
                    :options="warehouses"
                    clearable
                    :placeholder="t('All warehouses')"
                />
            </BaseField>
            <BaseField :label="t('Category')" class="w-56">
                <BaseCombobox
                    v-model="filters.category_id"
                    :options="categories"
                    clearable
                    :placeholder="t('All categories')"
                />
            </BaseField>
            <BaseCheckbox v-model="filters.include_zero" class="mb-3">
                {{ t('Show items without stock') }}
            </BaseCheckbox>
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
                            <th class="th-cell">{{ t('Item') }}</th>
                            <th class="th-cell">{{ t('Category') }}</th>
                            <th class="th-cell">{{ t('Unit') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Average cost') }}
                            </th>
                            <th class="th-cell text-end">{{ t('Value') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="row in report.rows"
                            :key="row.item_id"
                            :class="{
                                'bg-warning-50/50 dark:bg-warning-500/5':
                                    row.below_reorder,
                            }"
                        >
                            <td class="td-cell font-mono" dir="ltr">
                                {{ row.code }}
                            </td>
                            <td class="td-cell">
                                <Link
                                    :href="
                                        stockCard.url(undefined, {
                                            query: {
                                                item_id: row.item_id,
                                                warehouse_id:
                                                    filters.warehouse_id ??
                                                    undefined,
                                                to: filters.as_of,
                                            },
                                        })
                                    "
                                    class="text-gray-800 hover:text-brand-500 dark:text-white/90"
                                >
                                    {{ row.name }}
                                </Link>
                                <BaseBadge
                                    v-if="row.below_reorder"
                                    color="warning"
                                    class="ms-2"
                                >
                                    {{ t('Below reorder level') }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">{{ row.category ?? '—' }}</td>
                            <td class="td-cell">{{ row.unit ?? '—' }}</td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ quantity(row.quantity) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ formatDecimal(row.average_cost, locale, 4) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(row.value) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="6">
                                {{ t('Total') }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.total_value) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <EmptyState v-else :message="t('No stock on this date.')" />
            </div>
        </DataCard>
    </ReportShell>
</template>
