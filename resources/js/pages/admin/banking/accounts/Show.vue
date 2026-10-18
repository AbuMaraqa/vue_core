<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import CheckCircle from '~icons/solar/check-circle-linear';
import ChecklistMinimalistic from '~icons/solar/checklist-minimalistic-linear';
import Filter from '~icons/solar/filter-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import UndoLeft from '~icons/solar/undo-left-linear';
import { computed, ref } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal, formatNumber } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { index, pdf, reconcile, show } from '@/routes/admin/banking/accounts';
import {
    destroy as destroyChequeBook,
    store as storeChequeBook,
    update as updateChequeBook,
} from '@/routes/admin/banking/accounts/cheque-books';
import { destroy as destroyReconciliation } from '@/routes/admin/banking/accounts/reconciliations';
import type {
    BankAccountDetails,
    BankReconciliationSummary,
    BankRegister,
    ChequeBook,
    CurrencyFormat,
} from '@/types';
import type { RouteQueryOptions } from '@/wayfinder';

const props = defineProps<{
    bankAccount: BankAccountDetails;
    register: BankRegister;
    filters: { from: string; to: string };
    chequeBooks: ChequeBook[];
    reconciliations: BankReconciliationSummary[];
    can: { update: boolean; reconcile: boolean };
    baseCurrency: CurrencyFormat;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can: hasPermission } = usePermissions();

/**
 * The register route of this account, shaped like a Wayfinder route without
 * parameters for the filters and the export menu.
 */
const registerRoute = {
    url: (_args?: undefined, options?: RouteQueryOptions): string =>
        show.url({ bankAccount: props.bankAccount.id }, options),
};

const { filters, apply } = useReportFilters(registerRoute, {
    ...props.filters,
});

/**
 * The printable statement of the period shown, not of filters not applied yet.
 */
const statementUrl = computed(() =>
    pdf.url(
        { bankAccount: props.bankAccount.id },
        { query: { from: props.filters.from, to: props.filters.to } },
    ),
);

const amount = (value: string | null): string =>
    value === null
        ? ''
        : formatDecimal(
              value,
              locale.value,
              props.bankAccount.currency.decimal_places,
          );

const details = computed(() =>
    [
        { label: t('Type'), value: props.bankAccount.type_label },
        { label: t('Account'), value: props.bankAccount.account },
        { label: t('Currency'), value: props.bankAccount.currency.code },
        { label: t('Bank'), value: props.bankAccount.bank_name },
        { label: t('Branch'), value: props.bankAccount.branch },
        {
            label: t('Account number'),
            value: props.bankAccount.account_number,
            ltr: true,
        },
        { label: t('IBAN'), value: props.bankAccount.iban, ltr: true },
    ].filter((detail) => detail.value),
);

const chequeBookForm = useForm({
    first_number: '',
    last_number: '',
});

function addChequeBook(): void {
    chequeBookForm.submit(
        storeChequeBook({ bankAccount: props.bankAccount.id }),
        {
            preserveScroll: true,
            onSuccess: () => chequeBookForm.reset(),
        },
    );
}

const togglingBook = ref<number | null>(null);

function toggleChequeBook(book: ChequeBook): void {
    router.visit(
        updateChequeBook({
            bankAccount: props.bankAccount.id,
            chequeBook: book.id,
        }),
        {
            data: { is_active: !book.is_active },
            preserveScroll: true,
            onStart: () => (togglingBook.value = book.id),
            onFinish: () => (togglingBook.value = null),
        },
    );
}

const bookDeletion = useConfirmDelete((book: ChequeBook) =>
    destroyChequeBook({
        bankAccount: props.bankAccount.id,
        chequeBook: book.id,
    }),
);

