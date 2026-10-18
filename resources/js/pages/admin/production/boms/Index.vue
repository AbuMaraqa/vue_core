<script setup lang="ts">
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Factory from '~icons/solar/toolbox-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { formatQuantity } from '@/lib/production';
import { create, destroy, edit, index } from '@/routes/admin/production/boms';
import { create as createOrder } from '@/routes/admin/production/orders';
import type {
    BillOfMaterials,
    BillOfMaterialsEstimate,
    Paginated,
    ProductionCurrency,
} from '@/types';

const props = defineProps<{
    boms: Paginated<BillOfMaterials>;
    estimates: Record<number, BillOfMaterialsEstimate>;
    filters: { search: string | null; status: string | null };
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    status: props.filters.status,
});

const deletion = useConfirmDelete((bom: BillOfMaterials) =>
    destroy({ bom: bom.id }),
);

const amount = (value: string | undefined): string =>
    formatDecimal(
        value ?? '0',
        locale.value,
        props.baseCurrency.decimal_places,
    );
</script>

<template>
    <div>
        <PageHeader
            :title="t('Bills of Materials')"
            :description="
                t(
                    'The recipe of each product: its materials and the other costs of making it.',
                )
            "
            :breadcrumbs="[{ title: t('Bills of Materials') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Bill Of Materials Create')"
                    :href="create()"
                >
                    <Plus class="size-5" />
                    {{ t('New recipe') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="boms">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by code or name...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.status" class="sm:w-40">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="active">{{ t('Active') }}</option>
                    <option value="inactive">{{ t('Inactive') }}</option>
                </BaseSelect>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="boms.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Code') }}</th>
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Product') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Batch quantity') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Materials') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Estimated unit cost') }}
                                <span class="text-theme-xs font-normal">
                                    ({{ baseCurrency.code }})
                                </span>
                            </th>
                            <th class="th-cell">{{ t('Status') }}</th>
                            <th class="th-cell" />
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="bom in boms.data"
                            :key="bom.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ bom.code }}
                            </td>
                            <td class="td-cell">{{ bom.name }}</td>
                            <td class="td-cell">{{ bom.item ?? '—' }}</td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ formatQuantity(bom.quantity, locale) }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ bom.unit ?? '' }}
                                </span>
                            </td>
                            <td class="td-cell text-end">
                                {{ bom.components_count ?? 0 }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(estimates[bom.id]?.unit) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="bom.is_active ? 'success' : 'light'"
                                >
                                    {{
                                        bom.is_active
                                            ? t('Active')
                                            : t('Inactive')
                                    }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="
                                            can('Production Order Create') &&
                                            bom.is_active
                                        "
                                        :href="
                                            createOrder(undefined, {
                                                query: { bom: bom.id },
                                            })
                                        "
                                        :label="t('New production order')"
                                    >
                                        <Factory />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="bom.can.update"
                                        :href="edit({ bom: bom.id })"
                                        :label="t('Edit')"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="bom.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(bom)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No bills of materials yet.')" />
            </div>
        </DataCard>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete recipe')"
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
