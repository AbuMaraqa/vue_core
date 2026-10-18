<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import { useTranslations } from '@/composables/useTranslations';
import { rowKey } from '@/lib/rowKey';
import { index, show, store, update } from '@/routes/admin/payroll/employees';
import type { ComboboxOption } from '@/types';
import type {
    EmployeeForEdit,
    EmployeePaymentMethod,
    PayComponentOption,
    PayComponentType,
} from '@/types/payroll';

const props = defineProps<{
    employee?: EmployeeForEdit;
    costCenters: ComboboxOption[];
    payComponents: PayComponentOption[];
    defaults?: { hire_date: string };
}>();

const { t } = useTranslations();

type ComponentInput = {
    pay_component_id: number | null;
    value: string;
};

const form = useForm({
    code: props.employee?.code ?? '',
    name: props.employee?.name ?? '',
    national_id: props.employee?.national_id ?? '',
    position: props.employee?.position ?? '',
    cost_center_id: props.employee?.cost_center_id ?? (null as number | null),
    hire_date: props.employee?.hire_date ?? props.defaults?.hire_date ?? '',
    termination_date: props.employee?.termination_date ?? '',
    basic_salary: props.employee?.basic_salary ?? '',
    payment_method: (props.employee?.payment_method ??
        'bank') as EmployeePaymentMethod,
    bank_name: props.employee?.bank_name ?? '',
    iban: props.employee?.iban ?? '',
    email: props.employee?.email ?? '',
    phone: props.employee?.phone ?? '',
    notes: props.employee?.notes ?? '',
    is_active: props.employee?.is_active ?? true,
    components: (props.employee?.components.map(
        (component): ComponentInput => ({
            pay_component_id: component.pay_component_id,
            value: component.value,
        }),
    ) ?? []) as ComponentInput[],
});

const isBank = computed(() => form.payment_method === 'bank');

/**
 * The components a row can choose: those already chosen by another row are
 * disabled, and each shows its type under its name.
 */
function optionsFor(position: number): ComboboxOption[] {
    const taken = form.components
        .filter((_, other) => other !== position)
        .map((row) => row.pay_component_id);

    return props.payComponents.map((component) => ({
        value: component.value,
        label: component.label,
        hint: component.type_label,
        disabled: taken.includes(component.value),
    }));
}

function componentOf(row: ComponentInput): PayComponentOption | undefined {
    return props.payComponents.find(
        (component) => component.value === row.pay_component_id,
    );
}

function chooseComponent(
    row: ComponentInput,
    option: ComboboxOption | null,
): void {
    const component = props.payComponents.find(
        (candidate) => candidate.value === option?.value,
    );

    row.value = component?.default_value ?? '';
}

function addComponent(): void {
    form.components.push({ pay_component_id: null, value: '' });
}

function typeColor(
    type: PayComponentType | undefined,
): 'success' | 'warning' | 'info' {
    if (type === 'earning') {
        return 'success';
    }

    return type === 'deduction' ? 'warning' : 'info';
}

