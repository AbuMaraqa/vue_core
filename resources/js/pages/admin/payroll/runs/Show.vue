<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import Plus from '~icons/solar/add-linear';
import CheckCircle from '~icons/solar/check-circle-linear';
import Pencil from '~icons/solar/pen-linear';
import Printer from '~icons/solar/printer-linear';
import Refresh from '~icons/solar/refresh-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import UndoLeft from '~icons/solar/undo-left-linear';
import WalletMoney from '~icons/solar/wallet-money-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { show as showEmployee } from '@/routes/admin/payroll/employees';
import {
    index,
    pay,
    pdf as runPdf,
    postAndPay,
    post as postRun,
    prepare,
    reverse,
} from '@/routes/admin/payroll/runs';
import {
    pdf as payslipPdf,
    update as updatePayslip,
} from '@/routes/admin/payroll/runs/payslips';
import type { BankAccountOption, ComboboxOption } from '@/types';
import type {
    PayComponentType,
    PayrollCurrency,
    PayrollRun,
    Payslip,
    PayslipComponentOption,
} from '@/types/payroll';
import type { RouteDefinition } from '@/wayfinder';

const props = defineProps<{
    run: PayrollRun;
    payComponents: PayslipComponentOption[];
    bankAccounts: BankAccountOption[];
    baseCurrency: PayrollCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

const amount = (value: string | number): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const periodLabel = computed(() =>
    formatDate(`${props.run.period}-01`, locale.value, {
        month: 'long',
        year: 'numeric',
    }),
);

/**
 * Today in the user's time zone, as YYYY-MM-DD.
 */
function today(): string {
    return new Date().toLocaleDateString('en-CA');
}

const typeLabels = computed<Record<PayComponentType, string>>(() => ({
    earning: t('Earning'),
    deduction: t('Deduction'),
    employer_contribution: t('Employer contribution'),
}));

function typeColor(type: PayComponentType): 'success' | 'warning' | 'info' {
    if (type === 'earning') {
        return 'success';
    }

    return type === 'deduction' ? 'warning' : 'info';
}

/**
 * Employer contributions are a cost to the company, not part of anyone's
 * pay, so simple mode leaves them out of the totals.
 */
const totalCards = computed(() => [
    { label: t('Gross pay'), value: props.run.gross_total },
    { label: t('Deductions'), value: props.run.deductions_total },
    ...(simpleMode.value
        ? []
        : [
              {
                  label: t('Employer contributions'),
                  value: props.run.employer_total,
              },
          ]),
    { label: t('Net pay'), value: props.run.net_total, strong: true },
]);

const details = computed(() =>
    [
        {
            label: t('Period'),
            value: `${formatDate(props.run.period_start, locale.value)} – ${formatDate(props.run.period_end, locale.value)}`,
        },
        {
            label: t('Payment date'),
            value: props.run.payment_date
                ? formatDate(props.run.payment_date, locale.value)
                : null,
        },
        { label: t('Paid from'), value: props.run.bank_account },
        { label: t('Prepared by'), value: props.run.created_by },
    ].filter((detail) => detail.value),
);

/**
 * The footer totals of the payslips table.
 */
const footer = computed(() => {
    const sum = (pick: (payslip: Payslip) => string): number =>
        props.run.payslips.reduce(
            (total, payslip) => total + Number(pick(payslip)),
            0,
        );

    return {
        basic: sum((payslip) => payslip.basic),
        earnings: sum((payslip) => payslip.earnings_total),
        deductions: sum((payslip) => payslip.deductions_total),
        employer: sum((payslip) => payslip.employer_total),
        net: sum((payslip) => payslip.net_pay),
    };
});

const expanded = ref<number[]>([]);

function toggle(payslip: Payslip): void {
    expanded.value = expanded.value.includes(payslip.id)
        ? expanded.value.filter((id) => id !== payslip.id)
        : [...expanded.value, payslip.id];
}

/**
 * Run a step of the payroll (prepare again, post, step back).
 */
const runningStep = ref<string | null>(null);

function runStep(
    step: string,
    route: RouteDefinition<'post'>,
    onFinish?: () => void,
): void {
    router.visit(route, {
        preserveScroll: true,
        onStart: () => (runningStep.value = step),
        onFinish: () => {
            runningStep.value = null;
            onFinish?.();
        },
    });
}

const confirmingPrepare = ref(false);
const confirmingReverse = ref(false);

const reverseStep = computed(() => {
    switch (props.run.status) {
        case 'draft':
            return {
                label: t('Delete draft'),
                message: t(
                    'This draft payroll and its payslips will be deleted.',
                ),
            };
        case 'paid':
            return {
                label: t('Reverse payment'),
                message: t(
                    'The salary payment will be reversed and the payroll returns to posted, ready to be paid again.',
                ),
            };
        default:
            return {
                label: t('Cancel payroll'),
                message: t(
                    'The journal entry of this payroll will be reversed and the payroll cancelled.',
                ),
            };
    }
});

/**
 * Simple mode pays in one step: it posts the payroll first when needed, so
 * "Post" never shows up.
 */
const paysInOneStep = computed(
    () => simpleMode.value && props.run.can.post_and_pay,
);

/**
 * The account suggested for paying: the only one, or in simple mode the
 * first cash box.
 */
function suggestedAccount(): number | null {
    if (props.bankAccounts.length === 1) {
        return props.bankAccounts[0].value;
    }

    if (!simpleMode.value) {
        return null;
    }

    return (
        props.bankAccounts.find((account) => account.type === 'cash')?.value ??
        props.bankAccounts[0]?.value ??
        null
    );
}

const isPayOpen = ref(false);
const payForm = useForm({
    bank_account_id: null as number | null,
    date: today(),
});

function openPay(): void {
    payForm.clearErrors();
    payForm.bank_account_id = suggestedAccount();
    payForm.date = today();
    isPayOpen.value = true;
}

function submitPay(): void {
    const route = paysInOneStep.value
        ? postAndPay({ run: props.run.id })
        : pay({ run: props.run.id });

    payForm.submit(route, {
        preserveScroll: true,
        onSuccess: () => (isPayOpen.value = false),
    });
}

type LineInput = {
    pay_component_id: number | null;
    amount: string;
};

const adjusting = ref<Payslip | null>(null);
const isAdjustOpen = ref(false);
const adjustForm = useForm({
    basic: '',
    lines: [] as LineInput[],
});

function openAdjust(payslip: Payslip): void {
    adjusting.value = payslip;
    adjustForm.clearErrors();
    adjustForm.basic = payslip.basic;
    adjustForm.lines = payslip.lines.map((line): LineInput => ({
        pay_component_id: line.pay_component_id,
        amount: line.amount,
    }));
    isAdjustOpen.value = true;
}

function componentOf(line: LineInput): PayslipComponentOption | undefined {
    return props.payComponents.find(
        (component) => component.value === line.pay_component_id,
    );
}

/**
 * The components a line can choose: those of the other lines are disabled.
 */
function optionsFor(position: number): ComboboxOption[] {
    const taken = adjustForm.lines
        .filter((_, other) => other !== position)
        .map((line) => line.pay_component_id);

    return props.payComponents.map((component) => ({
        value: component.value,
        label: component.label,
        hint: typeLabels.value[component.type],
        disabled: taken.includes(component.value),
    }));
}

function lineError(position: number, field: string): string | undefined {
    return (adjustForm.errors as Record<string, string | undefined>)[
        `lines.${position}.${field}`
    ];
}

/**
 * The payslip's net pay as it will be after the adjustment.
 */
const adjustedNet = computed(() =>
    adjustForm.lines.reduce(
        (net, line) => {
            const type = componentOf(line)?.type;

            if (type === 'earning') {
                return net + Number(line.amount || 0);
            }

            return type === 'deduction' ? net - Number(line.amount || 0) : net;
        },
        Number(adjustForm.basic || 0),
    ),
);

function submitAdjust(): void {
    if (!adjusting.value) {
        return;
    }

    adjustForm.submit(
        updatePayslip({ run: props.run.id, payslip: adjusting.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => (isAdjustOpen.value = false),
        },
    );
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Payroll :period', { period: periodLabel })"
            :breadcrumbs="[
                { title: t('Payroll Runs'), href: index() },
                { title: periodLabel },
            ]"
        >
            <template #actions>
                <PrintButton :url="runPdf.url({ run: run.id })" />
                <BaseButton
                    v-if="run.can.update"
                    variant="outline"
                    @click="confirmingPrepare = true"
                >
                    <Refresh class="size-5" />
                    {{ t('Prepare again') }}
                </BaseButton>
                <BaseButton
                    v-if="run.can.reverse"
                    variant="outline"
                    @click="confirmingReverse = true"
                >
                    <Trash2 v-if="run.status === 'draft'" class="size-5" />
                    <UndoLeft v-else class="size-5" />
                    {{ reverseStep.label }}
                </BaseButton>
                <BaseButton v-if="paysInOneStep" @click="openPay">
                    <WalletMoney class="size-5" />
                    {{ t('Pay salaries') }}
                </BaseButton>
                <BaseButton
                    v-if="!paysInOneStep && run.can.post"
                    :loading="runningStep === 'post'"
                    @click="runStep('post', postRun({ run: run.id }))"
                >
                    <CheckCircle class="size-5" />
                    {{ t('Post') }}
                </BaseButton>
                <BaseButton
                    v-if="!paysInOneStep && run.can.pay"
                    @click="openPay"
                >
                    <WalletMoney class="size-5" />
                    {{ t('Pay salaries') }}
                </BaseButton>
            </template>
        </PageHeader>

        <BaseCard class="mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <BaseBadge :color="run.status_color">
                            {{ run.status_label }}
                        </BaseBadge>
                        <span
                            v-if="run.number"
                            class="font-mono text-sm text-gray-500 dark:text-gray-400"
                            dir="ltr"
                        >
                            № {{ run.number }}
                        </span>
                    </div>
                    <p
                        class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ periodLabel }}
                    </p>
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{
                            t(':count employees', {
                                count: run.employees,
                            })
                        }}
                    </p>
                </div>
                <dl
                    class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2"
                >
                    <div v-for="detail in details" :key="detail.label">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ detail.label }}
                        </dt>
                        <dd class="text-gray-800 dark:text-white/90">
                            {{ detail.value }}
                        </dd>
                    </div>
                    <div v-if="!simpleMode && run.journal_entries.length">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Journal entries') }}
                        </dt>
                        <dd class="flex flex-wrap gap-x-3 gap-y-1">
                            <template
                                v-for="entry in run.journal_entries"
                                :key="entry.id"
                            >
                                <Link
                                    v-if="can('Journal Entry View List')"
                                    :href="
                                        showEntry({ journalEntry: entry.id })
                                    "
                                    :title="entry.description ?? undefined"
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ entry.number ?? entry.id }}
                                </Link>
                                <span
                                    v-else
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ entry.number ?? entry.id }}
                                </span>
                            </template>
                        </dd>
                    </div>
                </dl>
            </div>
        </BaseCard>

        <div
            :class="[
                'mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2',
                simpleMode ? 'xl:grid-cols-3' : 'xl:grid-cols-4',
            ]"
        >
            <div
                v-for="card in totalCards"
                :key="card.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ card.label }}
                </p>
                <p
                    :class="[
                        'mt-2 font-mono text-xl font-semibold',
                        card.strong
                            ? 'text-brand-500 dark:text-brand-400'
                            : 'text-gray-800 dark:text-white/90',
                    ]"
                    dir="ltr"
                >
                    {{ amount(card.value) }}
                    <span class="text-theme-xs font-normal text-gray-400">{{
                        baseCurrency.code
                    }}</span>
                </p>
            </div>
        </div>

        <DataCard>
            <div class="px-5 py-4 sm:px-6">
                <h3 class="font-semibold text-gray-800 dark:text-white/90">
                    {{ t('Payslips') }}
                </h3>
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{
                        run.can.update
                            ? paysInOneStep
                                ? t(
                                      'Review the payslips and adjust them before paying the salaries.',
                                  )
                                : t(
                                      'Review the payslips and adjust them before posting the payroll.',
                                  )
                            : t('Amounts in :currency', {
                                  currency: baseCurrency.code,
                              })
                    }}
                </p>
            </div>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="run.payslips.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="w-10 th-cell" />
                            <th class="th-cell">{{ t('Employee') }}</th>
                            <th class="th-cell">{{ t('Department') }}</th>
                            <th class="th-cell text-end">{{ t('Days') }}</th>
                            <th class="th-cell text-end">{{ t('Basic') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Earnings') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Deductions') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Employer') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Net pay') }}
                            </th>
                            <th class="th-cell" />
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <template
                            v-for="payslip in run.payslips"
                            :key="payslip.id"
                        >
                            <tr
                                class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                            >
                                <td class="td-cell">
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                                        :aria-expanded="
                                            expanded.includes(payslip.id)
                                        "
                                        :aria-label="t('Show lines')"
                                        @click="toggle(payslip)"
                                    >
                                        <ChevronDown
                                            :class="[
                                                'size-4 transition-transform',
                                                {
                                                    'rotate-180':
                                                        expanded.includes(
                                                            payslip.id,
                                                        ),
                                                },
                                            ]"
                                        />
                                    </button>
                                </td>
                                <td class="td-cell">
                                    <Link
                                        v-if="can('Employee View')"
                                        :href="
                                            showEmployee({
                                                employee: payslip.employee_id,
                                            })
                                        "
                                        class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90"
                                    >
                                        {{ payslip.employee }}
                                    </Link>
                                    <span
                                        v-else
                                        class="font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ payslip.employee }}
                                    </span>
                                </td>
                                <td class="td-cell">
                                    {{ payslip.cost_center ?? '—' }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono whitespace-nowrap"
                                    dir="ltr"
                                >
                                    {{ payslip.days_worked }}/{{
                                        payslip.days_in_period
                                    }}
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
                                    {{ amount(payslip.earnings_total) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.deductions_total) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.employer_total) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono font-semibold text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ amount(payslip.net_pay) }}
                                </td>
                                <td class="td-cell">
                                    <div class="flex justify-end">
                                        <a
                                            :href="
                                                payslipPdf.url({
                                                    run: run.id,
                                                    payslip: payslip.id,
                                                })
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            :title="t('Print payslip')"
                                            :aria-label="t('Print payslip')"
                                            class="flex size-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
                                        >
                                            <Printer class="size-4" />
                                        </a>
                                        <BaseIconButton
                                            v-if="run.can.update"
                                            :label="t('Adjust')"
                                            @click="openAdjust(payslip)"
                                        >
                                            <Pencil />
                                        </BaseIconButton>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="expanded.includes(payslip.id)"
                                class="bg-gray-50 dark:bg-white/[0.02]"
                            >
                                <td />
                                <td :colspan="9" class="px-5 py-3">
                                    <ul
                                        v-if="payslip.lines.length"
                                        class="max-w-lg space-y-2 text-sm"
                                    >
                                        <li
                                            v-for="line in payslip.lines"
                                            :key="line.pay_component_id"
                                            class="flex items-center justify-between gap-4"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
                                                <BaseBadge
                                                    :color="
                                                        typeColor(line.type)
                                                    "
                                                >
                                                    {{ typeLabels[line.type] }}
                                                </BaseBadge>
                                                <span
                                                    class="text-gray-700 dark:text-gray-300"
                                                >
                                                    {{ line.name }}
                                                </span>
                                            </span>
                                            <span
                                                class="font-mono text-gray-800 dark:text-white/90"
                                                dir="ltr"
                                            >
                                                {{ amount(line.amount) }}
                                            </span>
                                        </li>
                                    </ul>
                                    <p
                                        v-else
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('Basic salary only.') }}
                                    </p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="4">
                                {{ t('Total') }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(footer.basic) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(footer.earnings) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(footer.deductions) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(footer.employer) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(footer.net) }}
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
                <EmptyState
                    v-else
                    :message="
                        t('No active employees were paid in this period.')
                    "
                />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isAdjustOpen"
            :title="
                t('Adjust payslip of :name', {
                    name: adjusting?.employee ?? '',
                })
            "
            max-width="lg"
        >
            <form
                id="payslip-form"
                class="space-y-5"
                @submit.prevent="submitAdjust"
            >
                <BaseField
                    :label="t('Basic')"
                    for="payslip_basic"
                    required
                    :hint="
                        adjusting
                            ? t(':worked of :days days worked', {
                                  worked: adjusting.days_worked,
                                  days: adjusting.days_in_period,
                              })
                            : undefined
                    "
                    :error="adjustForm.errors.basic"
                    class="sm:w-1/2"
                >
                    <BaseInput
                        id="payslip_basic"
                        v-model="adjustForm.basic"
                        type="number"
                        min="0"
                        step="0.0001"
                        dir="ltr"
                        class="text-end font-mono"
                        :invalid="Boolean(adjustForm.errors.basic)"
                    />
                </BaseField>

                <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                    <table class="w-full min-w-[480px]">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Component') }}</th>
                                <th class="w-40 th-cell text-end">
                                    {{ t('Amount') }}
                                </th>
                                <th class="w-12 th-cell" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(line, position) in adjustForm.lines"
                                :key="rowKey(line)"
                                class="align-top"
                            >
                                <td class="px-1 py-2">
                                    <BaseCombobox
                                        v-model="line.pay_component_id"
                                        :options="optionsFor(position)"
                                        :placeholder="t('Choose a component')"
                                        :invalid="
                                            Boolean(
                                                lineError(
                                                    position,
                                                    'pay_component_id',
                                                ),
                                            )
                                        "
                                    />
                                    <BaseInputError
                                        :message="
                                            lineError(
                                                position,
                                                'pay_component_id',
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-1 py-2">
                                    <BaseInput
                                        v-model="line.amount"
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        dir="ltr"
                                        class="text-end font-mono"
                                        :invalid="
                                            Boolean(
                                                lineError(position, 'amount'),
                                            )
                                        "
                                    />
                                    <BaseInputError
                                        :message="lineError(position, 'amount')"
                                    />
                                </td>
                                <td class="px-1 py-2">
                                    <BaseIconButton
                                        color="danger"
                                        :label="t('Remove')"
                                        @click="
                                            adjustForm.lines.splice(position, 1)
                                        "
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <BaseInputError :message="adjustForm.errors.lines" />

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <BaseButton
                        variant="ghost"
                        size="sm"
                        :disabled="
                            adjustForm.lines.length >= payComponents.length
                        "
                        @click="
                            adjustForm.lines.push({
                                pay_component_id: null,
                                amount: '',
                            })
                        "
                    >
                        <Plus class="size-4" />
                        {{ t('Add component') }}
                    </BaseButton>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Net pay') }}:
                        <span
                            class="font-mono font-semibold text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ amount(adjustedNet) }}
                        </span>
                    </p>
                </div>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isAdjustOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="payslip-form"
                    size="sm"
                    :loading="adjustForm.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <BaseModal
            v-model:open="isPayOpen"
            :title="t('Pay salaries')"
            max-width="md"
        >
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                {{
                    t('Net salaries of :amount :currency for :period.', {
                        amount: amount(run.net_total),
                        currency: baseCurrency.code,
                        period: periodLabel,
                    })
                }}
            </p>
            <form id="pay-form" class="space-y-5" @submit.prevent="submitPay">
                <BaseField
                    :label="t('Paid from')"
                    required
                    :hint="
                        bankAccounts.length
                            ? undefined
                            : t(
                                  'There is no active bank account in the base currency.',
                              )
                    "
                    :error="payForm.errors.bank_account_id"
                >
                    <BaseCombobox
                        v-model="payForm.bank_account_id"
                        :options="bankAccounts"
                        :placeholder="t('Choose an account')"
                        :invalid="Boolean(payForm.errors.bank_account_id)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Date')"
                    for="pay_date"
                    required
                    :error="payForm.errors.date"
                >
                    <BaseInput
                        id="pay_date"
                        v-model="payForm.date"
                        type="date"
                        :min="run.period_start"
                        :invalid="Boolean(payForm.errors.date)"
                    />
                </BaseField>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isPayOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="pay-form"
                    size="sm"
                    :loading="payForm.processing"
                >
                    {{ t('Pay salaries') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            v-model:open="confirmingPrepare"
            :title="t('Prepare again')"
            :message="
                t(
                    'The payslips will be prepared again from the current salaries of the employees. Manual changes to them will be lost.',
                )
            "
            :confirm-label="t('Prepare again')"
            :processing="runningStep === 'prepare'"
            @confirm="
                runStep(
                    'prepare',
                    prepare({ run: run.id }),
                    () => (confirmingPrepare = false),
                )
            "
        />

        <ConfirmDialog
            v-model:open="confirmingReverse"
            :title="reverseStep.label"
            :message="reverseStep.message"
            :confirm-label="reverseStep.label"
            :processing="runningStep === 'reverse'"
            @confirm="
                runStep(
                    'reverse',
                    reverse({ run: run.id }),
                    () => (confirmingReverse = false),
                )
            "
        />
    </div>
</template>
