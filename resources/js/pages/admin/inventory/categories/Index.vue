<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber } from '@/lib/format';
import { destroy, store, update } from '@/routes/admin/inventory/categories';
import type { ComboboxOption, Translations } from '@/types';

type CategoryRow = {
    id: number;
    name: string;
    name_translations: Translations;
    items_count: number;
    inventory_account_id: number | null;
    cost_of_sales_account_id: number | null;
    sales_account_id: number | null;
    purchase_account_id: number | null;
};

defineProps<{
    categories: CategoryRow[];
    revenueAccounts: ComboboxOption[];
    expenseAccounts: ComboboxOption[];
    inventoryAccounts: ComboboxOption[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const editing = ref<CategoryRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    name: {} as Translations,
    inventory_account_id: null as number | null,
    cost_of_sales_account_id: null as number | null,
    sales_account_id: null as number | null,
    purchase_account_id: null as number | null,
});

function openForm(category: CategoryRow | null = null): void {
    editing.value = category;
    form.clearErrors();
    form.name = { ...category?.name_translations };
    form.inventory_account_id = category?.inventory_account_id ?? null;
    form.cost_of_sales_account_id = category?.cost_of_sales_account_id ?? null;
    form.sales_account_id = category?.sales_account_id ?? null;
    form.purchase_account_id = category?.purchase_account_id ?? null;
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(
        editing.value ? update({ category: editing.value.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => (isFormOpen.value = false),
        },
    );
}

function hasOwnAccounts(category: CategoryRow): boolean {
    return [
        category.inventory_account_id,
        category.cost_of_sales_account_id,
        category.sales_account_id,
        category.purchase_account_id,
    ].some((account) => account !== null);
}

const deletion = useConfirmDelete((category: CategoryRow) =>
    destroy({ category: category.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Item Categories')"
            :breadcrumbs="[{ title: t('Item Categories') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Item Category Create')"
                    @click="openForm()"
                >
                    <Plus class="size-5" />
                    {{ t('Add category') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="categories.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell text-end">{{ t('Items') }}</th>
                            <th class="th-cell">{{ t('Accounts') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="category in categories" :key="category.id">
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ category.name }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ formatNumber(category.items_count, locale) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    v-if="hasOwnAccounts(category)"
                                    color="primary"
                                >
                                    {{ t('Own accounts') }}
                                </BaseBadge>
                                <span v-else>{{ t('Default accounts') }}</span>
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="can('Item Category Update')"
                                        :label="t('Edit')"
                                        @click="openForm(category)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Item Category Delete')"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(category)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No categories yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit category') : t('Add category')"
            max-width="lg"
        >
            <form id="category-form" class="space-y-5" @submit.prevent="submit">
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'Items of this category post to these accounts. Leave empty to use the default accounts.',
                        )
                    }}
                </p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        :label="t('Inventory account')"
                        :error="form.errors.inventory_account_id"
                    >
                        <BaseCombobox
                            v-model="form.inventory_account_id"
                            :options="inventoryAccounts"
                            clearable
                            :placeholder="t('Default account')"
                            :invalid="Boolean(form.errors.inventory_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Cost of sales account')"
                        :error="form.errors.cost_of_sales_account_id"
                    >
                        <BaseCombobox
                            v-model="form.cost_of_sales_account_id"
                            :options="expenseAccounts"
                            clearable
                            :placeholder="t('Default account')"
                            :invalid="
                                Boolean(form.errors.cost_of_sales_account_id)
                            "
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Sales account')"
                        :error="form.errors.sales_account_id"
                    >
                        <BaseCombobox
                            v-model="form.sales_account_id"
                            :options="revenueAccounts"
                            clearable
                            :placeholder="t('Default account')"
                            :invalid="Boolean(form.errors.sales_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Purchase account')"
                        :hint="t('For services bought from vendors.')"
                        :error="form.errors.purchase_account_id"
                    >
                        <BaseCombobox
                            v-model="form.purchase_account_id"
                            :options="expenseAccounts"
                            clearable
                            :placeholder="t('Default account')"
                            :invalid="Boolean(form.errors.purchase_account_id)"
                        />
                    </BaseField>
                </div>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isFormOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="category-form"
                    size="sm"
                    :loading="form.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete category')"
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
