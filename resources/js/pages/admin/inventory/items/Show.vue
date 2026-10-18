<script setup lang="ts">
import Chart from '~icons/solar/chart-2-linear';
import Pencil from '~icons/solar/pen-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { edit, index, pdf } from '@/routes/admin/inventory/items';
import { stockCard } from '@/routes/admin/inventory/reports';
import type { Item, Resource } from '@/types';

type StockLevelRow = {
    warehouse_id: number;
    warehouse: string;
    quantity: string;
    average_cost: string;
    value: string;
};

type MovementRow = {
    id: number;
    date: string;
    type: string;
    document: string | null;
    warehouse: string;
    quantity: string;
    value: string;
};

const props = defineProps<{
    item: Resource<Item>;
    accounts: {
        sales: string | null;
        purchase: string | null;
        inventory: string | null;
        cost_of_sales: string | null;
    };
    taxes: { sales: string | null; purchase: string | null };
    levels: StockLevelRow[];
    recentMovements: MovementRow[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const item = computed(() => props.item.data);
const isStock = computed(() => item.value.type === 'stock');

const amount = (value: string | number): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string | number): string =>
    formatDecimal(
        value,
        locale.value,
        /\.\d{2}\d*[1-9]/.test(String(value)) ? 4 : 2,
    );

const totals = computed(() => ({
    quantity: props.levels.reduce(
        (sum, level) => sum + Number(level.quantity),
        0,
    ),
    value: props.levels.reduce((sum, level) => sum + Number(level.value), 0),
}));

const isLow = computed(
    () =>
        isStock.value &&
        item.value.reorder_level !== null &&
        totals.value.quantity <= Number(item.value.reorder_level),
);

const details = computed(() =>
    [
        { label: t('Barcode'), value: item.value.barcode, ltr: true },
        { label: t('Type'), value: item.value.type_label },
        { label: t('Category'), value: item.value.category ?? '—' },
        { label: t('Unit'), value: item.value.unit ?? '—' },
        {
            label: t('Reorder level'),
            value:
                isStock.value && item.value.reorder_level !== null
                    ? quantity(item.value.reorder_level)
                    : null,
            ltr: true,
        },
        { label: t('Sales tax'), value: props.taxes.sales ?? t('No tax') },
        {
            label: t('Purchase tax'),
            value: props.taxes.purchase ?? t('No tax'),
        },
        {
            label: t('Sales account'),
            value: props.accounts.sales ?? t('Default account'),
        },
        {
            label: t('Purchase account'),
            value: isStock.value
                ? null
                : (props.accounts.purchase ?? t('Default account')),
        },
        {
            label: t('Inventory account'),
            value: isStock.value
                ? (props.accounts.inventory ?? t('Default account'))
                : null,
        },
        {
            label: t('Cost of sales account'),
            value: isStock.value
                ? (props.accounts.cost_of_sales ?? t('Default account'))
                : null,
        },
    ].filter((detail) => detail.value),
);
</script>

<template>
    <div>
        <PageHeader
            :title="item.name"
            :breadcrumbs="[
                { title: t('Items'), href: index() },
                { title: item.name },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ item: item.id })" />
                <BaseButton
                    v-if="isStock && can('Inventory Report View')"
                    variant="outline"
                    :href="
                        stockCard(undefined, { query: { item_id: item.id } })
                    "
                >
                    <Chart class="size-5" />
                    {{ t('Stock card') }}
                </BaseButton>
                <BaseButton
                    v-if="item.can.update"
                    variant="outline"
                    :href="edit({ item: item.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-if="isStock"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('On hand') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold"
                    :class="
                        isLow
                            ? 'text-error-600 dark:text-error-500'
                            : 'text-gray-800 dark:text-white/90'
                    "
                    dir="ltr"
                >
                    {{ quantity(totals.quantity) }}
                    <span
                        v-if="item.unit"
                        class="text-theme-xs font-normal text-gray-400"
                    >
                        {{ item.unit }}
                    </span>
                </p>
                <p
                    v-if="isLow"
                    class="mt-1 text-theme-xs text-error-600 dark:text-error-500"
                >
                    {{ t('Below reorder level') }}
                </p>
            </div>
            <div
                v-if="isStock"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Stock value') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(totals.value) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Sale price') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(item.sale_price) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Purchase price') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(item.purchase_price) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <BaseCard :title="t('Details')" class="xl:col-span-1">
                <template #actions>
                    <div class="flex gap-1">
                        <BaseBadge :color="isStock ? 'primary' : 'info'">
                            {{ item.type_label }}
                        </BaseBadge>
                        <BaseBadge v-if="!item.is_active" color="light">
                            {{ t('Inactive') }}
                        </BaseBadge>
                    </div>
                </template>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Code') }}
                        </dt>
                        <dd
                            class="font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ item.code }}
                        </dd>
                    </div>
                    <div
                        v-for="detail in details"
                        :key="detail.label"
                        class="flex justify-between gap-4"
                    >
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">
                            {{ detail.label }}
                        </dt>
                        <dd
                            class="text-end text-gray-800 dark:text-white/90"
                            :dir="detail.ltr ? 'ltr' : undefined"
                        >
                            {{ detail.value }}
                        </dd>
                    </div>
                    <div
                        v-if="item.description"
                        class="border-t border-gray-100 pt-3 dark:border-gray-800"
                    >
                        <dt class="mb-1 text-gray-500 dark:text-gray-400">
                            {{ t('Description') }}
                        </dt>
                        <dd
                            class="whitespace-pre-line text-gray-800 dark:text-white/90"
                        >
                            {{ item.description }}
                        </dd>
                    </div>
                </dl>
            </BaseCard>

            <div class="space-y-6 xl:col-span-2">
                <DataCard v-if="isStock">
                    <div class="px-5 py-4 sm:px-6">
                        <h3
                            class="font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ t('Stock by warehouse') }}
                        </h3>
                    </div>
                    <div class="custom-scrollbar max-w-full overflow-x-auto">
                        <table v-if="levels.length" class="min-w-full">
                            <thead>
                                <tr
                                    class="border-y border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">
                                        {{ t('Warehouse') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Quantity') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Average cost') }}
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
                                    v-for="level in levels"
                                    :key="level.warehouse_id"
                                >
                                    <td class="td-cell">
                                        {{ level.warehouse }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ quantity(level.quantity) }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{
                                            formatDecimal(
                                                level.average_cost,
                                                locale,
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(level.value) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr
                                    class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                                >
                                    <td class="px-5 py-3">{{ t('Total') }}</td>
                                    <td
                                        class="px-5 py-3 text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ quantity(totals.quantity) }}
                                    </td>
                                    <td />
                                    <td
                                        class="px-5 py-3 text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(totals.value) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        <EmptyState
                            v-else
                            :message="t('This item has no stock yet.')"
                        />
                    </div>
                </DataCard>

                <DataCard v-if="isStock">
                    <div class="px-5 py-4 sm:px-6">
                        <h3
                            class="font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ t('Recent movements') }}
                        </h3>
                    </div>
                    <div class="custom-scrollbar max-w-full overflow-x-auto">
                        <table v-if="recentMovements.length" class="min-w-full">
                            <thead>
                                <tr
                                    class="border-y border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">{{ t('Date') }}</th>
                                    <th class="th-cell">{{ t('Type') }}</th>
                                    <th class="th-cell">{{ t('Document') }}</th>
                                    <th class="th-cell">
                                        {{ t('Warehouse') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Quantity') }}
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
                                    v-for="movement in recentMovements"
                                    :key="movement.id"
                                >
                                    <td class="td-cell whitespace-nowrap">
                                        {{ formatDate(movement.date, locale) }}
                                    </td>
                                    <td class="td-cell">{{ movement.type }}</td>
                                    <td class="td-cell font-mono" dir="ltr">
                                        {{ movement.document ?? '—' }}
                                    </td>
                                    <td class="td-cell">
                                        {{ movement.warehouse }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        :class="
                                            Number(movement.quantity) < 0
                                                ? 'text-error-600 dark:text-error-500'
                                                : 'text-success-600 dark:text-success-500'
                                        "
                                        dir="ltr"
                                    >
                                        {{ quantity(movement.quantity) }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(movement.value) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <EmptyState
                            v-else
                            :message="t('No stock movements yet.')"
                        />
                    </div>
                </DataCard>
            </div>
        </div>
    </div>
</template>
