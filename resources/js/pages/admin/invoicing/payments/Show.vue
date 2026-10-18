<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import CloseCircle from '~icons/solar/close-circle-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import ShareButton from '@/components/common/ShareButton.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { documentRoutes, paymentRoutes } from '@/lib/invoicing-routes';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { show as showCheque } from '@/routes/admin/banking/cheques';
import { show as showContact } from '@/routes/admin/contacts';
import type {
    OpenInvoice,
    Payment,
    PaymentAllocation,
    PaymentTypeProps,
    Resource,
} from '@/types';

const props = defineProps<{
    type: PaymentTypeProps;
    payment: Resource<Payment>;
    openInvoices: OpenInvoice[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const payment = computed(() => props.payment.data);
const routes = computed(() => paymentRoutes(props.type.route));
const invoiceRoutes = computed(() => documentRoutes(props.type.invoice_route));
const isReceipt = computed(() => props.type.value === 'receipt');

const decimals = computed(() => payment.value.currency?.decimal_places ?? 2);

const money = (value: string | number | undefined): string =>
    formatDecimal(value ?? null, locale.value, decimals.value);

const isForeign = computed(() => Number(payment.value.exchange_rate) !== 1);

const unallocated = computed(() => Number(payment.value.unallocated ?? 0));

const canAllocate = computed(
    () =>
        payment.value.can.allocate &&
        unallocated.value > 0 &&
        props.openInvoices.length > 0,
);

const details = computed(() =>
    [
        {
            label: t('Date'),
            value: formatDate(payment.value.date, locale.value),
        },
        { label: t('Method'), value: payment.value.method_label },
        {
            label: isReceipt.value ? t('Received into') : t('Paid from'),
            value: payment.value.bank_account,
        },
        {
            label: t('Exchange rate'),
            value: isForeign.value ? payment.value.exchange_rate : null,
            ltr: true,
        },
        { label: t('Reference'), value: payment.value.reference, ltr: true },
        {
            label: t('Created by'),
            value: payment.value.created_by ?? t('System'),
        },
    ].filter((detail) => detail.value),
);

const allocationForm = useForm({
    invoice_id: (props.openInvoices[0]?.id ?? null) as number | null,
    amount: '',
});

function suggestAmount(): void {
    const invoice = props.openInvoices.find(
        (open) => open.id === allocationForm.invoice_id,
    );
    const amount = Math.min(
        Number(invoice?.amount_due ?? 0),
        unallocated.value,
    );

    allocationForm.amount = amount > 0 ? amount.toFixed(decimals.value) : '';
}

suggestAmount();

function allocate(): void {
    allocationForm.submit(
        routes.value.allocate({ payment: payment.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                allocationForm.invoice_id = props.openInvoices[0]?.id ?? null;
                suggestAmount();
            },
        },
    );
}

const removal = useConfirmDelete((allocation: PaymentAllocation) =>
    routes.value.allocations.destroy({
        payment: payment.value.id,
        allocation: allocation.id,
    }),
);

const confirmingCancel = ref(false);
const cancelling = ref(false);

function cancelPayment(): void {
    router.visit(routes.value.cancel({ payment: payment.value.id }), {
        preserveScroll: true,
        onStart: () => (cancelling.value = true),
        onFinish: () => {
            cancelling.value = false;
            confirmingCancel.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="`${type.label} ${payment.number}`"
            :breadcrumbs="[
                {
                    title: isReceipt
                        ? t('Customer Receipts')
                        : t('Vendor Payments'),
                    href: routes.index(),
                },
                { title: payment.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="routes.pdf.url({ payment: payment.id })" />
                <ShareButton
                    :url="routes.pdf.url({ payment: payment.id })"
                    :filename="payment.number"
                    :text="
                        t(':document :number — :amount', {
                            document: payment.type_label,
                            number: payment.number,
                            amount: `${money(payment.amount)} ${payment.currency?.code ?? ''}`.trim(),
                        })
                    "
                    :phone="payment.contact_phone"
                />
                <BaseButton
                    v-if="payment.can.cancel"
                    variant="outline"
                    @click="confirmingCancel = true"
                >
                    <CloseCircle class="size-5" />
                    {{ t('Cancel document') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <BaseCard>
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <BaseBadge :color="payment.status_color">
                                    {{ payment.status_label }}
                                </BaseBadge>
                                <BaseBadge
                                    v-if="payment.is_effective === false"
                                    color="error"
                                >
                                    {{ t('Not effective') }}
                                </BaseBadge>
                            </div>
                            <p
                                class="mt-3 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ isReceipt ? t('Customer') : t('Vendor') }}
                            </p>
                            <Link
                                v-if="can('Contact View')"
                                :href="
                                    showContact({ contact: payment.contact_id })
                                "
                                class="font-medium text-brand-500 hover:underline"
                            >
                                {{ payment.contact }}
                            </Link>
                            <p
                                v-else
                                class="font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ payment.contact }}
                            </p>
                        </div>
                        <div class="text-end">
                            <p
                                class="font-mono text-2xl font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ money(payment.amount) }}
                                <span class="text-sm font-normal text-gray-400">
                                    {{ payment.currency?.code }}
                                </span>
                            </p>
                            <p
                                v-if="payment.status === 'posted'"
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t('Unallocated') }}:
                                <span class="font-mono" dir="ltr">{{
                                    money(payment.unallocated)
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <dl
                        class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-2 dark:border-gray-800"
                    >
                        <div v-for="detail in details" :key="detail.label">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ detail.label }}
                            </dt>
                            <dd
                                :class="[
                                    'mt-1 text-gray-800 dark:text-white/90',
                                    { 'font-mono': detail.ltr },
                                ]"
                                :dir="detail.ltr ? 'ltr' : undefined"
                            >
                                {{ detail.value }}
                            </dd>
                        </div>
                        <div v-if="payment.cheque">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Cheque') }}
                            </dt>
                            <dd class="mt-1 flex flex-wrap items-center gap-2">
                                <Link
                                    v-if="can('Cheque View')"
                                    :href="
                                        showCheque({
                                            cheque: payment.cheque.id,
                                        })
                                    "
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    № {{ payment.cheque.number }}
                                </Link>
                                <span
                                    v-else
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    № {{ payment.cheque.number }}
                                </span>
                                <BaseBadge :color="payment.cheque.status_color">
                                    {{ payment.cheque.status_label }}
                                </BaseBadge>
                                <span
                                    class="w-full text-theme-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ t('Due date') }}:
                                    {{
                                        formatDate(
                                            payment.cheque.due_date,
                                            locale,
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="payment.journal_entries?.length">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Journal entries') }}
                            </dt>
                            <dd class="mt-1 flex flex-wrap gap-2">
                                <template
                                    v-for="entry in payment.journal_entries"
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
                        <div v-if="payment.description" class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Description') }}
                            </dt>
                            <dd class="mt-1 text-gray-800 dark:text-white/90">
                                {{ payment.description }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard
                    :title="
                        isReceipt ? t('Invoices settled') : t('Bills settled')
                    "
                >
                    <div
                        v-if="payment.allocations?.length"
                        class="-mx-4 custom-scrollbar overflow-x-auto sm:-mx-6"
                    >
                        <table class="min-w-full">
                            <thead>
                                <tr
                                    class="border-b border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">{{ t('Number') }}</th>
                                    <th class="th-cell">
                                        {{ t('Document date') }}
                                    </th>
                                    <th class="th-cell">
                                        {{ t('Allocated on') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Document total') }}
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
                                    v-for="allocation in payment.allocations"
                                    :key="allocation.id"
                                >
                                    <td class="td-cell">
                                        <Link
                                            v-if="allocation.invoice"
                                            :href="
                                                invoiceRoutes.show({
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
                                    <td class="td-cell whitespace-nowrap">
                                        {{
                                            formatDate(
                                                allocation.invoice?.date ??
                                                    null,
                                                locale,
                                            )
                                        }}
                                    </td>
                                    <td class="td-cell whitespace-nowrap">
                                        {{
                                            formatDate(allocation.date, locale)
                                        }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ money(allocation.invoice?.total) }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono text-gray-800 dark:text-white/90"
                                        dir="ltr"
                                    >
                                        {{ money(allocation.amount) }}
                                    </td>
                                    <td class="td-cell">
                                        <BaseIconButton
                                            v-if="payment.can.allocate"
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
                    <EmptyState
                        v-else
                        :message="t('This payment is not allocated yet.')"
                    />
                </BaseCard>
            </div>

            <div class="space-y-6">
                <BaseCard v-if="canAllocate" :title="t('Allocate')">
                    <form class="space-y-5" @submit.prevent="allocate">
                        <BaseField
                            :label="isReceipt ? t('Invoice') : t('Bill')"
                            for="invoice_id"
                            required
                            :error="allocationForm.errors.invoice_id"
                        >
                            <BaseSelect
                                id="invoice_id"
                                v-model="allocationForm.invoice_id"
                                :invalid="
                                    Boolean(allocationForm.errors.invoice_id)
                                "
                                @change="suggestAmount"
                            >
                                <option
                                    v-for="invoice in openInvoices"
                                    :key="invoice.id"
                                    :value="invoice.id"
                                >
                                    {{ invoice.number }} —
                                    {{
                                        t('due :amount', {
                                            amount: money(invoice.amount_due),
                                        })
                                    }}
                                </option>
                            </BaseSelect>
                        </BaseField>
                        <BaseField
                            :label="t('Amount')"
                            for="allocation_amount"
                            required
                            :hint="
                                t('Unallocated: :amount', {
                                    amount: money(payment.unallocated),
                                })
                            "
                            :error="allocationForm.errors.amount"
                        >
                            <BaseInput
                                id="allocation_amount"
                                v-model="allocationForm.amount"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                class="font-mono"
                                :invalid="Boolean(allocationForm.errors.amount)"
                            />
                        </BaseField>
                        <BaseButton
                            type="submit"
                            class="w-full"
                            :loading="allocationForm.processing"
                        >
                            {{ t('Allocate') }}
                        </BaseButton>
                    </form>
                </BaseCard>

                <BaseCard v-if="payment.cheque && payment.status === 'posted'">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            t(
                                'This payment was made by cheque. To undo it, return or cancel the cheque from the cheque register.',
                            )
                        }}
                    </p>
                </BaseCard>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel document')"
            :message="
                t(
                    'Its journal entry will be reversed and the invoices it settled will be due again.',
                )
            "
            :confirm-label="t('Cancel document')"
            :processing="cancelling"
            @confirm="cancelPayment"
        />

        <ConfirmDialog
            :open="removal.target !== null"
            :title="t('Remove allocation')"
            :message="
                t(
                    'The invoice will be due again and the amount will be unallocated.',
                )
            "
            :confirm-label="t('Remove')"
            :processing="removal.processing"
            @update:open="(open) => !open && removal.cancel()"
            @confirm="removal.confirm"
        />
    </div>
</template>
