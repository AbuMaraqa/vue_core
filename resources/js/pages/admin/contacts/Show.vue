<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DocumentAdd from '~icons/solar/document-add-linear';
import Filter from '~icons/solar/filter-linear';
import History from '~icons/solar/history-linear';
import Pencil from '~icons/solar/pen-linear';
import WalletMoney from '~icons/solar/wallet-money-linear';
import { computed } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import ShareButton from '@/components/common/ShareButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useReportFilters } from '@/composables/useReportFilters';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { edit, index, pdf, show } from '@/routes/admin/contacts';
import { create as createBill } from '@/routes/admin/purchases/bills';
import { create as createVendorPayment } from '@/routes/admin/purchases/payments';
import { create as createInvoice } from '@/routes/admin/sales/invoices';
import { create as createReceipt } from '@/routes/admin/sales/receipts';
import type { Contact, ContactBalances, Resource } from '@/types';
import type { RouteQueryOptions } from '@/wayfinder';

type StatementLine = {
    entry_id: number;
    date: string;
    number: string | null;
    description: string | null;
    reference: string | null;
    debit: string;
    credit: string;
    balance: string;
    foreign: {
        currency: string;
        decimal_places: number;
        amount: string;
    } | null;
};

const props = defineProps<{
    contact: Resource<Contact>;
    balances: ContactBalances;
    statement: {
        opening: string;
        closing: string;
        total_debit: string;
        total_credit: string;
        lines: StatementLine[];
        truncated: boolean;
    };
    filters: { from: string; to: string };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

const contact = computed(() => props.contact.data);

const statementRoute = {
    url: (_args?: undefined, options?: RouteQueryOptions): string =>
        show.url({ contact: props.contact.data.id }, options),
};

const { filters, apply } = useReportFilters(statementRoute, {
    ...props.filters,
});

/**
 * The query that opens a sales or purchases form for this contact.
 */
const forContact = computed(() => ({
    query: { contact_id: props.contact.data.id },
}));

const openingFor = computed(() => ({
    query: { contact_id: props.contact.data.id, opening: 1 },
}));

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * How much of the credit limit the receivable uses, when there is a limit.
 */
const creditUsed = computed((): number | null => {
    const limit = Number(contact.value.credit_limit);

    if (!contact.value.credit_limit || limit <= 0) {
        return null;
    }

    return Math.round(
        (Math.max(Number(props.balances.receivable), 0) / limit) * 100,
    );
});

const details = computed(() =>
    [
        { label: t('Kind'), value: contact.value.kind_label },
        { label: t('Contact person'), value: contact.value.contact_person },
        { label: t('Email'), value: contact.value.email, ltr: true },
        { label: t('Phone'), value: contact.value.phone, ltr: true },
        { label: t('Mobile'), value: contact.value.mobile, ltr: true },
        { label: t('Tax number'), value: contact.value.tax_number, ltr: true },
        {
            label: t('Currency'),
            value: contact.value.currency ?? t('Base currency'),
        },
        {
            label: t('Payment terms'),
            value: contact.value.payment_terms_days
                ? t(':days days', { days: contact.value.payment_terms_days })
                : t('Due on receipt'),
        },
        {
            label: t('Receivable account'),
            value:
                !simpleMode.value && contact.value.is_customer
                    ? (contact.value.receivable_account ?? t('Default account'))
                    : null,
        },
        {
            label: t('Payable account'),
            value:
                !simpleMode.value && contact.value.is_vendor
                    ? (contact.value.payable_account ?? t('Default account'))
                    : null,
        },
        {
            label: t('Address'),
            value:
                [
                    contact.value.address,
                    contact.value.city,
                    contact.value.country,
                ]
                    .filter(Boolean)
                    .join('، ') || null,
        },
    ].filter((detail) => detail.value),
);
</script>

<template>
    <div>
        <PageHeader
            :title="contact.name"
            :breadcrumbs="[
                { title: t('Customers & Vendors'), href: index() },
                { title: contact.name },
            ]"
        >
            <template #actions>
                <PrintButton
                    :url="
                        pdf.url(
                            { contact: contact.id },
                            { query: { ...props.filters } },
                        )
                    "
                />
                <ShareButton
                    :url="
                        pdf.url(
                            { contact: contact.id },
                            { query: { ...props.filters } },
                        )
                    "
                    :filename="`${t('Account statement')} - ${contact.name}`"
                    :text="
                        t('Account statement of :name — balance :amount', {
                            name: contact.name,
                            amount: `${amount(balances.balance)} ${baseCurrency.code}`,
                        })
                    "
                    :phone="contact.mobile ?? contact.phone"
                />
                <ExportMenu
                    v-if="can('Contact Export')"
                    :route="statementRoute"
                />
                <template v-if="contact.is_customer">
                    <BaseButton
                        v-if="can('Customer Receipt Create')"
                        variant="outline"
                        :href="createReceipt(undefined, forContact)"
                    >
                        <WalletMoney class="size-5" />
                        {{ t('Record receipt') }}
                    </BaseButton>
                    <BaseButton
                        v-if="can('Sales Invoice Create')"
                        variant="outline"
                        :href="createInvoice(undefined, openingFor)"
                    >
                        <History class="size-5" />
                        {{
                            contact.is_vendor
                                ? t('Opening receivable')
                                : t('Opening balance')
                        }}
                    </BaseButton>
                    <BaseButton
                        v-if="can('Sales Invoice Create')"
                        :href="createInvoice(undefined, forContact)"
                    >
                        <DocumentAdd class="size-5" />
                        {{ t('New invoice') }}
                    </BaseButton>
                </template>
                <template v-if="contact.is_vendor">
                    <BaseButton
                        v-if="can('Vendor Payment Create')"
                        variant="outline"
                        :href="createVendorPayment(undefined, forContact)"
                    >
                        <WalletMoney class="size-5" />
                        {{ t('Record payment') }}
                    </BaseButton>
                    <BaseButton
                        v-if="can('Purchase Bill Create')"
                        variant="outline"
                        :href="createBill(undefined, openingFor)"
                    >
                        <History class="size-5" />
                        {{
                            contact.is_customer
                                ? t('Opening payable')
                                : t('Opening balance')
                        }}
                    </BaseButton>
                    <BaseButton
                        v-if="can('Purchase Bill Create')"
                        :href="createBill(undefined, forContact)"
                    >
                        <DocumentAdd class="size-5" />
                        {{ t('New bill') }}
                    </BaseButton>
                </template>
                <BaseButton
                    v-if="contact.can.update"
                    variant="outline"
                    :href="edit({ contact: contact.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Net balance') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(balances.balance) }}
                    <span class="text-theme-xs font-normal text-gray-400">{{
                        baseCurrency.code
                    }}</span>
                </p>
                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                    {{
                        Number(balances.balance) >= 0
                            ? t('Owed to us')
                            : t('We owe them')
                    }}
                </p>
            </div>
            <div
                v-if="contact.is_customer"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ simpleMode ? t('Owed to us') : t('Receivable') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(balances.receivable) }}
                </p>
            </div>
            <div
                v-if="contact.is_vendor"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ simpleMode ? t('We owe them') : t('Payable') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(balances.payable) }}
                </p>
            </div>
            <div
                v-if="creditUsed !== null"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Credit limit') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(contact.credit_limit ?? '0') }}
                </p>
                <div
                    class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800"
                >
                    <div
                        class="h-1.5 rounded-full"
                        :class="
                            creditUsed >= 100
                                ? 'bg-error-500'
                                : creditUsed >= 80
                                  ? 'bg-warning-500'
                                  : 'bg-success-500'
                        "
                        :style="{ width: `${Math.min(creditUsed, 100)}%` }"
                    />
                </div>
                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                    {{ t(':percent% used', { percent: creditUsed }) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <BaseCard :title="t('Details')" class="xl:col-span-1">
                <template #actions>
                    <div class="flex gap-1">
                        <BaseBadge v-if="contact.is_customer" color="primary">{{
                            t('Customer')
                        }}</BaseBadge>
                        <BaseBadge v-if="contact.is_vendor" color="warning">{{
                            t('Vendor')
                        }}</BaseBadge>
                        <BaseBadge v-if="!contact.is_active" color="light">{{
                            t('Inactive')
                        }}</BaseBadge>
                    </div>
                </template>
                <dl class="space-y-3 px-6 pb-6 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Code') }}
                        </dt>
                        <dd
                            class="font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ contact.code }}
                        </dd>
                    </div>
                    <div
                        v-for="detail in details"
                        :key="detail.label"
                        class="flex justify-between gap-4"
                    >
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">
                            {{ detail.label }}
                        </dt>
                        <dd
                            class="text-end text-gray-800 dark:text-white/90"
                            :dir="detail.ltr ? 'ltr' : undefined"
                        >
                            {{ detail.value }}
                        </dd>
                    </div>
                    <div
                        v-if="contact.notes"
                        class="border-t border-gray-100 pt-3 dark:border-gray-800"
                    >
                        <dt class="mb-1 text-gray-500 dark:text-gray-400">
                            {{ t('Notes') }}
                        </dt>
                        <dd
                            class="whitespace-pre-line text-gray-800 dark:text-white/90"
                        >
                            {{ contact.notes }}
                        </dd>
                    </div>
                </dl>
            </BaseCard>

            <DataCard class="xl:col-span-2">
                <form
                    class="flex flex-wrap items-end gap-4 px-5 py-4 sm:px-6"
                    @submit.prevent="apply"
                >
                    <div class="me-auto">
                        <h3
                            class="font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ t('Statement of Account') }}
                        </h3>
                        <p
                            class="text-theme-sm text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t('Amounts in :currency', {
                                    currency: baseCurrency.code,
                                })
                            }}
                        </p>
                    </div>
                    <BaseField :label="t('From')" for="from"
                        ><BaseInput
                            id="from"
                            v-model="filters.from"
                            type="date"
                    /></BaseField>
                    <BaseField :label="t('To')" for="to"
                        ><BaseInput id="to" v-model="filters.to" type="date"
                    /></BaseField>
                    <BaseButton type="submit" size="sm">
                        <Filter class="size-4" />
                        {{ t('Apply') }}
                    </BaseButton>
                </form>
                <BaseAlert
                    v-if="statement.truncated"
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
                                    {{ t('Debit') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Credit') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Balance') }}
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
                                    {{ amount(statement.opening) }}
                                </td>
                            </tr>
                            <tr
                                v-for="(line, position) in statement.lines"
                                :key="position"
                            >
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(line.date, locale) }}
                                </td>
                                <td class="td-cell">
                                    <Link
                                        v-if="can('Journal Entry View List')"
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
                                    <span v-else dir="ltr">{{
                                        line.number
                                    }}</span>
                                </td>
                                <td class="max-w-xs td-cell">
                                    <span class="block truncate">{{
                                        line.description ?? '—'
                                    }}</span>
                                    <span
                                        v-if="line.foreign"
                                        class="block text-theme-xs text-gray-400"
                                        dir="ltr"
                                    >
                                        {{
                                            formatDecimal(
                                                line.foreign.amount,
                                                locale,
                                                line.foreign.decimal_places,
                                            )
                                        }}
                                        {{ line.foreign.currency }}
                                    </span>
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        Number(line.debit)
                                            ? amount(line.debit)
                                            : ''
                                    }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        Number(line.credit)
                                            ? amount(line.credit)
                                            : ''
                                    }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(line.balance) }}
                                </td>
                            </tr>
                            <tr v-if="!statement.lines.length">
                                <td colspan="6">
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
                                    {{ amount(statement.total_debit) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(statement.total_credit) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(statement.closing) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </DataCard>
        </div>
    </div>
</template>
