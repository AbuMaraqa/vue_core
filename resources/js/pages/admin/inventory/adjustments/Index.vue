<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
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
import { create, index, show } from '@/routes/admin/inventory/adjustments';
import type {
    ComboboxOption,
    Paginated,
    SelectOption,
    StockAdjustment,
} from '@/types';

const props = defineProps<{
    adjustments: Paginated<StockAdjustment>;
    warehouses: ComboboxOption[];
    statuses: SelectOption[];
    filters: {
        search: string | null;
        warehouse: string | null;
        status: string | null;
        from: string | null;
        to: string | null;
    };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    warehouse: props.filters.warehouse,
    status: props.filters.status,
    from: props.filters.from,
    to: props.filters.to,
});
</script>

<template>
    <div>
        <PageHeader
            :title="t('Stock Adjustments')"
            :breadcrumbs="[{ title: t('Stock Adjustments') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Stock Adjustment Create')"
                    :href="create()"
                >
                    <Plus class="size-5" />
                    {{ t('New adjustment') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="adjustments">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by number or description...')"
                />
            </template>
            <template #filters>
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
                <BaseSelect v-model="filters.status" class="sm:w-36">
                    <option value="">{{ t('All statuses') }}</option>
                    <option
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
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
                <table v-if="adjustments.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Warehouse') }}</th>
                            <th class="th-cell">{{ t('Description') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Total value') }}
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
                            v-for="adjustment in adjustments.data"
                            :key="adjustment.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ adjustment: adjustment.id })"
                                    class="font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ adjustment.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(adjustment.date, locale) }}
                            </td>
                            <td class="td-cell">
                                {{ adjustment.warehouse ?? '—' }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ adjustment.description ?? '—' }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatDecimal(
                                        adjustment.total ?? '0',
                                        locale,
                                        baseCurrency.decimal_places,
                                    )
                                }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="adjustment.status_color">
                                    {{ adjustment.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState
                    v-else
                    :message="t('No stock adjustments found.')"
                />
            </div>
        </DataCard>
    </div>
</template>
