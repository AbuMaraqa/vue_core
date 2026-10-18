<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ArrowDownUp from '~icons/solar/sort-vertical-linear';
import Plus from '~icons/solar/add-linear';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
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
import { create, index, show } from '@/routes/admin/assets/assets';
import type { ComboboxOption, Paginated, SelectOption } from '@/types';
import type { Asset, BaseCurrency } from '@/types/assets';

const props = defineProps<{
    assets: Paginated<Asset>;
    categories: ComboboxOption[];
    statuses: SelectOption[];
    filters: {
        search: string | null;
        category: string | null;
        status: string | null;
    };
    baseCurrency: BaseCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters, sortBy } = useIndexFilters(index, {
    search: props.filters.search,
    category: props.filters.category,
    status: props.filters.status,
});

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Fixed Assets')"
            :breadcrumbs="[{ title: t('Fixed Assets') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Asset Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('Register asset') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="assets">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by number, name, serial or location...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.category" class="sm:w-48">
                    <option value="">{{ t('All categories') }}</option>
                    <option
                        v-for="category in categories"
                        :key="category.value"
                        :value="String(category.value)"
                    >
                        {{ category.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.status" class="sm:w-44">
                    <option value="">{{ t('All statuses') }}</option>
                    <option
                        v-for="status in statuses"
                        :key="status.value"
                        :value="String(status.value)"
                    >
                        {{ status.label }}
                    </option>
                </BaseSelect>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="assets.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('number')"
                                >
                                    {{ t('Number') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell">{{ t('Asset') }}</th>
                            <th class="th-cell">{{ t('Category') }}</th>
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('acquisition_date')"
                                >
                                    {{ t('Acquired') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell text-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('cost')"
                                >
                                    {{ t('Cost') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Accumulated depreciation') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Book value') }}
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
                            v-for="asset in assets.data"
                            :key="asset.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ asset: asset.id })"
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ asset.number }}
                                </Link>
                            </td>
                            <td class="td-cell">
                                <span
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ asset.name }}
                                </span>
                                <span
                                    v-if="asset.serial_number || asset.location"
                                    class="block text-theme-xs"
                                >
                                    <span
                                        v-if="asset.serial_number"
                                        class="font-mono"
                                        dir="ltr"
                                    >
                                        {{ asset.serial_number }}
                                    </span>
                                    <span
                                        v-if="
                                            asset.serial_number &&
                                            asset.location
                                        "
                                    >
                                        ·
                                    </span>
                                    <span v-if="asset.location">
                                        {{ asset.location }}
                                    </span>
                                </span>
                            </td>
                            <td class="td-cell">{{ asset.category ?? '—' }}</td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(asset.acquisition_date, locale) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(asset.cost) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(asset.accumulated_depreciation) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono font-medium text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(asset.book_value) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="asset.status_color">
                                    {{ asset.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No assets found.')" />
            </div>
        </DataCard>
    </div>
</template>