function componentError(position: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `components.${position}.${field}`
    ];
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        bank_name: data.payment_method === 'bank' ? data.bank_name : '',
        iban: data.payment_method === 'bank' ? data.iban : '',
    })).submit(
        props.employee ? update({ employee: props.employee.id }) : store(),
        { preserveScroll: true },
    );
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <BaseCard :title="t('Details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <BaseField
                    :label="t('Name')"
                    for="name"
                    required
                    :error="form.errors.name"
                >
                    <BaseInput
                        id="name"
                        v-model="form.name"
                        :invalid="Boolean(form.errors.name)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Code')"
                    for="code"
                    required
                    :error="form.errors.code"
                >
                    <BaseInput
                        id="code"
                        v-model="form.code"
                        dir="ltr"
                        :invalid="Boolean(form.errors.code)"
                    />
                </BaseField>
                <BaseField
                    :label="t('National ID')"
                    for="national_id"
                    :error="form.errors.national_id"
                >
                    <BaseInput
                        id="national_id"
                        v-model="form.national_id"
                        dir="ltr"
                    />
                </BaseField>
                <BaseField
                    :label="t('Position')"
                    for="position"
                    :error="form.errors.position"
                >
                    <BaseInput id="position" v-model="form.position" />
                </BaseField>
                <BaseField
                    :label="t('Department')"
                    for="cost_center_id"
                    :hint="t('Its salary cost is charged to this cost center.')"
                    :error="form.errors.cost_center_id"
                >
                    <BaseCombobox
                        id="cost_center_id"
                        v-model="form.cost_center_id"
                        :options="costCenters"
                        clearable
                        :placeholder="t('No department')"
                        :invalid="Boolean(form.errors.cost_center_id)"
                    />
                </BaseField>
                <div class="flex items-end pb-3">
                    <BaseCheckbox v-model="form.is_active">
                        {{ t('Active') }}
                    </BaseCheckbox>
                </div>
                <BaseField
                    :label="t('Hire date')"
                    for="hire_date"
                    required
                    :error="form.errors.hire_date"
                >
                    <BaseInput
                        id="hire_date"
                        v-model="form.hire_date"
                        type="date"
                        :invalid="Boolean(form.errors.hire_date)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Termination date')"
                    for="termination_date"
                    :hint="
                        t('Leave empty while the employee is still working.')
                    "
                    :error="form.errors.termination_date"
                >
                    <BaseInput
                        id="termination_date"
                        v-model="form.termination_date"
                        type="date"
                        :invalid="Boolean(form.errors.termination_date)"
                    />
                </BaseField>
            </div>
        </BaseCard>

        <BaseCard :title="t('Salary and payment')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <BaseField
                    :label="t('Basic salary')"
                    for="basic_salary"
                    required
                    :hint="t('Monthly, in the base currency.')"
                    :error="form.errors.basic_salary"
                >
                    <BaseInput
                        id="basic_salary"
                        v-model="form.basic_salary"
                        type="number"
                        min="0"
                        step="0.0001"
                        dir="ltr"
                        :invalid="Boolean(form.errors.basic_salary)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Payment method')"
                    for="payment_method"
                    required
                    :error="form.errors.payment_method"
                >
                    <BaseSelect
                        id="payment_method"
                        v-model="form.payment_method"
                    >
                        <option value="bank">{{ t('Bank transfer') }}</option>
                        <option value="cash">{{ t('Cash') }}</option>
                    </BaseSelect>
                </BaseField>
                <template v-if="isBank">
                    <BaseField
                        :label="t('Bank name')"
                        for="bank_name"
                        :error="form.errors.bank_name"
                    >
                        <BaseInput id="bank_name" v-model="form.bank_name" />
                    </BaseField>
                    <BaseField
                        :label="t('IBAN')"
                        for="iban"
                        :error="form.errors.iban"
                    >
                        <BaseInput
                            id="iban"
                            v-model="form.iban"
                            dir="ltr"
                            class="font-mono"
                            :invalid="Boolean(form.errors.iban)"
                        />
                    </BaseField>
                </template>
            </div>
        </BaseCard>

        <BaseCard
            :title="t('Pay components')"
            :description="
                t(
                    'Allowances, deductions and employer contributions this employee receives every month.',
                )
            "
        >
            <div
                v-if="form.components.length"
                class="-mx-2 custom-scrollbar overflow-x-auto px-2"
            >
                <table class="w-full min-w-[560px]">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Component') }}</th>
                            <th class="w-40 th-cell">{{ t('Type') }}</th>
                            <th class="w-44 th-cell text-end">
                                {{ t('Value') }}
                            </th>
                            <th class="w-12 th-cell" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, position) in form.components"
                            :key="rowKey(row)"
                            class="align-top"
                        >
                            <td class="px-1 py-2">
                                <BaseCombobox
                                    v-model="row.pay_component_id"
                                    :options="optionsFor(position)"
                                    :placeholder="t('Choose a component')"
                                    :invalid="
                                        Boolean(
                                            componentError(
                                                position,
                                                'pay_component_id',
                                            ),
                                        )
                                    "
                                    @select="
                                        (option) => chooseComponent(row, option)
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        componentError(
                                            position,
                                            'pay_component_id',
                                        )
                                    "
                                />
                            </td>
                            <td class="px-3 py-4">
                                <BaseBadge
                                    v-if="componentOf(row)"
                                    :color="typeColor(componentOf(row)?.type)"
                                >
                                    {{ componentOf(row)?.type_label }}
                                </BaseBadge>
                            </td>
                            <td class="px-1 py-2">
                                <div class="relative">
                                    <BaseInput
                                        v-model="row.value"
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        dir="ltr"
                                        :class="[
                                            'text-end font-mono',
                                            {
                                                'pe-9':
                                                    componentOf(row)
                                                        ?.calculation ===
                                                    'percent_of_basic',
                                            },
                                        ]"
                                        :invalid="
                                            Boolean(
                                                componentError(
                                                    position,
                                                    'value',
                                                ),
                                            )
                                        "
                                    />
                                    <span
                                        v-if="
                                            componentOf(row)?.calculation ===
                                            'percent_of_basic'
                                        "
                                        class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-gray-400"
                                    >
                                        %
                                    </span>
                                </div>
                                <BaseInputError
                                    :message="componentError(position, 'value')"
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseIconButton
                                    color="danger"
                                    :label="t('Remove')"
                                    @click="form.components.splice(position, 1)"
                                >
                                    <Trash2 />
                                </BaseIconButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                {{
                    t(
                        'No pay components. The employee receives the basic salary only.',
                    )
                }}
            </p>
            <BaseInputError :message="form.errors.components" />
            <div class="mt-3">
                <BaseButton
                    variant="ghost"
                    size="sm"
                    :disabled="form.components.length >= payComponents.length"
                    @click="addComponent"
                >
                    <Plus class="size-4" />
                    {{ t('Add component') }}
                </BaseButton>
            </div>
        </BaseCard>

        <BaseCard :title="t('Contact information')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <BaseField
                    :label="t('Email')"
                    for="email"
                    :error="form.errors.email"
                >
                    <BaseInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        dir="ltr"
                        :invalid="Boolean(form.errors.email)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Phone')"
                    for="phone"
                    :error="form.errors.phone"
                >
                    <BaseInput id="phone" v-model="form.phone" dir="ltr" />
                </BaseField>
                <BaseField
                    :label="t('Notes')"
                    for="notes"
                    class="sm:col-span-2"
                    :error="form.errors.notes"
                >
                    <BaseTextarea id="notes" v-model="form.notes" rows="3" />
                </BaseField>
            </div>
        </BaseCard>

        <div class="flex justify-end gap-3">
            <BaseButton
                variant="outline"
                :href="employee ? show({ employee: employee.id }) : index()"
            >
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ employee ? t('Save changes') : t('Create') }}
            </BaseButton>
        </div>
    </form>
</template>
