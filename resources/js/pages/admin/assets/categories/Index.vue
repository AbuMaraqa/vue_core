<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal, formatNumber } from '@/lib/format';
import { destroy, store, update } from '@/routes/admin/assets/categories';
import type { ComboboxOption, SelectOption, Translations } from '@/types';
import type { AssetCategoryRow } from '@/types/assets';

const props = defineProps<{
    categories: AssetCategoryRow[];
    methods: SelectOption[];
    assetAccounts: ComboboxOption[];
    accumulatedAccounts: ComboboxOption[];
    expenseAccounts: ComboboxOption[];
    defaults: { depreciation_expense_account_id: number | null };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const editing = ref<AssetCategoryRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    name: {} as Translations,
    asset_account_id: null as number | string | null,
    accumulated_depreciation_account_id: null as number | string | null,
    depreciation_expense_account_id: null as number | string | null,
    method: 'straight_line' as string,
    useful_life_months: '' as number | string,
});

function openForm(category: AssetCategoryRow | null = null): void {
    editing.value = category;
    form.clearErrors();
    form.name = { ...category?.name_translations };
    form.asset_account_id = category?.asset_account_id ?? null;
    form.accumulated_depreciation_account_id =
        category?.accumulated_depreciation_account_id ?? null;
    form.depreciation_expense_account_id = category
        ? category.depreciation_expense_account_id
        : props.defaults.depreciation_expense_account_id;
    form.method = category?.method ?? 'straight_line';
    form.useful_life_months = category?.useful_life_months ?? 60;
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

/**
 * A useful life in months and, when it helps, in years.
 */
function lifeLabel(months: number | string): string {
    const count = Number(months);

    if (!(count > 0)) {
        return '';
    }

    const years = formatDecimal(
        count / 12,
        locale.value,
        count % 12 === 0 ? 0 : 2,
    );

    return `${t(':months months', { months: count })} (${t(':years years', { years })})`;
}

const deletion = useConfirmDelete((category: AssetCategoryRow) =>
    destroy({ category: category.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Asset Categories')"
            :breadcrumbs="[{ title: t('Asset Categories') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Asset Category Create')"
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
                            <th class="th-cell">{{ t('Asset account') }}</th>
                            <th class="th-cell">
                                {{ t('Depreciation') }}
                            </th>
                            <th class="th-cell text-end">{{ t('Assets') }}</th>
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
                            <td class="td-cell">
                                {{ category.asset_account }}
                                <span class="block text-theme-xs">
                                    {{
                                        category.accumulated_depreciation_account
                                    }}
                                </span>
                            </td>
                            <td class="td-cell">
                                {{ category.method_label }}
                                <span class="block text-theme-xs">
                                    {{ lifeLabel(category.useful_life_months) }}
                                </span>
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatNumber(category.assets_count, locale)
                                }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="can('Asset Category Update')"
                                        :label="t('Edit')"
                                        @click="openForm(category)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Asset Category Delete')"
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
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        class="sm:col-span-2"
                        :label="t('Asset account')"
                        required
                        :error="form.errors.asset_account_id"
                    >
                        <BaseCombobox
                            v-model="form.asset_account_id"
                            :options="assetAccounts"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.asset_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Accumulated depreciation account')"
                        required
                        :error="form.errors.accumulated_depreciation_account_id"
                    >
                        <BaseCombobox
                            v-model="form.accumulated_depreciation_account_id"
                            :options="accumulatedAccounts"
                            :placeholder="t('Choose an account')"
                            :invalid="
                                Boolean(
                                    form.errors
                                        .accumulated_depreciation_account_id,
                                )
                            "
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Depreciation expense account')"
                        required
                        :error="form.errors.depreciation_expense_account_id"
                    >
                        <BaseCombobox
                            v-model="form.depreciation_expense_account_id"
                            :options="expenseAccounts"
                            :placeholder="t('Choose an account')"
                            :invalid="
                                Boolean(
                                    form.errors.depreciation_expense_account_id,
                                )
                            "
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Depreciation method')"
                        for="method"
                        required
                        :error="form.errors.method"
                    >
                        <BaseSelect
                            id="method"
                            v-model="form.method"
                            :invalid="Boolean(form.errors.method)"
                        >
                            <option
                                v-for="method in methods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="t('Useful life in months')"
                        for="useful_life_months"
                        required
                        :hint="lifeLabel(form.useful_life_months) || undefined"
                        :error="form.errors.useful_life_months"
                    >
                        <BaseInput
                            id="useful_life_months"
                            v-model="form.useful_life_months"
                            type="number"
                            step="1"
                            min="1"
                            max="1200"
                            dir="ltr"
                            class="font-mono"
                            :invalid="Boolean(form.errors.useful_life_months)"
                        />
                    </BaseField>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'New assets of this category start with this method and useful life; each asset can change them.',
                        )
                    }}
                </p>
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
