<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import History from '~icons/solar/history-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import MobileRecordCard from '@/components/common/MobileRecordCard.vue';
import MobileRecordList from '@/components/common/MobileRecordList.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal, formatNumber } from '@/lib/format';
import { documentRoutes } from '@/lib/invoicing-routes';
import type {
    CurrencyFormat,
    Invoice,
    InvoicePaymentStatusColor,
    InvoiceStatusColor,
    InvoiceSummary,
    InvoiceTypeProps,
    Paginated,
    SelectOption,
} from '@/types';

const props = defineProps<{
    type: InvoiceTypeProps;
    invoices: Paginated<Invoice>;
    summary: InvoiceSummary;
    statuses: SelectOption[];
    paymentStatuses: SelectOption[];
    filters: {
        search: string | null;
        status: string | null;
        payment: string | null;
        contact: string | null;
        from: string | null;
        to: string | null;
    };
    baseCurrency: CurrencyFormat;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

const routes = computed(() => documentRoutes(props.type.route));

const { filters, activeCount } = useIndexFilters(routes.value.index, {
    search: props.filters.search,
    status: props.filters.status,
    payment: props.filters.payment,
    contact: props.filters.contact,
    from: props.filters.from,
    to: props.filters.to,
});

const base = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const money = (invoice: Invoice, value: string): string =>
    formatDecimal(value, locale.value, invoice.currency?.decimal_places ?? 2);

/**
 * The status badges of a row. A draft or cancelled invoice shows its status;
 * a posted one adds how much of it is paid, which is all simple mode shows.
 */
function statusBadges(
    invoice: Invoice,
): { label: string; color: InvoiceStatusColor | InvoicePaymentStatusColor }[] {
    const status = { label: invoice.status_label, color: invoice.status_color };

    if (invoice.status !== 'posted') {
        return [status];
    }

    const payment = {
        label: invoice.payment_status_label,
        color: invoice.payment_status_color,
    };

    return simpleMode.value ? [payment] : [status, payment];
}

/**
 * In simple mode a click anywhere on a row opens the invoice, except on the
 * links and buttons inside it.
 */
function openRow(event: MouseEvent, invoice: Invoice): void {
    if (
        !simpleMode.value ||
        (event.target as HTMLElement).closest('a, button, input, select, label')
    ) {
        return;
    }

    router.visit(routes.value.show({ invoice: invoice.id }));
}

const cards = computed(() => [
    {
        label: props.type.is_return
            ? t('Open (not applied)')
            : t('Open amount due'),
        value: base(props.summary.open),
        money: true,
        alert: false,
    },
    {
        label: t('Overdue'),
        value: base(props.summary.overdue),
        money: true,
        alert: Number(props.summary.overdue) > 0,
    },
    {
        label: t('Drafts'),
        value: formatNumber(props.summary.drafts, locale.value),
        money: false,
        alert: false,
    },
]);
</script>

<template>
    <div>
        <PageHeader
            :title="type.plural"
            :breadcrumbs="[{ title: type.plural }]"
        >
            <template v-if="can(`${type.permission} Create`)" #actions>
                <BaseButton
                    v-if="!type.is_return"
                    variant="outline"
                    :href="routes.create(undefined, { query: { opening: 1 } })"
                >
                    <History class="size-5" />
                    {{ t('Opening balance') }}
                </BaseButton>
                <BaseButton :href="routes.create()">
                    <Plus class="size-5" />
                    {{ t('New :document', { document: type.label }) }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ card.label }}
                </p>
                <p
                    :class="[
                        'mt-2 font-mono text-xl font-semibold',
                        card.alert
                            ? 'text-error-600 dark:text-error-500'
                            : 'text-gray-800 dark:text-white/90',
                    ]"
                    dir="ltr"
                >
                    {{ card.value }}
                    <span
                        v-if="card.money"
                        class="text-theme-xs font-normal text-gray-400"
                    >
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
        </div>

        <DataCard :pagination="invoices" :active-filters="activeCount">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by number or reference...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.status" class="sm:w-36">
                    <option value="">{{ t('All statuses') }}</option>
                    <option
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.payment" class="sm:w-40">
                    <option value="">{{ t('Any payment status') }}</option>
                    <option
                        v-for="status in paymentStatuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </BaseSelect>
                <BaseInput
                    v-model="filters.from"
                    type="date"
                    class="sm:w-40"
                    :title="t('From')"
                />
                <BaseInput
                    v-model="filters.to"
                    type="date"
                    class="sm:w-40"
                    :title="t('To')"
                />
            </template>

            <MobileRecordList v-if="invoices.data.length">
                <MobileRecordCard
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    :href="routes.show({ invoice: invoice.id })"
                    :title="invoice.number ?? t('Draft')"
                    mono
                    :subtitle="invoice.contact?.name"
                    :meta="formatDate(invoice.date, locale)"
                    :amount="money(invoice, invoice.total)"
                    :currency="invoice.currency?.code"
                >
                    <BaseBadge
                        v-for="badge in statusBadges(invoice)"
                        :key="badge.label"
                        :color="badge.color"
                    >
                        {{ badge.label }}
                    </BaseBadge>
                </MobileRecordCard>
            </MobileRecordList>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table
                    v-if="invoices.data.length"
                    class="hidden min-w-full sm:table"
                >
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">
                                {{
                                    type.is_sales ? t('Customer') : t('Vendor')
                                }}
                            </th>
                            <th class="th-cell">{{ t('Due date') }}</th>
                            <th class="th-cell text-end">{{ t('Total') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Amount due') }}
                            </th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="invoice in invoices.data"
                            :key="invoice.id"
                            :class="[
                                'hover:bg-gray-50 dark:hover:bg-white/[0.02]',
                                simpleMode ? 'cursor-pointer' : '',
                            ]"
                            @click="openRow($event, invoice)"
                        >
                            <td class="td-cell whitespace-nowrap">
                                <Link
                                    :href="routes.show({ invoice: invoice.id })"
                                    class="font-mono font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ invoice.number ?? t('Draft') }}
                                </Link>
                                <BaseBadge
                                    v-if="invoice.is_opening"
                                    color="light"
                                    class="ms-2"
                                >
                                    {{ t('Opening') }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(invoice.date, locale) }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ invoice.contact?.name ?? '—' }}
                            </td>
                            <td
                                :class="[
                                    'td-cell whitespace-nowrap',
                                    invoice.is_overdue
                                        ? 'font-medium text-error-600 dark:text-error-500'
                                        : '',
                                ]"
                            >
                                {{ formatDate(invoice.due_date, locale) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono whitespace-nowrap"
                                dir="ltr"
                            >
                                {{ money(invoice, invoice.total) }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ invoice.currency?.code }}
                                </span>
                            </td>
                            <td
                                class="td-cell text-end font-mono whitespace-nowrap"
                                dir="ltr"
                            >
                                {{
                                    invoice.status === 'posted'
                                        ? money(invoice, invoice.amount_due)
                                        : '—'
                                }}
                            </td>
                            <td class="td-cell">
                                <div class="flex flex-wrap gap-1">
                                    <BaseBadge
                                        v-for="badge in statusBadges(invoice)"
                                        :key="badge.label"
                                        :color="badge.color"
                                    >
                                        {{ badge.label }}
                                    </BaseBadge>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No documents found.')">
                    <BaseButton
                        v-if="can(`${type.permission} Create`)"
                        size="sm"
                        :href="routes.create()"
                    >
                        <Plus class="size-5" />
                        {{ t('New :document', { document: type.label }) }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>
    </div>
</template>
