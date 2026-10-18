<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
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
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, store, update } from '@/routes/admin/accounting/taxes';
import type { ComboboxOption, SelectOption, Translations } from '@/types';

type TaxRow = {
    id: number;
    name: string;
    name_translations: Translations;
    rate: string;
    scope: string;
    scope_label: string;
    sales_account_id: number | null;
    sales_account: string | null;
    purchase_account_id: number | null;
    purchase_account: string | null;
    is_active: boolean;
};

defineProps<{
    taxes: TaxRow[];
    scopes: SelectOption[];
    accounts: ComboboxOption[];
}>();

const { t } = useTranslations();
const { can } = usePermissions();

const editing = ref<TaxRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    name: {} as Translations,
    rate: '',
    scope: 'both',
    sales_account_id: null as number | null,
    purchase_account_id: null as number | null,
    is_active: true,
});

function openForm(tax: TaxRow | null = null): void {
    editing.value = tax;
    form.clearErrors();
    form.name = { ...tax?.name_translations };
    form.rate = tax?.rate ?? '';
    form.scope = tax?.scope ?? 'both';
    form.sales_account_id = tax?.sales_account_id ?? null;
    form.purchase_account_id = tax?.purchase_account_id ?? null;
    form.is_active = tax?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(editing.value ? update({ tax: editing.value.id }) : store(), {
        preserveScroll: true,
        onSuccess: () => (isFormOpen.value = false),
    });
}

const deletion = useConfirmDelete((tax: TaxRow) => destroy({ tax: tax.id }));
</script>

<template>
    <div>
        <PageHeader :title="t('Taxes')" :breadcrumbs="[{ title: t('Taxes') }]">
            <template #actions>
                <BaseButton v-if="can('Tax Create')" @click="openForm()">
                    <Plus class="size-5" />
                    {{ t('Add tax') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="taxes.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Rate') }}</th>
                            <th class="th-cell">{{ t('Applies to') }}</th>
                            <th class="th-cell">
                                {{ t('Sales tax account') }}
                            </th>
                            <th class="th-cell">
                                {{ t('Purchase tax account') }}
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="tax in taxes"
                            :key="tax.id"
                            :class="{ 'opacity-60': !tax.is_active }"
                        >
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ tax.name }}
                                <BaseBadge
                                    v-if="!tax.is_active"
                                    color="light"
                                    class="ms-2"
                                    >{{ t('Inactive') }}</BaseBadge
                                >
                            </td>
                            <td class="td-cell font-mono" dir="ltr">
                                {{ Number(tax.rate) }}%
                            </td>
                            <td class="td-cell">{{ tax.scope_label }}</td>
                            <td class="td-cell">
                                {{ tax.sales_account ?? '—' }}
                            </td>
                            <td class="td-cell">
                                {{ tax.purchase_account ?? '—' }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="can('Tax Update')"
                                        :label="t('Edit')"
                                        @click="openForm(tax)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Tax Delete')"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(tax)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No taxes yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit tax') : t('Add tax')"
            max-width="lg"
        >
            <form id="tax-form" class="space-y-5" @submit.prevent="submit">
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        :label="t('Rate (%)')"
                        for="rate"
                        required
                        :error="form.errors.rate"
                    >
                        <BaseInput
                            id="rate"
                            v-model="form.rate"
                            type="number"
                            step="any"
                            min="0"
                            max="100"
                            dir="ltr"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Applies to')"
                        for="scope"
                        required
                        :error="form.errors.scope"
                    >
                        <BaseSelect id="scope" v-model="form.scope">
                            <option
                                v-for="scope in scopes"
                                :key="scope.value"
                                :value="scope.value"
                            >
                                {{ scope.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                </div>
                <BaseField
                    v-if="form.scope !== 'purchases'"
                    :label="t('Sales tax account')"
                    :hint="
                        t(
                            'Tax charged to customers, owed to the tax authority.',
                        )
                    "
                    :error="form.errors.sales_account_id"
                >
                    <BaseCombobox
                        v-model="form.sales_account_id"
                        :options="accounts"
                        clearable
                        :invalid="Boolean(form.errors.sales_account_id)"
                    />
                </BaseField>
                <BaseField
                    v-if="form.scope !== 'sales'"
                    :label="t('Purchase tax account')"
                    :hint="
                        t(
                            'Tax paid to vendors, recoverable from the tax authority.',
                        )
                    "
                    :error="form.errors.purchase_account_id"
                >
                    <BaseCombobox
                        v-model="form.purchase_account_id"
                        :options="accounts"
                        clearable
                        :invalid="Boolean(form.errors.purchase_account_id)"
                    />
                </BaseField>
                <BaseCheckbox v-model="form.is_active">{{
                    t('Active')
                }}</BaseCheckbox>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isFormOpen = false"
                    >{{ t('Cancel') }}</BaseButton
                >
                <BaseButton
                    type="submit"
                    form="tax-form"
                    size="sm"
                    :loading="form.processing"
                    >{{ t('Save') }}</BaseButton
                >
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete tax')"
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
