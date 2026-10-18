<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import CheckCircle from '~icons/solar/check-circle-linear';
import CloseCircle from '~icons/solar/close-circle-linear';
import DocumentAdd from '~icons/solar/document-add-linear';
import Pencil from '~icons/solar/pen-linear';
import Plain from '~icons/solar/plain-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showContact } from '@/routes/admin/contacts';
import { show as showInvoice } from '@/routes/admin/sales/invoices';
import {
    convert,
    destroy,
    edit,
    index,
    pdf,
    status as changeStatus,
} from '@/routes/admin/sales/quotations';
import type { Quotation, QuotationStatusValue, Resource } from '@/types';

const props = defineProps<{
    quotation: Resource<Quotation>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const page = usePage();

const quotation = computed(() => props.quotation.data);

const decimals = computed(() => quotation.value.currency?.decimal_places ?? 2);

const money = (value: string | number): string =>
    formatDecimal(value, locale.value, decimals.value);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);

const hasDiscount = computed(() =>
    (quotation.value.lines ?? []).some((line) => Number(line.discount_percent)),
);

const statusIcons: Partial<Record<QuotationStatusValue, Component>> = {
    sent: Plain,
    accepted: CheckCircle,
    rejected: CloseCircle,
};

const busy = ref<string | null>(null);
const confirmingDelete = ref(false);
const confirmingConvert = ref(false);

function markAs(status: QuotationStatusValue): void {
    router.post(
        changeStatus.url({ quotation: quotation.value.id }),
        { status },
        {
            preserveScroll: true,
            onStart: () => (busy.value = status),
            onFinish: () => (busy.value = null),
        },
    );
}

function visit(action: 'convert' | 'delete'): void {
    const id = { quotation: quotation.value.id };

    router.visit(action === 'convert' ? convert(id) : destroy(id), {
        preserveScroll: true,
        onStart: () => (busy.value = action),
        onFinish: () => {
            busy.value = null;
            confirmingDelete.value = false;
            confirmingConvert.value = false;
        },
    });
}

