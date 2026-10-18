<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import { computed } from 'vue';
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
import { formatQuantity } from '@/lib/production';
import { create, index, show } from '@/routes/admin/production/orders';
import type {
    ComboboxOption,
    Paginated,
    ProductionCurrency,
    ProductionOrder,
    ProductionOrderStatus,
    SelectOption,
} from '@/types';

const props = defineProps<{
    orders: Paginated<ProductionOrder>;
    statuses: SelectOption[];
    warehouses: ComboboxOption[];
    counts: Partial<Record<ProductionOrderStatus, number>>;
    filters: {
        search: string | null;
        status: string | null;
        warehouse: string | null;
        from: string | null;
        to: string | null;
    };
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    status: props.filters.status,
    warehouse: props.filters.warehouse,
    from: props.filters.from,
    to: props.filters.to,
});

/**
 * One card per active stage, so the workshop sees its load at a glance;
 * clicking a card filters the list.
 */
const stages = computed(() =>
    (['draft', 'in_progress', 'completed'] as const).map((status) => ({
        status,
        label:
            props.statuses.find((option) => option.value === status)?.label ??
            status,
        count: props.counts[status] ?? 0,
    })),
);

function toggleStatus(status: string): void {
    filters.status = filters.status === status ? '' : status;
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Production Orders')"
            :description="
                t(
                    'Turn materials into finished products and know the real cost of each one.',
                )
            "
            :breadcrumbs="[{ title: t('Production Orders') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Production Order Create')"
                    :href="create()"
                >
                    <Plus class="size-5" />
                    {{ t('New production order') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <button
                v-for="stage in stages"
                :key="stage.status"
                type="button"
                class="rounded-2xl border bg-white px-5 py-4 text-start transition dark:bg-white/[0.03]"
                :class="
                    filters.status === stage.status
                        ? 'border-brand-500 ring-1 ring-brand-500'
                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'
                "
                @click="toggleStatus(stage.status)"
            >
                <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ stage.label }}
                </span>
                <span
                    class="mt-1 block text-title-sm font-bold text-gray-800 dark:text-white/90"
                >
                    {{ stage.count }}
                </span>
            </button>
        </div>

        <DataCard :pagination="orders">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by number, reference or description...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.status" class="sm:w-40">
                    <option value="">{{ t('All statuses') }}</option>
                    <option
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.warehouse" class="sm:w-44">
                    <option value="">{{ t('All warehouses') }}</option>
                    <option
                        v-for="warehouse in warehouses"
                        :key="warehouse.value"
                        :value="String(warehouse.value)"
                    >
                        {{ warehouse.label }}
                    </option>
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
                <table v-if="orders.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Product') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="th-cell">{{ t('Customer') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Unit cost') }}
                                <span class="text-theme-xs font-normal">
                                    ({{ baseCurrency.code }})
                                </span>
                            </th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ order: order.id })"
                                    class="font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ order.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(order.date, locale) }}
                            </td>
                            <td class="td-cell">
                                {{ order.item ?? '—' }}
                                <p
                                    v-if="order.description"
                                    class="max-w-xs truncate text-theme-xs text-gray-400"
                                >
                                    {{ order.description }}
                                </p>
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatQuantity(
                                        order.produced_quantity ??
                                            order.quantity,
                                        locale,
                                    )
                                }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ order.unit ?? '' }}
                                </span>
                            </td>
                            <td class="td-cell">
                                {{ order.contact?.label ?? '—' }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    order.status === 'completed'
                                        ? formatDecimal(
                                              order.unit_cost,
                                              locale,
                                              baseCurrency.decimal_places,
                                          )
                                        : '—'
                                }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="order.status_color">
                                    {{ order.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState
                    v-else
                    :message="t('No production orders found.')"
                />
            </div>
        </DataCard>
    </div>
</template>
