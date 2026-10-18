<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
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
import { destroy, store, update } from '@/routes/admin/payroll/components';
import type { ComboboxOption, SelectOption, Translations } from '@/types';
import type {
    PayCalculation,
    PayComponentRow,
    PayComponentType,
} from '@/types/payroll';

const props = defineProps<{
    components: PayComponentRow[];
    types: SelectOption[];
    calculations: SelectOption[];
    accounts: ComboboxOption[];
}>();

const { t } = useTranslations();
const { can } = usePermissions();

/**
 * What each type does to a payslip, in one line.
 */
const typeExplanations = computed<Record<PayComponentType, string>>(() => ({
    earning: t(
        'Added to the pay, e.g. allowances, overtime and bonuses. An expense.',
    ),
    deduction: t(
        'Taken from the pay, e.g. employee social security, income tax or an advance being recovered.',
    ),
    employer_contribution: t(
        'Paid by the company on top of the pay, e.g. employer social security. Not part of the net pay.',
    ),
}));

const typeOptions = computed(() =>
    props.types.map((type) => ({
        value: type.value as PayComponentType,
        label: type.label,
        explanation: typeExplanations.value[type.value as PayComponentType],
    })),
);

function typeColor(type: PayComponentType): 'success' | 'warning' | 'info' {
    if (type === 'earning') {
        return 'success';
    }

    return type === 'deduction' ? 'warning' : 'info';
}

const editing = ref<PayComponentRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    name: {} as Translations,
    type: 'earning' as PayComponentType,
    calculation: 'fixed' as PayCalculation,
    default_value: '',
    account_id: null as number | null,
    liability_account_id: null as number | null,
    is_active: true,
});

const isContribution = computed(() => form.type === 'employer_contribution');
const isPercent = computed(() => form.calculation === 'percent_of_basic');

function openForm(component: PayComponentRow | null = null): void {
    editing.value = component;
    form.clearErrors();
    form.name = { ...component?.name_translations };
    form.type = component?.type ?? 'earning';
    form.calculation = component?.calculation ?? 'fixed';
    form.default_value = component?.default_value ?? '';
    form.account_id = component?.account_id ?? null;
    form.liability_account_id = component?.liability_account_id ?? null;
    form.is_active = component?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        liability_account_id:
            data.type === 'employer_contribution'
                ? data.liability_account_id
                : null,
    })).submit(
        editing.value ? update({ component: editing.value.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => (isFormOpen.value = false),
        },
    );
}

function defaultValue(component: PayComponentRow): string {
    return component.calculation === 'percent_of_basic'
        ? `${Number(component.default_value)}%`
        : String(Number(component.default_value));
}

const deletion = useConfirmDelete((component: PayComponentRow) =>
    destroy({ component: component.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Pay Components')"
            :description="
                t(
                    'Allowances, deductions and employer contributions, and the accounts they post to.',
                )
            "
            :breadcrumbs="[{ title: t('Pay Components') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Pay Component Create')"
                    @click="openForm()"
                >
                    <Plus class="size-5" />
                    {{ t('Add component') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="components.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Calculation') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Default value') }}
                            </th>
                            <th class="th-cell">{{ t('Accounts') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="component in components" :key="component.id">
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ component.name }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="typeColor(component.type)">
                                    {{ component.type_label }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                {{ component.calculation_label }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ defaultValue(component) }}
                            </td>
                            <td class="td-cell">
                                <span class="block">{{
                                    component.account
                                }}</span>
                                <span
                                    v-if="component.liability_account"
                                    class="block text-theme-xs text-gray-400"
                                >
                                    {{ t('Payable') }}:
                                    {{ component.liability_account }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        component.is_active
                                            ? 'success'
                                            : 'light'
                                    "
                                >
                                    {{
                                        component.is_active
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
                                        v-if="can('Pay Component Update')"
                                        :label="t('Edit')"
                                        @click="openForm(component)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Pay Component Delete')"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(component)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No pay components yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit component') : t('Add component')"
            max-width="lg"
        >
            <form
                id="pay-component-form"
                class="space-y-5"
                @submit.prevent="submit"
            >
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />

                <div>
                    <p
                        class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        {{ t('Type') }}
                        <span class="text-error-500">*</span>
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label
                            v-for="option in typeOptions"
                            :key="option.value"
                            :class="[
                                'flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-sm',
                                form.type === option.value
                                    ? 'border-brand-300 bg-brand-50 dark:border-brand-800 dark:bg-brand-500/10'
                                    : 'border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]',
                            ]"
                        >
                            <span class="flex items-center gap-2">
                                <input
                                    v-model="form.type"
                                    type="radio"
                                    name="type"
                                    :value="option.value"
                                    class="size-4 accent-brand-500"
                                />
                                <span
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ option.label }}
                                </span>
                            </span>
                            <span
                                class="text-theme-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ option.explanation }}
                            </span>
                        </label>
                    </div>
                    <BaseInputError
                        :message="form.errors.type"
                        class="mt-1.5"
                    />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        :label="t('Calculation')"
                        for="calculation"
                        required
                        :error="form.errors.calculation"
                    >
                        <BaseSelect id="calculation" v-model="form.calculation">
                            <option
                                v-for="calculation in calculations"
                                :key="calculation.value"
                                :value="calculation.value"
                            >
                                {{ calculation.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="t('Default value')"
                        for="default_value"
                        required
                        :hint="
                            isPercent
                                ? t('Percent of the basic salary, e.g. 7.5.')
                                : t('A monthly amount in the base currency.')
                        "
                        :error="form.errors.default_value"
                    >
                        <div class="relative">
                            <BaseInput
                                id="default_value"
                                v-model="form.default_value"
                                type="number"
                                min="0"
                                step="0.0001"
                                dir="ltr"
                                :class="['font-mono', { 'pe-9': isPercent }]"
                                :invalid="Boolean(form.errors.default_value)"
                            />
                            <span
                                v-if="isPercent"
                                class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-gray-400"
                            >
                                %
                            </span>
                        </div>
                    </BaseField>
                    <BaseField
                        :label="
                            form.type === 'deduction'
                                ? t('Account credited')
                                : t('Expense account')
                        "
                        required
                        :hint="
                            form.type === 'deduction'
                                ? t(
                                      'e.g. social security payable, or employee advances to recover an advance.',
                                  )
                                : undefined
                        "
                        :error="form.errors.account_id"
                        :class="{ 'sm:col-span-2': !isContribution }"
                    >
                        <BaseCombobox
                            v-model="form.account_id"
                            :options="accounts"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.account_id)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="isContribution"
                        :label="t('Liability account')"
                        required
                        :hint="
                            t(
                                'Where the amount owed to the authority is credited.',
                            )
                        "
                        :error="form.errors.liability_account_id"
                    >
                        <BaseCombobox
                            v-model="form.liability_account_id"
                            :options="accounts"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.liability_account_id)"
                        />
                    </BaseField>
                </div>

                <BaseCheckbox v-model="form.is_active">
                    {{ t('Active') }}
                </BaseCheckbox>
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
                    form="pay-component-form"
                    size="sm"
                    :loading="form.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete component')"
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
