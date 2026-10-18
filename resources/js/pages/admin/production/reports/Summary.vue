<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ReportShell from '@/components/accounting/ReportShell.vue';
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
import { formatQuantity } from '@/lib/production';
import { show } from '@/routes/admin/production/orders';
import { summary } from '@/routes/admin/production/reports';
import type { ItemOption, ProductionCurrency } from '@/types';

type OrderRow = {
    id: number;
    number: string;
    completed_on: string | null;
    item: string;
    unit: string | null;
    contact: string | null;
    produced_quantity: string;
    materials_cost: string;
    other_costs: string;
    total_cost: string;
    unit_cost: string;
};

type ProductRow = {
    item_id: number;
    item: string;
    unit: string | null;
    orders: number;
    quantity: string;
    total_cost: string;
    average_unit_cost: string;
};

type MaterialRow = {
    item_id: number;
    item: string;
    unit: string | null;
    quantity: string;
    value: string;
};

const props = defineProps<{
    report: {
        orders: OrderRow[];
        products: ProductRow[];
        materials: MaterialRow[];
        totals: {
            materials_cost: string;
            other_costs: string;
            total_cost: string;
        };
    };
    filters: { from: string; to: string; item_id: number | null };
    selectedItem: ItemOption | null;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const searchItems = useItemSearch('stock');

const { filters, apply } = useReportFilters(summary, { ...props.filters });

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);
</script>

<template>
    <ReportShell
        :title="t('Production Report')"
        :route="summary"
        export-permission="Production Report Export"
        :section="t('Production')"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('Product')" class="w-full sm:w-80">
                <BaseCombobox
                    v-model="filters.item_id"
                    :options="selectedItem ? [selectedItem] : []"
                    :search="searchItems"
                    clearable
                    :placeholder="t('All products')"
                />
            </BaseField>
            <BaseField :label="t('From')" for="from">
                <BaseInput id="from" v-model="filters.from" type="date" />
            </BaseField>
            <BaseField :label="t('To')" for="to">
                <BaseInput id="to" v-model="filters.to" type="date" />
            </BaseField>
        </template>

        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div
                    v-for="card in [
                        {
                            label: t('Materials cost'),
                            value: report.totals.materials_cost,
                        },
                        {
                            label: t('Other costs'),
                            value: report.totals.other_costs,
                        },
                        {
                            label: t('Total production cost'),
                            value: report.totals.total_cost,
                        },
                    ]"
                    :key="card.label"
                    class="rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <span
                        class="text-theme-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ card.label }}
                    </span>
                    <span
                        class="mt-1 block font-mono text-title-sm font-bold text-gray-800 dark:text-white/90"
                        dir="ltr"
                    >
                        {{ amount(card.value) }}
                    </span>
                    <span class="text-theme-xs text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </div>
            </div>

            <DataCard>
                <div class="px-5 py-3 sm:px-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white/90">
                        {{ t('By product') }}
                    </h3>
                </div>
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table v-if="report.products.length" class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Product') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Orders') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Produced quantity') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Total cost') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Average unit cost') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="row in report.products"
                                :key="row.item_id"
                            >
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ row.item }}
                                </td>
                                <td class="td-cell text-end">
                                    {{ row.orders }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ formatQuantity(row.quantity, locale) }}
                                    <span class="text-theme-xs text-gray-400">
                                        {{ row.unit ?? '' }}
                                    </span>
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.total_cost) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono font-semibold text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ amount(row.average_unit_cost) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState
                        v-else
                        :message="t('Nothing was produced in this period.')"
                    />
                </div>
            </DataCard>

            <DataCard v-if="report.orders.length">
                <div class="px-5 py-3 sm:px-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white/90">
                        {{ t('Completed orders') }}
                    </h3>
                </div>
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Number') }}</th>
                                <th class="th-cell">
                                    {{ t('Completed on') }}
                                </th>
                                <th class="th-cell">{{ t('Product') }}</th>
                                <th class="th-cell">{{ t('Customer') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Quantity') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Materials cost') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Other costs') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Total cost') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Unit cost') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="row in report.orders" :key="row.id">
                                <td class="td-cell">
                                    <Link
                                        :href="show({ order: row.id })"
                                        class="font-medium text-brand-500 hover:underline"
                                        dir="ltr"
                                    >
                                        {{ row.number }}
                                    </Link>
                                </td>
                                <td class="td-cell whitespace-nowrap">
                                    {{
                                        row.completed_on
                                            ? formatDate(
                                                  row.completed_on,
                                                  locale,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td class="td-cell">{{ row.item }}</td>
                                <td class="td-cell">
                                    {{ row.contact ?? '—' }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        formatQuantity(
                                            row.produced_quantity,
                                            locale,
                                        )
                                    }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.materials_cost) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.other_costs) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.total_cost) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono font-semibold"
                                    dir="ltr"
                                >
                                    {{ amount(row.unit_cost) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="5">
                                    {{ t('Total') }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(report.totals.materials_cost) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(report.totals.other_costs) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(report.totals.total_cost) }}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </DataCard>

            <DataCard v-if="report.materials.length">
                <div class="px-5 py-3 sm:px-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white/90">
                        {{ t('Materials consumed') }}
                    </h3>
                </div>
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Material') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Quantity used') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="row in report.materials"
                                :key="row.item_id"
                            >
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ row.item }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ formatQuantity(row.quantity, locale) }}
                                    <span class="text-theme-xs text-gray-400">
                                        {{ row.unit ?? '' }}
                                    </span>
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.value) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DataCard>
        </div>
    </ReportShell>
</template>
