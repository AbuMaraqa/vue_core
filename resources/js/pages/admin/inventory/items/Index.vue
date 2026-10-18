<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ArrowDownUp from '~icons/solar/sort-vertical-linear';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import ImportDialog from '@/components/common/ImportDialog.vue';
import MobileRecordCard from '@/components/common/MobileRecordCard.vue';
import MobileRecordList from '@/components/common/MobileRecordList.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import {
    create,
    destroy,
    edit,
    exportMethod,
    importMethod,
    importTemplate,
    index,
    show,
} from '@/routes/admin/inventory/items';
import type { ComboboxOption, Item, Paginated, SelectOption } from '@/types';

const props = defineProps<{
    items: Paginated<Item>;
    categories: ComboboxOption[];
    types: SelectOption[];
    filters: {
        search: string | null;
        category: string | null;
        type: string | null;
        status: string | null;
        low_stock: boolean;
        sort: string;
    };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters, sortBy, activeCount } = useIndexFilters(
    index,
    {
        search: props.filters.search,
        category: props.filters.category,
        type: props.filters.type,
        status: props.filters.status,
        low_stock: props.filters.low_stock ? '1' : null,
    },
    { sort: props.filters.sort },
);

const lowStock = computed({
    get: (): boolean => filters.low_stock === '1',
    set: (value: boolean) => (filters.low_stock = value ? '1' : ''),
});

const deletion = useConfirmDelete((item: Item) => destroy({ item: item.id }));

const importColumns = [
    'code',
    'name_ar',
    'name_en',
    'type',
    'category',
    'unit',
    'barcode',
    'sale_price',
    'purchase_price',
    'reorder_level',
];

const amount = (value: string | null | undefined): string =>
    formatDecimal(
        value ?? '0',
        locale.value,
        props.baseCurrency.decimal_places,
    );

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string | null | undefined): string =>
    formatDecimal(
        value ?? '0',
        locale.value,
        /\.\d{2}\d*[1-9]/.test(value ?? '') ? 4 : 2,
    );

function isLow(item: Item): boolean {
    return (
        item.reorder_level !== null &&
        Number(item.on_hand ?? 0) <= Number(item.reorder_level)
    );
}
</script>

<template>
    <div>
        <PageHeader :title="t('Items')" :breadcrumbs="[{ title: t('Items') }]">
            <template #actions>
                <ImportDialog
                    v-if="can('Item Import')"
                    :title="t('Import items')"
                    :import-route="importMethod"
                    :template-route="importTemplate"
                    :columns="importColumns"
                />
                <ExportMenu v-if="can('Item Export')" :route="exportMethod" />
                <BaseButton
                    v-if="can('Item Create')"
                    variant="outline"
                    :href="create(undefined, { query: { type: 'service' } })"
                >
                    <Plus class="size-5" />
                    {{ t('Add service') }}
                </BaseButton>
                <BaseButton v-if="can('Item Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('Add item') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="items" :active-filters="activeCount">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by name, code or barcode...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.category" class="sm:w-44">
                    <option value="">{{ t('All categories') }}</option>
                    <option
                        v-for="category in categories"
                        :key="category.value"
                        :value="String(category.value)"
                    >
                        {{ category.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.type" class="sm:w-36">
                    <option value="">{{ t('All types') }}</option>
                    <option
                        v-for="type in types"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ type.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.status" class="sm:w-36">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="active">{{ t('Active') }}</option>
                    <option value="inactive">{{ t('Inactive') }}</option>
                </BaseSelect>
                <BaseCheckbox v-model="lowStock">
                    {{ t('Low stock') }}
                </BaseCheckbox>
            </template>

            <MobileRecordList v-if="items.data.length">
                <MobileRecordCard
                    v-for="item in items.data"
                    :key="item.id"
                    :href="show({ item: item.id })"
                    :title="item.name"
                    :subtitle="item.category"
                    :meta="
                        item.type === 'stock'
                            ? `${t('On hand')}: ${quantity(item.on_hand)}`
                            : null
                    "
                    :alert="item.type === 'stock' && isLow(item)"
                    :amount="amount(item.sale_price)"
                    :currency="baseCurrency.code"
                >
                    <BaseBadge
                        :color="item.type === 'stock' ? 'primary' : 'info'"
                    >
                        {{ item.type_label }}
                    </BaseBadge>
                    <BaseBadge v-if="!item.is_active" color="light">
                        {{ t('Inactive') }}
                    </BaseBadge>
                </MobileRecordCard>
            </MobileRecordList>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table
                    v-if="items.data.length"
                    class="hidden min-w-full sm:table"
                >
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('code')"
                                >
                                    {{ t('Code') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Unit') }}</th>
                            <th class="th-cell text-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('sale_price')"
                                >
                                    {{ t('Sale price') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell text-end">{{ t('On hand') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Stock value') }}
                                <span class="text-theme-xs font-normal">
                                    ({{ baseCurrency.code }})
                                </span>
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="item in items.data"
                            :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell font-mono" dir="ltr">
                                {{ item.code }}
                            </td>
                            <td class="td-cell">
                                <Link
                                    :href="show({ item: item.id })"
                                    class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90"
                                >
                                    {{ item.name }}
                                </Link>
                                <span v-if="!item.is_active" class="ms-2">
                                    <BaseBadge color="light">
                                        {{ t('Inactive') }}
                                    </BaseBadge>
                                </span>
                                <span
                                    v-if="item.category"
                                    class="block text-theme-xs"
                                >
                                    {{ item.category }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        item.type === 'stock'
                                            ? 'primary'
                                            : 'info'
                                    "
                                >
                                    {{ item.type_label }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">{{ item.unit ?? '—' }}</td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(item.sale_price) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono"
                                dir="ltr"
                                :class="{
                                    'text-error-600 dark:text-error-500':
                                        item.type === 'stock' && isLow(item),
                                }"
                            >
                                {{
                                    item.type === 'stock'
                                        ? quantity(item.on_hand)
                                        : '—'
                                }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    item.type === 'stock'
                                        ? amount(item.stock_value)
                                        : '—'
                                }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <BaseIconButton
                                        v-if="item.can.update"
                                        :href="edit({ item: item.id })"
                                        :label="t('Edit')"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="item.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(item)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No items found.')">
                    <BaseButton
                        v-if="can('Item Create')"
                        size="sm"
                        :href="create()"
                    >
                        <Plus class="size-5" />
                        {{ t('Add item') }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete item')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: deletion.target?.name ?? '' },
                )
            "
            :processing="deletion.processing"
            @update:open="(open) => !open && deletion.cancel()"
            @confirm="deletion.confirm"
        />
    </div>
</template>
