<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import CheckCircle from '~icons/solar/check-circle-linear';
import { computed, ref, watch } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { index, reconcile, show } from '@/routes/admin/banking/accounts';
import { store } from '@/routes/admin/banking/accounts/reconcile';
import type { CurrencyFormat, ReconciliationLine } from '@/types';

const props = defineProps<{
    bankAccount: {
        id: number;
        label: string;
        currency: CurrencyFormat;
        book_balance: string;
    };
    statementDate: string;
    reconciledBalance: string;
    lastReconciliation: {
        statement_date: string;
        statement_balance: string;
    } | null;
    lines: ReconciliationLine[];
    truncated: boolean;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

/**
 * Amounts are compared as whole units of 0.0001, the precision the server
 * stores, so floating point never decides whether the statement balances.
 */
const SCALE = 4;
const ONE = 10n ** BigInt(SCALE);

function toUnits(value: string | number | null | undefined): bigint {
    const match = /^\s*(-)?(\d*)(?:\.(\d*))?\s*$/.exec(String(value ?? ''));

    if (!match || (match[2] === '' && !match[3])) {
        return 0n;
    }

    const [, sign, whole, fraction = ''] = match;
    const units =
        BigInt(whole || '0') * ONE +
        BigInt((fraction + '0'.repeat(SCALE)).slice(0, SCALE));

    return sign ? -units : units;
}

function fromUnits(units: bigint): string {
    const negative = units < 0n;
    const absolute = negative ? -units : units;
    const fraction = (absolute % ONE).toString().padStart(SCALE, '0');

    return `${negative ? '-' : ''}${absolute / ONE}.${fraction}`;
}

const amount = (value: string): string =>
    formatDecimal(
        value,
        locale.value,
        props.bankAccount.currency.decimal_places,
    );

const statementDate = ref(props.statementDate);

const form = useForm({
    statement_date: props.statementDate,
    statement_balance: '',
    line_ids: [] as string[],
});

/**
 * Errors without a field of their own on this page. Broken accounting rules
 * already arrive as a toast.
 */
const otherErrors = computed(() =>
    Object.entries(form.errors)
        .filter(
            ([key]) =>
                !['statement_balance', 'statement_date', 'accounting'].includes(
                    key,
                ),
        )
        .map(([, message]) => message)
        .filter((message): message is string => Boolean(message)),
);

/**
 * A new statement date lists the open lines up to that date.
 */
watch(statementDate, (date) => {
    if (!date || date === props.statementDate) {
        return;
    }

    router.get(
        reconcile.url({ bankAccount: props.bankAccount.id }),
        { statement_date: date },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

watch(
    () => props.lines,
    (lines) => {
        const open = new Set(lines.map((line) => String(line.id)));

        form.line_ids = form.line_ids.filter((id) => open.has(id));
        form.statement_date = props.statementDate;
        statementDate.value = props.statementDate;
    },
);

const allSelected = computed(
    () => props.lines.length > 0 && form.line_ids.length === props.lines.length,
);

function toggleAll(checked: boolean | string[] | undefined): void {
    form.line_ids = checked ? props.lines.map((line) => String(line.id)) : [];
}

const totals = computed(() => {
    const chosen = new Set(form.line_ids);
    const cleared = props.lines
        .filter((line) => chosen.has(String(line.id)))
        .reduce((sum, line) => sum + toUnits(line.amount), 0n);
    const clearedBalance = toUnits(props.reconciledBalance) + cleared;
    const difference = toUnits(form.statement_balance) - clearedBalance;

    return {
        cleared: fromUnits(cleared),
        clearedBalance: fromUnits(clearedBalance),
        difference: fromUnits(difference),
        balanced: difference === 0n,
    };
});

const canFinish = computed(
    () =>
        form.statement_balance !== '' &&
        form.statement_balance !== null &&
        totals.value.balanced,
);

function submit(): void {
    if (!canFinish.value) {
        return;
    }

    form.transform((data) => ({
        ...data,
        line_ids: data.line_ids.map(Number),
    })).submit(store({ bankAccount: props.bankAccount.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Reconcile :account', { account: bankAccount.label })"
            :description="
                t(
                    'Tick the lines that appear on the bank statement until the difference is zero.',
                )
            "
            :breadcrumbs="[
                { title: t('Bank & Cash'), href: index() },
                {
                    title: bankAccount.label,
                    href: show({ bankAccount: bankAccount.id }),
                },
                { title: t('Reconcile') },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseAlert
                v-if="truncated"
                variant="warning"
                :title="
                    t(
                        'Only the oldest open lines are shown. Reconcile an earlier statement date first.',
                    )
                "
            />
            <BaseAlert
                v-if="otherErrors.length"
                variant="error"
                :messages="otherErrors"
            />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <BaseCard :title="t('Bank statement')" class="lg:col-span-1">
                    <div class="space-y-5">
                        <BaseField
                            :label="t('Statement date')"
                            for="statement_date"
                            required
                            :error="form.errors.statement_date"
                        >
                            <BaseInput
                                id="statement_date"
                                v-model="statementDate"
                                type="date"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Statement closing balance')"
                            for="statement_balance"
                            required
                            :hint="bankAccount.currency.code"
                            :error="form.errors.statement_balance"
                        >
                            <BaseInput
                                id="statement_balance"
                                v-model="form.statement_balance"
                                type="number"
                                step="any"
                                dir="ltr"
                                class="font-mono"
                                :invalid="
                                    Boolean(form.errors.statement_balance)
                                "
                            />
                        </BaseField>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ t('Book balance') }}
                                </dt>
                                <dd
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ amount(bankAccount.book_balance) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ t('Last reconciliation') }}
                                </dt>
                                <dd
                                    class="text-end text-gray-800 dark:text-white/90"
                                >
                                    <template v-if="lastReconciliation">
                                        {{
                                            formatDate(
                                                lastReconciliation.statement_date,
                                                locale,
                                            )
                                        }}
                                        <span
                                            class="block font-mono text-theme-xs text-gray-400"
                                            dir="ltr"
                                        >
                                            {{
                                                amount(
                                                    lastReconciliation.statement_balance,
                                                )
                                            }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        {{ t('Never') }}
                                    </template>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </BaseCard>

                <BaseCard :title="t('Summary')" class="lg:col-span-2">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-xl bg-gray-50 p-4 dark:bg-white/[0.03]"
                        >
                            <dt
                                class="text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t('Reconciled opening balance') }}
                            </dt>
                            <dd
                                class="mt-1 font-mono text-lg font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(reconciledBalance) }}
                            </dd>
                        </div>
                        <div
                            class="rounded-xl bg-gray-50 p-4 dark:bg-white/[0.03]"
                        >
                            <dt
                                class="text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t('Ticked lines') }}
                            </dt>
                            <dd
                                class="mt-1 font-mono text-lg font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(totals.cleared) }}
                            </dd>
                        </div>
                        <div
                            class="rounded-xl bg-gray-50 p-4 dark:bg-white/[0.03]"
                        >
                            <dt
                                class="text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t('Cleared balance') }}
                            </dt>
                            <dd
                                class="mt-1 font-mono text-lg font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(totals.clearedBalance) }}
                            </dd>
                        </div>
                        <div
                            :class="[
                                'rounded-xl p-4',
                                totals.balanced
                                    ? 'bg-success-50 dark:bg-success-500/15'
                                    : 'bg-error-50 dark:bg-error-500/15',
                            ]"
                        >
                            <dt
                                class="text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t('Difference') }}
                            </dt>
                            <dd
                                :class="[
                                    'mt-1 flex items-center gap-2 font-mono text-lg font-semibold',
                                    totals.balanced
                                        ? 'text-success-600 dark:text-success-500'
                                        : 'text-error-600 dark:text-error-500',
                                ]"
                            >
                                <span dir="ltr">
                                    {{ amount(totals.difference) }}
                                </span>
                                <CheckCircle
                                    v-if="totals.balanced"
                                    class="size-5"
                                />
                            </dd>
                        </div>
                    </dl>
                </BaseCard>
            </div>

            <BaseCard
                :title="t('Open lines')"
                :description="
                    t('Lines up to the statement date not reconciled yet.')
                "
            >
                <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                    <table v-if="lines.length" class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="w-12 th-cell">
                                    <BaseCheckbox
                                        :model-value="allSelected"
                                        :indeterminate="
                                            form.line_ids.length > 0 &&
                                            !allSelected
                                        "
                                        :aria-label="t('Select all')"
                                        @update:model-value="toggleAll"
                                    />
                                </th>
                                <th class="th-cell">{{ t('Date') }}</th>
                                <th class="th-cell">{{ t('Number') }}</th>
                                <th class="th-cell">{{ t('Description') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Money in') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Money out') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="line in lines"
                                :key="line.id"
                                :class="
                                    form.line_ids.includes(String(line.id))
                                        ? 'bg-brand-25 dark:bg-brand-500/5'
                                        : ''
                                "
                            >
                                <td class="td-cell">
                                    <BaseCheckbox
                                        v-model="form.line_ids"
                                        :value="String(line.id)"
                                        :aria-label="line.number ?? ''"
                                    />
                                </td>
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(line.date, locale) }}
                                </td>
                                <td class="td-cell whitespace-nowrap" dir="ltr">
                                    {{ line.number ?? '—' }}
                                </td>
                                <td class="max-w-xs td-cell">
                                    <span class="block truncate">
                                        {{ line.description ?? '—' }}
                                    </span>
                                    <span
                                        v-if="line.reference"
                                        class="block text-theme-xs text-gray-400"
                                        dir="ltr"
                                    >
                                        {{ line.reference }}
                                    </span>
                                </td>
                                <td
                                    class="td-cell text-end font-mono text-success-600 dark:text-success-500"
                                    dir="ltr"
                                >
                                    {{
                                        toUnits(line.amount) > 0n
                                            ? amount(line.amount)
                                            : ''
                                    }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono text-error-600 dark:text-error-500"
                                    dir="ltr"
                                >
                                    {{
                                        toUnits(line.amount) < 0n
                                            ? amount(
                                                  fromUnits(
                                                      -toUnits(line.amount),
                                                  ),
                                              )
                                            : ''
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState
                        v-else
                        :message="
                            t(
                                'There are no open lines up to the statement date.',
                            )
                        "
                    />
                </div>
            </BaseCard>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <p
                    v-if="!canFinish"
                    class="me-auto text-theme-sm text-gray-500 dark:text-gray-400"
                >
                    {{
                        t(
                            'Enter the statement balance and tick lines until the difference is zero.',
                        )
                    }}
                </p>
                <BaseButton
                    variant="outline"
                    :href="show({ bankAccount: bankAccount.id })"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    :loading="form.processing"
                    :disabled="!canFinish"
                >
                    <CheckCircle class="size-5" />
                    {{ t('Finish') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
