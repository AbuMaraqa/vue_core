<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CheckCircle from '~icons/solar/check-circle-linear';
import CloseCircle from '~icons/solar/close-circle-linear';
import DocumentAdd from '~icons/solar/document-add-linear';
import LinkIcon from '~icons/solar/link-linear';
import Pencil from '~icons/solar/pen-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import WalletMoney from '~icons/solar/wallet-money-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import ShareButton from '@/components/common/ShareButton.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import {
    documentRoutes,
    documentRoutesOf,
    paymentRoutes,
    paymentRoutesOf,
} from '@/lib/invoicing-routes';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { show as showContact } from '@/routes/admin/contacts';
import type {
    Invoice,
    InvoiceAllocation,
    InvoiceTypeProps,
    OpenNoteOption,
    Resource,
} from '@/types';

const props = defineProps<{
    type: InvoiceTypeProps;
    invoice: Resource<Invoice>;
    openNotes: OpenNoteOption[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();
const page = usePage();

const invoice = computed(() => props.invoice.data);
const routes = computed(() => documentRoutes(props.type.route));

const decimals = computed(() => invoice.value.currency?.decimal_places ?? 2);

const money = (value: string | number): string =>
    formatDecimal(value, locale.value, decimals.value);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);

const isPosted = computed(() => invoice.value.status === 'posted');
const isForeign = computed(() => Number(invoice.value.exchange_rate) !== 1);
const hasDiscount = computed(() =>
    (invoice.value.lines ?? []).some((line) => Number(line.discount_percent)),
);
const isDue = computed(
    () => isPosted.value && Number(invoice.value.amount_due) > 0,
);

const title = computed(() =>
    invoice.value.number
        ? `${props.type.label} ${invoice.value.number}`
        : t(':document (draft)', { document: props.type.label }),
);

const paymentRoute = computed(() =>
    props.type.is_sales ? 'admin.sales.receipts' : 'admin.purchases.payments',
);

const canRecordPayment = computed(
    () =>
        !props.type.is_return &&
        isDue.value &&
        can(
            props.type.is_sales
                ? 'Customer Receipt Create'
                : 'Vendor Payment Create',
        ),
);

/**
 * The payment form, filled in for this contact and settling this document.
 */
const recordPaymentUrl = computed(() =>
    paymentRoutes(paymentRoute.value).create(undefined, {
        query: {
            contact_id: invoice.value.contact_id,
            invoice_id: invoice.value.id,
        },
    }),
);

const canCreateNote = computed(
    () =>
        !props.type.is_return &&
        isPosted.value &&
        can(`${props.type.permission} Create`),
);

const canApplyNote = computed(
    () => invoice.value.can.settle && isDue.value && props.openNotes.length > 0,
);

/**
 * Posting, deleting and cancelling are plain visits with a busy flag.
 */
const busy = ref<'post' | 'delete' | 'cancel' | null>(null);
const confirmingDelete = ref(false);
const confirmingCancel = ref(false);

function visit(action: 'post' | 'delete' | 'cancel'): void {
    const id = { invoice: invoice.value.id };
    const route =
        action === 'post'
            ? routes.value.post(id)
            : action === 'delete'
              ? routes.value.destroy(id)
              : routes.value.cancel(id);

    router.visit(route, {
        preserveScroll: true,
        onStart: () => (busy.value = action),
        onFinish: () => {
            busy.value = null;
            confirmingDelete.value = false;
            confirmingCancel.value = false;
        },
    });
}

const isApplyingNote = ref(false);

const noteForm = useForm({
    note_id: null as number | null,
    amount: '',
});

function availableOf(noteId: number | null): number {
    return Number(
        props.openNotes.find((note) => note.value === noteId)?.available ?? 0,
    );
}

function suggestAmount(): void {
    const amount = Math.min(
        availableOf(noteForm.note_id),
        Number(invoice.value.amount_due),
    );

    noteForm.amount = amount > 0 ? amount.toFixed(decimals.value) : '';
}

function openApplyNote(): void {
    noteForm.clearErrors();
    noteForm.note_id = props.openNotes[0]?.value ?? null;
    suggestAmount();
    isApplyingNote.value = true;
}

function applyNote(): void {
    noteForm.submit(routes.value.applyNote({ invoice: invoice.value.id }), {
        preserveScroll: true,
        onSuccess: () => (isApplyingNote.value = false),
    });
}

const removal = useConfirmDelete((allocation: InvoiceAllocation) =>
    routes.value.allocations.destroy({
        invoice: invoice.value.id,
        allocation: allocation.id,
    }),
);

const details = computed(() =>
    [
        {
            label: t('Date'),
            value: formatDate(invoice.value.date, locale.value),
        },
        {
            label: t('Due date'),
            value: formatDate(invoice.value.due_date, locale.value),
            alert: invoice.value.is_overdue,
        },
        { label: t('Reference'), value: invoice.value.reference, ltr: true },
        { label: t('Currency'), value: invoice.value.currency?.code },
        {
            label: t('Exchange rate'),
            value: isForeign.value ? invoice.value.exchange_rate : null,
            ltr: true,
        },
        { label: t('Warehouse'), value: invoice.value.warehouse },
    ].filter((detail) => detail.value),
);
</script>

<template>
    <div>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { title: type.plural, href: routes.index() },
                { title: invoice.number ?? t('Draft') },
            ]"
        >
            <template #actions>
                <PrintButton :url="routes.pdf.url({ invoice: invoice.id })" />
                <ShareButton
                    v-if="invoice.number"
                    :url="routes.pdf.url({ invoice: invoice.id })"
                    :filename="invoice.number"
                    :text="
                        t(':document :number — :amount', {
                            document: invoice.type_label,
                            number: invoice.number,
                            amount: `${money(invoice.total)} ${invoice.currency?.code ?? ''}`.trim(),
                        })
                    "
                    :phone="invoice.contact?.phone"
                />
                <BaseButton
                    v-if="invoice.can.update"
                    variant="outline"
                    :href="routes.edit({ invoice: invoice.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
                <BaseButton
                    v-if="invoice.can.post"
                    :loading="busy === 'post'"
                    @click="visit('post')"
                >
                    <CheckCircle class="size-5" />
                    {{ t('Post') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div
            v-if="canRecordPayment && simpleMode"
            class="mb-6 flex flex-col gap-4 rounded-2xl border border-brand-200 bg-brand-25 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-brand-500/30 dark:bg-brand-500/10"
        >
            <div>
                <p class="font-semibold text-gray-800 dark:text-white/90">
                    {{
                        type.is_sales
                            ? t('This invoice is not fully paid yet.')
                            : t('This bill is not fully paid yet.')
                    }}
                </p>
                <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Amount due') }}:
                    <span
                        class="font-mono font-semibold text-gray-800 dark:text-white/90"
                        dir="ltr"
                    >
                        {{ money(invoice.amount_due) }}
                        {{ invoice.currency?.code }}
                    </span>
                </p>
            </div>
            <BaseButton :href="recordPaymentUrl" class="sm:shrink-0">
                <WalletMoney class="size-5" />
                {{ type.is_sales ? t('Receive payment') : t('Pay this bill') }}
            </BaseButton>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <BaseCard>
                    <div
                        class="flex flex-wrap items-start justify-between gap-6 border-b border-gray-100 pb-6 dark:border-gray-800"
                    >
                        <div>
                            <p
                                class="text-lg font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ page.props.name }}
                            </p>
                            <p
                                class="mt-4 text-theme-xs tracking-wide text-gray-400 uppercase"
                            >
                                {{ type.is_sales ? t('Bill to') : t('Vendor') }}
                            </p>
                            <template v-if="invoice.contact">
                                <Link
                                    v-if="can('Contact View')"
                                    :href="
                                        showContact({
                                            contact: invoice.contact.id,
                                        })
                                    "
                                    class="mt-1 block font-medium text-brand-500 hover:underline"
                                >
                                    {{ invoice.contact.name }}
                                </Link>
                                <p
                                    v-else
                                    class="mt-1 font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ invoice.contact.name }}
                                </p>
                                <p
                                    class="font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                                    dir="ltr"
                                >
                                    {{ invoice.contact.code }}
                                </p>
                                <p
                                    v-if="invoice.contact.tax_number"
                                    class="text-theme-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ t('Tax number') }}:
                                    <span dir="ltr">{{
                                        invoice.contact.tax_number
                                    }}</span>
                                </p>
                                <p
                                    v-if="invoice.contact.address"
                                    class="text-theme-sm whitespace-pre-line text-gray-500 dark:text-gray-400"
                                >
                                    {{ invoice.contact.address }}
                                </p>
                            </template>
                        </div>
                        <div class="text-end">
                            <p
                                class="text-title-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ invoice.type_label }}
                            </p>
                            <p
                                class="mt-1 font-mono text-gray-500 dark:text-gray-400"
                                dir="ltr"
                            >
                                {{ invoice.number ?? t('Draft') }}
                            </p>
                            <dl class="mt-4 space-y-1 text-sm">
                                <div
                                    v-for="detail in details"
                                    :key="detail.label"
                                    class="flex justify-end gap-3"
                                >
                                    <dt
                                        class="text-gray-500 dark:text-gray-400"
                                    >
                                        {{ detail.label }}
                                    </dt>
                                    <dd
                                        :class="[
                                            detail.alert
                                                ? 'font-medium text-error-600 dark:text-error-500'
                                                : 'text-gray-800 dark:text-white/90',
                                            { 'font-mono': detail.ltr },
                                        ]"
                                        :dir="detail.ltr ? 'ltr' : undefined"
                                    >
                                        {{ detail.value }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div
                        class="-mx-4 custom-scrollbar overflow-x-auto sm:-mx-6"
                    >
                        <table class="min-w-full">
                            <thead>
                                <tr
                                    class="border-b border-gray-100 dark:border-gray-800"
                                >
                                    <th class="w-10 th-cell">#</th>
                                    <th class="th-cell">
                                        {{ t('Description') }}
                                    </th>
                                    <template v-if="!invoice.is_opening">
                                        <th class="th-cell text-end">
                                            {{ t('Quantity') }}
                                        </th>
                                        <th class="th-cell text-end">
                                            {{ t('Price') }}
                                        </th>
                                        <th
                                            v-if="hasDiscount"
                                            class="th-cell text-end"
                                        >
                                            {{ t('Discount %') }}
                                        </th>
                                        <th class="th-cell text-end">
                                            {{ t('Tax') }}
                                        </th>
                                    </template>
                                    <th class="th-cell text-end">
                                        {{
                                            invoice.is_opening
                                                ? t('Amount')
                                                : t('Total')
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr
                                    v-for="(line, position) in invoice.lines"
                                    :key="line.id"
                                    class="align-top"
                                >
                                    <td class="td-cell text-gray-400">
                                        {{ position + 1 }}
                                    </td>
                                    <td class="td-cell">
                                        <p
                                            class="text-gray-800 dark:text-white/90"
                                        >
                                            {{ line.description ?? '—' }}
                                        </p>
                                        <p
                                            v-if="line.item || line.account"
                                            class="text-theme-xs text-gray-400"
                                        >
                                            <span
                                                v-if="line.item"
                                                class="font-mono"
                                                dir="ltr"
                                                >{{ line.item.code }}</span
                                            >
                                            <template
                                                v-if="line.item && line.account"
                                            >
                                                ·
                                            </template>
                                            <span v-if="line.account">{{
                                                line.account
                                            }}</span>
                                        </p>
                                    </td>
                                    <template v-if="!invoice.is_opening">
                                        <td
                                            class="td-cell text-end font-mono whitespace-nowrap"
                                            dir="ltr"
                                        >
                                            {{ quantity(line.quantity) }}
                                            <span
                                                v-if="line.item?.unit"
                                                class="text-theme-xs text-gray-400"
                                            >
                                                {{ line.item.unit }}
                                            </span>
                                        </td>
                                        <td
                                            class="td-cell text-end font-mono"
                                            dir="ltr"
                                        >
                                            {{ money(line.unit_price) }}
                                        </td>
                                        <td
                                            v-if="hasDiscount"
                                            class="td-cell text-end font-mono"
                                            dir="ltr"
                                        >
                                            {{
                                                Number(line.discount_percent)
                                                    ? `${Number(line.discount_percent)}%`
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="td-cell text-end font-mono"
                                            dir="ltr"
                                        >
                                            {{
                                                Number(line.tax_rate)
                                                    ? `${Number(line.tax_rate)}%`
                                                    : '—'
                                            }}
                                        </td>
                                    </template>
                                    <td
                                        class="td-cell text-end font-mono text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        {{ money(line.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-6 border-t border-gray-100 pt-4 sm:flex-row sm:justify-between dark:border-gray-800"
                    >
                        <div class="max-w-md text-sm">
                            <template v-if="invoice.notes">
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ t('Notes') }}
                                </p>
                                <p
                                    class="mt-1 whitespace-pre-line text-gray-800 dark:text-white/90"
                                >
                                    {{ invoice.notes }}
                                </p>
                            </template>
                            <p
                                v-if="invoice.prices_include_tax"
                                class="mt-2 text-theme-xs text-gray-400"
                            >
                                {{ t('Prices include tax.') }}
                            </p>
                        </div>
                        <dl class="w-full space-y-2 text-sm sm:w-72">
                            <template v-if="!invoice.is_opening">
                                <div
                                    v-if="Number(invoice.discount_total)"
                                    class="flex justify-between gap-4"
                                >
                                    <dt
                                        class="text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('Discount') }}
                                    </dt>
                                    <dd
                                        class="font-mono text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        −{{ money(invoice.discount_total) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt
                                        class="text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('Subtotal') }}
                                    </dt>
                                    <dd
                                        class="font-mono text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        {{ money(invoice.subtotal) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt
                                        class="text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('Tax') }}
                                    </dt>
                                    <dd
                                        class="font-mono text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        {{ money(invoice.tax_total) }}
                                    </dd>
                                </div>
                            </template>
                            <div
                                class="flex justify-between gap-4 border-t border-gray-100 pt-2 text-base font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                            >
                                <dt>{{ t('Total') }}</dt>
                                <dd class="font-mono" dir="ltr">
                                    {{ money(invoice.total) }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        {{ invoice.currency?.code }}
                                    </span>
                                </dd>
                            </div>
                            <template v-if="isPosted">
                                <div class="flex justify-between gap-4">
                                    <dt
                                        class="text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            type.is_return
                                                ? t('Applied')
                                                : t('Paid')
                                        }}
                                    </dt>
                                    <dd
                                        class="font-mono text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        {{ money(invoice.paid_amount) }}
                                    </dd>
                                </div>
                                <div
                                    class="flex justify-between gap-4 font-semibold text-gray-800 dark:text-white/90"
                                >
                                    <dt>
                                        {{
                                            type.is_return
                                                ? t('Not applied')
                                                : t('Amount due')
                                        }}
                                    </dt>
                                    <dd class="font-mono" dir="ltr">
                                        {{ money(invoice.amount_due) }}
                                    </dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </BaseCard>

                <BaseCard
                    v-if="
                        isPosted &&
                        (!type.is_return || invoice.allocations?.length)
                    "
                    :title="t('Settlements')"
                    :description="
                        t('Payments and notes applied to this document.')
                    "
                >
                    <div
                        v-if="invoice.allocations?.length"
                        class="-mx-4 custom-scrollbar overflow-x-auto sm:-mx-6"
                    >
                        <table class="min-w-full">
                            <thead>
                                <tr
                                    class="border-b border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">{{ t('Date') }}</th>
                                    <th class="th-cell">
                                        {{ t('Document') }}
                                    </th>
                                    <th class="th-cell">
                                        {{ t('Exchange difference') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Amount') }}
                                    </th>
                                    <th class="w-12 th-cell" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr
                                    v-for="allocation in invoice.allocations"
                                    :key="allocation.id"
                                >
                                    <td class="td-cell whitespace-nowrap">
                                        {{
                                            formatDate(allocation.date, locale)
                                        }}
                                    </td>
                                    <td class="td-cell">
                                        <Link
                                            v-if="allocation.payment"
                                            :href="
                                                paymentRoutesOf(
                                                    allocation.payment.type,
                                                ).show({
                                                    payment:
                                                        allocation.payment.id,
                                                })
                                            "
                                            class="font-mono text-brand-500 hover:underline"
                                            dir="ltr"
                                        >
                                            {{ allocation.payment.number }}
                                        </Link>
                                        <Link
                                            v-else-if="allocation.credit_note"
                                            :href="
                                                documentRoutesOf(
                                                    allocation.credit_note.type,
                                                ).show({
                                                    invoice:
                                                        allocation.credit_note
                                                            .id,
                                                })
                                            "
                                            class="font-mono text-brand-500 hover:underline"
                                            dir="ltr"
                                        >
                                            {{ allocation.credit_note.number }}
                                        </Link>
                                        <span
                                            class="ms-2 text-theme-xs text-gray-400"
                                        >
                                            {{
                                                allocation.payment
                                                    ? t('Payment')
                                                    : type.counterpart.label
                                            }}
                                        </span>
                                    </td>
                                    <td class="td-cell">
                                        <template
                                            v-if="
                                                allocation.exchange_difference
                                            "
                                        >
                                            <Link
                                                v-if="
                                                    can(
                                                        'Journal Entry View List',
                                                    )
                                                "
                                                :href="
                                                    showEntry({
                                                        journalEntry:
                                                            allocation
                                                                .exchange_difference
                                                                .id,
                                                    })
                                                "
                                                class="text-brand-500 hover:underline"
                                                dir="ltr"
                                            >
                                                {{
                                                    allocation
                                                        .exchange_difference
                                                        .number
                                                }}
                                            </Link>
                                            <span v-else dir="ltr">
                                                {{
                                                    allocation
                                                        .exchange_difference
                                                        .number
                                                }}
                                            </span>
                                        </template>
                                        <span v-else>—</span>
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ money(allocation.amount) }}
                                    </td>
                                    <td class="td-cell">
                                        <BaseIconButton
                                            v-if="invoice.can.settle"
                                            color="danger"
                                            :label="t('Remove')"
                                            @click="removal.ask(allocation)"
                                        >
                                            <Trash2 />
                                        </BaseIconButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState v-else :message="t('Nothing settled yet.')" />
                </BaseCard>

                <BaseCard
                    v-if="type.is_return && invoice.applied_to?.length"
                    :title="t('Applied to')"
                >
                    <div
                        class="-mx-4 custom-scrollbar overflow-x-auto sm:-mx-6"
                    >
                        <table class="min-w-full">
                            <thead>
                                <tr
                                    class="border-b border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">{{ t('Date') }}</th>
                                    <th class="th-cell">
                                        {{ type.counterpart.label }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Amount') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr
                                    v-for="allocation in invoice.applied_to"
                                    :key="allocation.id"
                                >
                                    <td class="td-cell whitespace-nowrap">
                                        {{
                                            formatDate(allocation.date, locale)
                                        }}
                                    </td>
                                    <td class="td-cell">
                                        <Link
                                            :href="
                                                documentRoutesOf(
                                                    allocation.invoice.type,
                                                ).show({
                                                    invoice:
                                                        allocation.invoice.id,
                                                })
                                            "
                                            class="font-mono text-brand-500 hover:underline"
                                            dir="ltr"
                                        >
                                            {{ allocation.invoice.number }}
                                        </Link>
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ money(allocation.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </BaseCard>
            </div>

            <div class="space-y-6">
                <BaseCard>
                    <div class="flex flex-wrap gap-2">
                        <BaseBadge :color="invoice.status_color">
                            {{ invoice.status_label }}
                        </BaseBadge>
                        <BaseBadge
                            v-if="isPosted"
                            :color="invoice.payment_status_color"
                        >
                            {{ invoice.payment_status_label }}
                        </BaseBadge>
                        <BaseBadge v-if="invoice.is_overdue" color="error">
                            {{ t('Overdue') }}
                        </BaseBadge>
                        <BaseBadge v-if="invoice.is_opening" color="light">
                            {{ t('Opening balance') }}
                        </BaseBadge>
                    </div>
                    <p
                        class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400"
                    >
                        {{
                            isPosted
                                ? type.is_return
                                    ? t('Not applied')
                                    : t('Amount due')
                                : t('Total')
                        }}
                    </p>
                    <p
                        class="mt-1 font-mono text-2xl font-semibold text-gray-800 dark:text-white/90"
                        dir="ltr"
                    >
                        {{
                            money(isPosted ? invoice.amount_due : invoice.total)
                        }}
                        <span class="text-sm font-normal text-gray-400">
                            {{ invoice.currency?.code }}
                        </span>
                    </p>

                    <div class="mt-6 flex flex-col gap-3">
                        <BaseButton
                            v-if="canRecordPayment && !simpleMode"
                            class="w-full"
                            :href="recordPaymentUrl"
                        >
                            <WalletMoney class="size-5" />
                            {{
                                type.is_sales
                                    ? t('Record receipt')
                                    : t('Record payment')
                            }}
                        </BaseButton>
                        <BaseButton
                            v-if="canApplyNote"
                            variant="outline"
                            class="w-full"
                            @click="openApplyNote"
                        >
                            <LinkIcon class="size-5" />
                            {{
                                t('Apply :document', {
                                    document: type.counterpart.label,
                                })
                            }}
                        </BaseButton>
                        <BaseButton
                            v-if="canCreateNote"
                            variant="outline"
                            class="w-full"
                            :href="
                                documentRoutes(type.counterpart.route).create(
                                    undefined,
                                    {
                                        query: {
                                            original_invoice_id: invoice.id,
                                        },
                                    },
                                )
                            "
                        >
                            <DocumentAdd class="size-5" />
                            {{
                                t('Create :document', {
                                    document: type.counterpart.label,
                                })
                            }}
                        </BaseButton>
                        <BaseButton
                            v-if="invoice.can.cancel"
                            variant="outline"
                            class="w-full"
                            @click="confirmingCancel = true"
                        >
                            <CloseCircle class="size-5" />
                            {{ t('Cancel document') }}
                        </BaseButton>
                        <BaseButton
                            v-if="invoice.can.delete"
                            variant="danger"
                            class="w-full"
                            @click="confirmingDelete = true"
                        >
                            <Trash2 class="size-5" />
                            {{ t('Delete draft') }}
                        </BaseButton>
                    </div>
                </BaseCard>

                <BaseCard :title="t('Details')">
                    <dl class="space-y-3 text-sm">
                        <div
                            v-if="invoice.original_invoice"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Original document') }}
                            </dt>
                            <dd>
                                <Link
                                    :href="
                                        documentRoutes(
                                            type.counterpart.route,
                                        ).show({
                                            invoice:
                                                invoice.original_invoice.id,
                                        })
                                    "
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ invoice.original_invoice.number }}
                                </Link>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Created by') }}
                            </dt>
                            <dd class="text-gray-800 dark:text-white/90">
                                {{ invoice.created_by ?? t('System') }}
                            </dd>
                        </div>
                        <div
                            v-if="invoice.posted_at"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Posted at') }}
                            </dt>
                            <dd class="text-gray-800 dark:text-white/90">
                                {{
                                    formatDate(invoice.posted_at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="invoice.journal_entries?.length"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Journal entries') }}
                            </dt>
                            <dd class="flex flex-wrap justify-end gap-2">
                                <template
                                    v-for="entry in invoice.journal_entries"
                                    :key="entry.id"
                                >
                                    <Link
                                        v-if="can('Journal Entry View List')"
                                        :href="
                                            showEntry({
                                                journalEntry: entry.id,
                                            })
                                        "
                                        class="text-brand-500 hover:underline"
                                        dir="ltr"
                                        :title="entry.description ?? undefined"
                                    >
                                        {{ entry.number }}
                                    </Link>
                                    <span
                                        v-else
                                        class="text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        {{ entry.number }}
                                    </span>
                                </template>
                            </dd>
                        </div>
                    </dl>
                </BaseCard>
            </div>
        </div>

        <BaseModal
            v-model:open="isApplyingNote"
            :title="t('Apply :document', { document: type.counterpart.label })"
            max-width="md"
        >
            <form
                id="apply-note-form"
                class="space-y-5"
                @submit.prevent="applyNote"
            >
                <BaseField
                    :label="type.counterpart.label"
                    for="note_id"
                    required
                    :error="noteForm.errors.note_id"
                >
                    <BaseSelect
                        id="note_id"
                        v-model="noteForm.note_id"
                        :invalid="Boolean(noteForm.errors.note_id)"
                        @change="suggestAmount"
                    >
                        <option
                            v-for="note in openNotes"
                            :key="note.value"
                            :value="note.value"
                        >
                            {{ note.label }} —
                            {{
                                t('available :amount', {
                                    amount: money(note.available),
                                })
                            }}
                        </option>
                    </BaseSelect>
                </BaseField>
                <BaseField
                    :label="t('Amount')"
                    for="note_amount"
                    required
                    :hint="
                        t('Amount due: :amount', {
                            amount: money(invoice.amount_due),
                        })
                    "
                    :error="noteForm.errors.amount"
                >
                    <BaseInput
                        id="note_amount"
                        v-model="noteForm.amount"
                        type="number"
                        step="any"
                        min="0"
                        dir="ltr"
                        class="font-mono"
                        :invalid="Boolean(noteForm.errors.amount)"
                    />
                </BaseField>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isApplyingNote = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="apply-note-form"
                    size="sm"
                    :loading="noteForm.processing"
                >
                    {{ t('Apply') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel document')"
            :message="
                t(
                    'Its journal entries and stock movements will be reversed. Remove its payments and notes first.',
                )
            "
            :confirm-label="t('Cancel document')"
            :processing="busy === 'cancel'"
            @confirm="visit('cancel')"
        />

        <ConfirmDialog
            v-model:open="confirmingDelete"
            :title="t('Delete draft')"
            :message="
                t(
                    'Are you sure you want to delete this draft? This action cannot be undone.',
                )
            "
            :processing="busy === 'delete'"
            @confirm="visit('delete')"
        />

        <ConfirmDialog
            :open="removal.target !== null"
            :title="t('Remove settlement')"
            :message="
                t(
                    'The amount will be due again and the payment or note will be free to use elsewhere.',
                )
            "
            :confirm-label="t('Remove')"
            :processing="removal.processing"
            @update:open="(open) => !open && removal.cancel()"
            @confirm="removal.confirm"
        />
    </div>
</template>