const reconciliationUndo = useConfirmDelete(
    (reconciliation: BankReconciliationSummary) =>
        destroyReconciliation({
            bankAccount: props.bankAccount.id,
            reconciliation: reconciliation.id,
        }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="bankAccount.name"
            :breadcrumbs="[
                { title: t('Bank & Cash'), href: index() },
                { title: bankAccount.name },
            ]"
        >
            <template #actions>
                <PrintButton :url="statementUrl" />
                <ExportMenu
                    v-if="hasPermission('Bank Account View')"
                    :route="registerRoute"
                />
                <BaseButton
                    v-if="can.reconcile"
                    :href="reconcile({ bankAccount: bankAccount.id })"
                >
                    <ChecklistMinimalistic class="size-5" />
                    {{ t('Reconcile') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Current balance') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(bankAccount.balance) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ bankAccount.currency.code }}
                    </span>
                </p>
                <BaseBadge
                    v-if="!bankAccount.is_active"
                    color="light"
                    class="mt-2"
                >
                    {{ t('Inactive') }}
                </BaseBadge>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Money in') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-success-600 dark:text-success-500"
                    dir="ltr"
                >
                    {{ amount(register.total_in) }}
                </p>
                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                    {{ t('In the selected period') }}
                </p>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Money out') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-error-600 dark:text-error-500"
                    dir="ltr"
                >
                    {{ amount(register.total_out) }}
                </p>
                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                    {{ t('In the selected period') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <DataCard class="xl:col-span-2">
                <form
                    class="flex flex-wrap items-end gap-4 px-5 py-4 sm:px-6"
                    @submit.prevent="apply"
                >
                    <div class="me-auto">
                        <h3
                            class="font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ t('Account register') }}
                        </h3>
                        <p
                            class="text-theme-sm text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t('Amounts in :currency', {
                                    currency: bankAccount.currency.code,
                                })
                            }}
                        </p>
                    </div>
                    <BaseField :label="t('From')" for="from">
                        <BaseInput
                            id="from"
                            v-model="filters.from"
                            type="date"
                        />
                    </BaseField>
                    <BaseField :label="t('To')" for="to">
                        <BaseInput id="to" v-model="filters.to" type="date" />
                    </BaseField>
                    <BaseButton type="submit" size="sm">
                        <Filter class="size-4" />
                        {{ t('Apply') }}
                    </BaseButton>
                </form>
                <BaseAlert
                    v-if="register.truncated"
                    variant="warning"
                    class="mx-5 mb-3"
                    :title="
                        t(
                            'Only the first lines are shown. Narrow the period or export the report.',
                        )
                    "
                />
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Date') }}</th>
                                <th class="th-cell">{{ t('Number') }}</th>
                                <th class="th-cell">{{ t('Description') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Money in') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Money out') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Balance') }}
                                </th>
                                <th class="th-cell text-center">
                                    <span class="sr-only">
                                        {{ t('Reconciled') }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                class="bg-gray-50 font-medium dark:bg-white/[0.02]"
                            >
                                <td class="td-cell" colspan="5">
                                    {{ t('Opening balance') }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(register.opening) }}
                                </td>
                                <td class="td-cell" />
                            </tr>
                            <tr v-for="line in register.lines" :key="line.id">
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(line.date, locale) }}
                                </td>
                                <td class="td-cell whitespace-nowrap">
                                    <Link
                                        v-if="
                                            hasPermission(
                                                'Journal Entry View List',
                                            )
                                        "
                                        :href="
                                            showEntry({
                                                journalEntry: line.entry_id,
                                            })
                                        "
                                        class="text-brand-500 hover:underline"
                                        dir="ltr"
                                    >
                                        {{ line.number }}
                                    </Link>
                                    <span v-else dir="ltr">
                                        {{ line.number }}
                                    </span>
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
                                    {{ amount(line.in) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono text-error-600 dark:text-error-500"
                                    dir="ltr"
                                >
                                    {{ amount(line.out) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(line.balance) }}
                                </td>
                                <td class="td-cell text-center">
                                    <CheckCircle
                                        v-if="line.reconciled"
                                        class="inline size-4 text-success-500"
                                        :aria-label="t('Reconciled')"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!register.lines.length">
                                <td colspan="7">
                                    <EmptyState
                                        :message="
                                            t('No transactions in this period.')
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="3">
                                    {{ t('Closing balance') }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(register.total_in) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(register.total_out) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(register.closing) }}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </DataCard>

            <div class="space-y-6">
                <BaseCard :title="t('Details')">
                    <dl class="space-y-3 text-sm">
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
                                class="text-end text-gray-800 dark:text-white/90"
                                :dir="detail.ltr ? 'ltr' : undefined"
                            >
                                {{ detail.value }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard :title="t('Cheque books')">
                    <ul
                        v-if="chequeBooks.length"
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <li
                            v-for="book in chequeBooks"
                            :key="book.id"
                            class="flex items-center justify-between gap-3 py-3 first:pt-0"
                        >
                            <div>
                                <p
                                    class="font-mono text-sm text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ book.first_number }} –
                                    {{ book.last_number }}
                                </p>
                                <p
                                    class="text-theme-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        t(':count left, next :number', {
                                            count: formatNumber(
                                                book.remaining,
                                                locale,
                                            ),
                                            number: book.next_number,
                                        })
                                    }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <BaseBadge
                                    :color="
                                        book.is_active ? 'success' : 'light'
                                    "
                                >
                                    {{
                                        book.is_active
                                            ? t('Active')
                                            : t('Inactive')
                                    }}
                                </BaseBadge>
                                <template v-if="can.update">
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :loading="togglingBook === book.id"
                                        @click="toggleChequeBook(book)"
                                    >
                                        {{
                                            book.is_active
                                                ? t('Retire')
                                                : t('Activate')
                                        }}
                                    </BaseButton>
                                    <BaseIconButton
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="bookDeletion.ask(book)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </template>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No cheque books yet.') }}
                    </p>

                    <form
                        v-if="can.update"
                        class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800"
                        @submit.prevent="addChequeBook"
                    >
                        <div class="grid grid-cols-2 gap-3">
                            <BaseField
                                :label="t('First number')"
                                for="first_number"
                                required
                            >
                                <BaseInput
                                    id="first_number"
                                    v-model="chequeBookForm.first_number"
                                    type="number"
                                    min="1"
                                    dir="ltr"
                                    :invalid="
                                        Boolean(
                                            chequeBookForm.errors.first_number,
                                        )
                                    "
                                />
                            </BaseField>
                            <BaseField
                                :label="t('Last number')"
                                for="last_number"
                                required
                            >
                                <BaseInput
                                    id="last_number"
                                    v-model="chequeBookForm.last_number"
                                    type="number"
                                    min="1"
                                    dir="ltr"
                                    :invalid="
                                        Boolean(
                                            chequeBookForm.errors.last_number,
                                        )
                                    "
                                />
                            </BaseField>
                        </div>
                        <BaseInputError
                            :message="
                                chequeBookForm.errors.first_number ??
                                chequeBookForm.errors.last_number
                            "
                        />
                        <BaseButton
                            type="submit"
                            size="sm"
                            variant="outline"
                            class="mt-3 w-full"
                            :loading="chequeBookForm.processing"
                        >
                            <Plus class="size-4" />
                            {{ t('Add cheque book') }}
                        </BaseButton>
                    </form>
                </BaseCard>

                <BaseCard :title="t('Reconciliations')">
                    <ul
                        v-if="reconciliations.length"
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <li
                            v-for="(
                                reconciliation, position
                            ) in reconciliations"
                            :key="reconciliation.id"
                            class="flex items-center justify-between gap-3 py-3 first:pt-0"
                        >
                            <div>
                                <p
                                    class="text-sm text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        formatDate(
                                            reconciliation.statement_date,
                                            locale,
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-theme-xs text-gray-500 dark:text-gray-400"
                                >
                                    <span class="font-mono" dir="ltr">
                                        {{
                                            amount(
                                                reconciliation.statement_balance,
                                            )
                                        }}
                                    </span>
                                    <template v-if="reconciliation.created_by">
                                        · {{ reconciliation.created_by }}
                                    </template>
                                </p>
                            </div>
                            <BaseButton
                                v-if="position === 0 && can.reconcile"
                                variant="ghost"
                                size="sm"
                                @click="reconciliationUndo.ask(reconciliation)"
                            >
                                <UndoLeft class="size-4" />
                                {{ t('Undo') }}
                            </BaseButton>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('This account was never reconciled.') }}
                    </p>
                </BaseCard>
            </div>
        </div>

        <ConfirmDialog
            :open="bookDeletion.target !== null"
            :title="t('Delete cheque book')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    {
                        name: bookDeletion.target
                            ? `${bookDeletion.target.first_number} – ${bookDeletion.target.last_number}`
                            : '',
                    },
                )
            "
            :processing="bookDeletion.processing"
            @update:open="(open) => !open && bookDeletion.cancel()"
            @confirm="bookDeletion.confirm"
        />

        <ConfirmDialog
            :open="reconciliationUndo.target !== null"
            :title="t('Undo reconciliation')"
            :message="
                t(
                    'The lines of this reconciliation will be open again for the next one.',
                )
            "
            :confirm-label="t('Undo')"
            :processing="reconciliationUndo.processing"
            @update:open="(open) => !open && reconciliationUndo.cancel()"
            @confirm="reconciliationUndo.confirm"
        />
    </div>
</template>
