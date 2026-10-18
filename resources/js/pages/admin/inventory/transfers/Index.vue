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
import { formatDate, formatNumber } from '@/lib/format';
import { create, index, show } from '@/routes/admin/inventory/transfers';
import type {
    ComboboxOption,
    Paginated,
    SelectOption,
    StockTransfer,
} from '@/types';

const props = defineProps<{
    transfers: Paginated<StockTransfer>;
    warehouses: ComboboxOption[];
    statuses: SelectOption[];
    filters: {
        search: string | null;
        warehouse: string | null;
        status: string | null;
        from: string | null;
        to: string | null;
    };
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
            :title="t('Stock Transfers')"
            :breadcrumbs="[{ title: t('Stock Transfers') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Stock Transfer Create')"
                    :href="create()"
                >
                    <Plus class="size-5" />
                    {{ t('New transfer') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="transfers">
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
                <table v-if="transfers.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('From warehouse') }}</th>
                            <th class="th-cell">{{ t('To warehouse') }}</th>
                            <th class="th-cell">{{ t('Description') }}</th>
                            <th class="th-cell text-end">{{ t('Lines') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="transfer in transfers.data"
                            :key="transfer.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ transfer: transfer.id })"
                                    class="font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ transfer.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(transfer.date, locale) }}
                            </td>
                            <td class="td-cell">
                                {{ transfer.from_warehouse ?? '—' }}
                            </td>
                            <td class="td-cell">
                                {{ transfer.to_warehouse ?? '—' }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ transfer.description ?? '—' }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatNumber(
                                        transfer.lines_count ?? 0,
                                        locale,
                                    )
                                }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="transfer.status_color">
                                    {{ transfer.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No stock transfers found.')" />
            </div>
        </DataCard>
    </div>
</template>