const details = computed(() =>
    [
        {
            label: t('Date'),
            value: formatDate(quotation.value.date, locale.value),
        },
        {
            label: t('Valid until'),
            value: formatDate(quotation.value.valid_until, locale.value),
            alert: quotation.value.is_expired,
        },
        { label: t('Reference'), value: quotation.value.reference, ltr: true },
        { label: t('Currency'), value: quotation.value.currency?.code },
    ].filter((detail) => detail.value),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Quotation :number', { number: quotation.number })"
            :breadcrumbs="[
                { title: t('Sales Quotations'), href: index() },
                { title: quotation.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ quotation: quotation.id })" />
                <BaseButton
                    v-if="quotation.can.update"
                    variant="outline"
                    :href="edit({ quotation: quotation.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
                <BaseButton
                    v-if="quotation.can.convert"
                    @click="confirmingConvert = true"
                >
                    <DocumentAdd class="size-5" />
                    {{ t('Convert to invoice') }}
                </BaseButton>
            </template>
        </PageHeader>

        <BaseAlert
            v-if="quotation.is_expired"
            variant="warning"
            class="mb-6"
            :title="t('This quotation has expired')"
            :messages="[
                t(
                    'Its validity date has passed. Edit it to give the customer a new date, or invoice it if they still agree.',
                ),
            ]"
        />

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
                                {{ t('Quotation for') }}
                            </p>
                            <template v-if="quotation.contact">
                                <Link
                                    v-if="can('Contact View')"
                                    :href="
                                        showContact({
                                            contact: quotation.contact.id,
                                        })
                                    "
                                    class="mt-1 block font-medium text-brand-500 hover:underline"
                                >
                                    {{ quotation.contact.name }}
                                </Link>
                                <p
                                    v-else
                                    class="mt-1 font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ quotation.contact.name }}
                                </p>
                                <p
                                    class="font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                                    dir="ltr"
                                >
                                    {{ quotation.contact.code }}
                                </p>
                                <p
                                    v-if="quotation.contact.tax_number"
                                    class="text-theme-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ t('Tax number') }}:
                                    <span dir="ltr">{{
                                        quotation.contact.tax_number
                                    }}</span>
                                </p>
                                <p
                                    v-if="quotation.contact.address"
                                    class="text-theme-sm whitespace-pre-line text-gray-500 dark:text-gray-400"
                                >
                                    {{ quotation.contact.address }}
                                </p>
                            </template>
                        </div>
                        <div class="text-end">
                            <p
                                class="text-title-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ t('Sales quotation') }}
                            </p>
                            <p
                                class="mt-1 font-mono text-gray-500 dark:text-gray-400"
                                dir="ltr"
                            >
                                {{ quotation.number }}
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
                                                ? 'font-medium text-warning-600 dark:text-warning-500'
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
                                    <th class="th-cell text-end">
                                        {{ t('Total') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr
                                    v-for="(line, position) in quotation.lines"
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
                                            v-if="line.item"
                                            class="font-mono text-theme-xs text-gray-400"
                                            dir="ltr"
                                        >
                                            {{ line.item.code }}
                                        </p>
                                    </td>
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
                            <template v-if="quotation.notes">
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ t('Notes') }}
                                </p>
                                <p
                                    class="mt-1 whitespace-pre-line text-gray-800 dark:text-white/90"
                                >
                                    {{ quotation.notes }}
                                </p>
                            </template>
                            <p
                                v-if="quotation.prices_include_tax"
                                class="mt-2 text-theme-xs text-gray-400"
                            >
                                {{ t('Prices include tax.') }}
                            </p>
                        </div>
                        <dl class="w-full space-y-2 text-sm sm:w-72">
                            <div
                                v-if="Number(quotation.discount_total)"
                                class="flex justify-between gap-4"
                            >
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ t('Discount') }}
                                </dt>
                                <dd
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    −{{ money(quotation.discount_total) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ t('Subtotal') }}
                                </dt>
                                <dd
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ money(quotation.subtotal) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ t('Tax') }}
                                </dt>
                                <dd
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ money(quotation.tax_total) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-4 border-t border-gray-100 pt-2 text-base font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                            >
                                <dt>{{ t('Total') }}</dt>
                                <dd class="font-mono" dir="ltr">
                                    {{ money(quotation.total) }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        {{ quotation.currency?.code }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </BaseCard>
            </div>

            <div class="space-y-6">
                <BaseCard :title="t('Status')">
                    <div class="space-y-4">
                        <BaseBadge :color="quotation.status_color" size="md">
                            {{ quotation.status_label }}
                        </BaseBadge>

                        <div
                            v-if="quotation.transitions.length"
                            class="space-y-3"
                        >
                            <p
                                class="text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t('What did the customer say?') }}
                            </p>
                            <BaseButton
                                v-for="transition in quotation.transitions"
                                :key="transition.value"
                                variant="outline"
                                class="w-full"
                                :loading="busy === transition.value"
                                :disabled="busy !== null"
                                @click="markAs(transition.value)"
                            >
                                <component
                                    :is="statusIcons[transition.value]"
                                    class="size-5"
                                />
                                {{
                                    t('Mark as :status', {
                                        status: transition.label,
                                    })
                                }}
                            </BaseButton>
                        </div>

                        <BaseButton
                            v-if="quotation.can.delete"
                            variant="danger"
                            class="w-full"
                            @click="confirmingDelete = true"
                        >
                            <Trash2 class="size-5" />
                            {{ t('Delete quotation') }}
                        </BaseButton>
                    </div>
                </BaseCard>

                <BaseCard :title="t('Details')">
                    <dl class="space-y-3 text-sm">
                        <div
                            v-if="quotation.invoice"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Sales invoice') }}
                            </dt>
                            <dd>
                                <Link
                                    v-if="can('Sales Invoice View')"
                                    :href="
                                        showInvoice({
                                            invoice: quotation.invoice.id,
                                        })
                                    "
                                    class="font-mono text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ quotation.invoice.number ?? t('Draft') }}
                                </Link>
                                <span
                                    v-else
                                    class="font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ quotation.invoice.number ?? t('Draft') }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Created by') }}
                            </dt>
                            <dd class="text-gray-800 dark:text-white/90">
                                {{ quotation.created_by ?? t('System') }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmingConvert"
            :title="t('Convert to invoice')"
            :message="
                t(
                    'A draft sales invoice dated today will be created with the same lines and prices. You can check it before posting it.',
                )
            "
            :confirm-label="t('Create the invoice')"
            :processing="busy === 'convert'"
            @confirm="visit('convert')"
        />

        <ConfirmDialog
            v-model:open="confirmingDelete"
            :title="t('Delete quotation')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: quotation.number },
                )
            "
            :processing="busy === 'delete'"
            @confirm="visit('delete')"
        />
    </div>
</template>
