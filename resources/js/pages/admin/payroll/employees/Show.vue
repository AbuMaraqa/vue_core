<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { edit, index } from '@/routes/admin/payroll/employees';
import { show as showRun } from '@/routes/admin/payroll/runs';
import type {
    EmployeeComponent,
    EmployeeDetails,
    EmployeePayslip,
    PayComponentType,
    PayrollCurrency,
} from '@/types/payroll';

const props = defineProps<{
    employee: EmployeeDetails;
    payslips: EmployeePayslip[];
    baseCurrency: PayrollCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const periodLabel = (period: string): string =>
    formatDate(`${period}-01`, locale.value, {
        month: 'long',
        year: 'numeric',
    });

const details = computed(() =>
    [
        {
            label: t('National ID'),
            value: props.employee.national_id,
            ltr: true,
        },
        { label: t('Position'), value: props.employee.position },
        { label: t('Department'), value: props.employee.cost_center },
        {
            label: t('Hire date'),
            value: formatDate(props.employee.hire_date, locale.value),
        },
        {
            label: t('Termination date'),
            value: props.employee.termination_date
                ? formatDate(props.employee.termination_date, locale.value)
                : null,
        },
        {
            label: t('Payment method'),
            value:
                props.employee.payment_method === 'bank'
                    ? t('Bank transfer')
                    : t('Cash'),
        },
        {
            label: t('Bank name'),
            value:
                props.employee.payment_method === 'bank'
                    ? props.employee.bank_name
                    : null,
        },
        {
            label: t('IBAN'),
            value:
                props.employee.payment_method === 'bank'
                    ? props.employee.iban
                    : null,
            ltr: true,
        },
        { label: t('Email'), value: props.employee.email, ltr: true },
        { label: t('Phone'), value: props.employee.phone, ltr: true },
    ].filter((detail) => detail.value),
);

function typeColor(type: PayComponentType): 'success' | 'warning' | 'info' {
    if (type === 'earning') {
        return 'success';
    }

    return type === 'deduction' ? 'warning' : 'info';
}

function componentValue(component: EmployeeComponent): string {
    return component.calculation === 'percent_of_basic'
        ? `${Number(component.value)}%`
        : amount(component.value);
}
</script>

<template>
    <div>
        <PageHeader
            :title="employee.name"
            :breadcrumbs="[
                { title: t('Employees'), href: index() },
                { title: employee.name },
            ]"
        >
            <template #actions>
                <BaseButton
                    v-if="employee.can.update"
                    variant="outline"
                    :href="edit({ employee: employee.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-1">
                <BaseCard :title="t('Details')">
                    <template #actions>
                        <BaseBadge
                            :color="employee.is_active ? 'success' : 'light'"
                        >
                            {{
                                employee.is_active ? t('Active') : t('Inactive')
                            }}
                        </BaseBadge>
                    </template>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Code') }}
                            </dt>
                            <dd
                                class="font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ employee.code }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Basic salary') }}
                            </dt>
                            <dd
                                class="font-mono font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(employee.basic_salary) }}
                                <span
                                    class="text-theme-xs font-normal text-gray-400"
                                    >{{ baseCurrency.code }}</span
                                >
                            </dd>
                        </div>
                        <div
                            v-for="detail in details"
                            :key="detail.label"
                            class="flex justify-between gap-4"
                        >
                            <dt
                                class="shrink-0 text-gray-500 dark:text-gray-400"
                            >
                                {{ detail.label }}
                            </dt>
                            <dd
                                :class="[
                                    'text-end text-gray-800 dark:text-white/90',
                                    { 'font-mono': detail.ltr },
                                ]"
                                :dir="detail.ltr ? 'ltr' : undefined"
                            >
                                {{ detail.value }}
                            </dd>
                        </div>
                        <div
                            v-if="employee.notes"
                            class="border-t border-gray-100 pt-3 dark:border-gray-800"
                        >
                            <dt class="mb-1 text-gray-500 dark:text-gray-400">
                                {{ t('Notes') }}
                            </dt>
                            <dd
                                class="whitespace-pre-line text-gray-800 dark:text-white/90"
                            >
                                {{ employee.notes }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard :title="t('Pay components')">
                    <ul
                        v-if="employee.components.length"
                        class="divide-y divide-gray-100 text-sm dark:divide-gray-800"
                    >
                        <li
                            v-for="(component, position) in employee.components"
                            :key="position"
                            class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                        >
                            <div>
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ component.name }}
                                </p>
                                <BaseBadge
                                    :color="typeColor(component.type)"
                                    class="mt-1"
                                >
                                    {{ component.type_label }}
                                </BaseBadge>
                            </div>
                            <span
                                class="font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ componentValue(component) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            t(
                                'No pay components. The employee receives the basic salary only.',
                            )
                        }}
                    </p>
                </BaseCard>
            </div>

            <DataCard class="xl:col-span-2">
                <div class="px-5 py-4 sm:px-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white/90">
                        {{ t('Payslips') }}
                    </h3>
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{
                            t('Amounts in :currency', {
                                currency: baseCurrency.code,
                            })
                        }}
                    </p>
                </div>
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table v-if="payslips.length" class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Period') }}</th>
                                <th class="th-cell">{{ t('Status') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Basic') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Earnings') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Deductions') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Net pay') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="payslip in payslips" :key="payslip.id">
                                <td class="td-cell whitespace-nowrap">
                                    <Link
                                        v-if="can('Payroll Run View')"
                                        :href="showRun({ run: payslip.run_id })"
                                        class="font-medium text-brand-500 hover:underline"
                                    >
                                        {{ periodLabel(payslip.period) }}
                                    </Link>
                                    <span v-else>
                                        {{ periodLabel(payslip.period) }}
                                    </span>
                                </td>
                                <td class="td-cell">
                                    <BaseBadge :color="payslip.status_color">
                                        {{ payslip.status_label }}
                                    </BaseBadge>
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.basic) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.earnings) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.deductions) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono font-semibold text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.net_pay) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState v-else :message="t('No payslips yet.')" />
                </div>
            </DataCard>
        </div>
    </div>
</template>
