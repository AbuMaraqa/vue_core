<script setup lang="ts">
import ReportShell from '@/components/accounting/ReportShell.vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { stockCard } from '@/routes/admin/inventory/reports';
import type { ComboboxOption, ItemOption } from '@/types';

type Line = {
    id: number;
    date: string;
    type: string;
    document: string | null;
    warehouse: string;
    description: string | null;
    in: string | null;
    out: string | null;
    unit_cost: string;
    value: string;
    balance_quantity: string;
    balance_value: string;
};

const props = defineProps<{
    report: {
        item: { id: number; label: string; unit: string | null };
        opening_quantity: string;
        opening_value: string;
        closing_quantity: string;
        closing_value: string;
        total_in: string;
        total_out: string;
        lines: Line[];
        truncated: boolean;
    } | null;
    filters: {
        from: string;
        to: string;
        item_id: number | null;
        warehouse_id: number | null;
    };
    selectedItem: ItemOption | null;
    warehouses: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const searchItems = useItemSearch('stock');

const { filters, apply } = useReportFilters(stockCard, { ...props.filters });

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string | null): string =>
    value === null
        ? ''
        : formatDecimal(
              value,
              locale.value,
              /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2,
          );
</script>

<template>
    <ReportShell
        :title="t('Stock Card')"
        :route="report ? stockCard : undefined"
        export-permission="Inventory Report Export"
        :section="t('Inventory')"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('Item')" class="w-full sm:w-80">
                <BaseCombobox
                    v-model="filters.item_id"
                    :options="selectedItem ? [selectedItem] : []"
                    :search="searchItems"
                    :placeholder="t('Choose an item')"
                />
            </BaseField>
            <BaseField :label="t('Warehouse')" class="w-56">
                <BaseCombobox
                    v-model="filters.warehouse_id"
                    :options="warehouses"
                    clearable
                    :placeholder="t('All warehouses')"
                />
            </BaseField>
            <BaseField :label="t('From')" for="from">
                <BaseInput id="from" v-model="filters.from" type="date" />
            </BaseField>
            <BaseField :label="t('To')" for="to">
                <BaseInput id="to" v-model="filters.to" type="date" />
            </BaseField>
        </template>

        <DataCard v-if="report">
            <div class="px-5 py-3 sm:px-6">
                <h3 class="font-semibold text-gray-800 dark:text-white/90">
                    {{ report.item.label }}
                </h3>
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    <span v-if="report.item.unit">
                        {{ t('Unit') }}: {{ report.item.unit }} ·
                    </span>
                    {{
                        t('Amounts in :currency', {
                            currency: baseCurrency.code,
                        })
                    }}
                </p>
            </div>
            <BaseAlert
                v-if="report.truncated"
                variant="warning"
                class="mx-5 mb-3"
                :title="
                    t(
                        'Only the first lines are shown. Narrow the period or export the report.',
                    )
                "
            />
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Document') }}</th>
                            <th class="th-cell">{{ t('Warehouse') }}</th>
                            <th class="th-cell">{{ t('Description') }}</th>
                            <th class="th-cell text-end">{{ t('In') }}</th>
                            <th class="th-cell text-end">{{ t('Out') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Unit cost') }}
                            </th>
                            <th class="th-cell text-end">{{ t('Value') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Balance quantity') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Balance value') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr class="bg-gray-50 font-medium dark:bg-white/[0.02]">
                            <td class="td-cell" colspan="9">
                                {{ t('Opening balance') }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ quantity(report.opening_quantity) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(report.opening_value) }}
                            </td>
                        </tr>
                        <tr v-for="line in report.lines" :key="line.id">
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(line.date, locale) }}
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ line.type }}
                            </td>
                            <td class="td-cell font-mono" dir="ltr">
                                {{ line.document ?? '—' }}
                            </td>
                            <td class="td-cell">{{ line.warehouse }}</td>
                            <td class="max-w-xs truncate td-cell">
                                {{ line.description ?? '—' }}
                            </td>
                            <td
                                class="td-cell text-end font-mono text-success-600 dark:text-success-500"
                                dir="ltr"
                            >
                                {{ quantity(line.in) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono text-error-600 dark:text-error-500"
                                dir="ltr"
                            >
                                {{ quantity(line.out) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ formatDecimal(line.unit_cost, locale, 4) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(line.value) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ quantity(line.balance_quantity) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(line.balance_value) }}
                            </td>
                        </tr>
                        <tr v-if="!report.lines.length">
                            <td colspan="11">
                                <EmptyState
                                    :message="t('No movements in this period.')"
                                />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="5">
                                {{ t('Closing balance') }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ quantity(report.total_in) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ quantity(report.total_out) }}
                            </td>
                            <td colspan="2" />
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ quantity(report.closing_quantity) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.closing_value) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </DataCard>
        <DataCard v-else>
            <EmptyState :message="t('Choose an item to see its stock card.')" />
        </DataCard>
    </ReportShell>
</template>
