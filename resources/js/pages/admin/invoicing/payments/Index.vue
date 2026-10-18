<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
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
import { formatDate, formatDecimal } from '@/lib/format';
import { paymentRoutes } from '@/lib/invoicing-routes';
import type {
    Paginated,
    Payment,
    PaymentTypeProps,
    SelectOption,
} from '@/types';

const props = defineProps<{
    type: PaymentTypeProps;
    payments: Paginated<Payment>;
    methods: SelectOption[];
    statuses: SelectOption[];
    filters: {
        search: string | null;
        method: string | null;
        status: string | null;
        contact: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

const routes = computed(() => paymentRoutes(props.type.route));

// Simple mode uses the plain wording of the sidebar.
const title = computed(() => {
    if (props.type.value === 'receipt') {
        return simpleMode.value
            ? t('Money received from customers')
            : t('Customer Receipts');
    }

    return simpleMode.value ? t('Money paid to vendors') : t('Vendor Payments');
});

const { filters, activeCount } = useIndexFilters(routes.value.index, {
    search: props.filters.search,
    method: props.filters.method,
    status: props.filters.status,
    contact: props.filters.contact,
    from: props.filters.from,
    to: props.filters.to,
});

const money = (payment: Payment, value: string | undefined): string =>
    formatDecimal(
        value ?? null,
        locale.value,
        payment.currency?.decimal_places ?? 2,
    );
</script>

<template>
    <div>
        <PageHeader :title="title" :breadcrumbs="[{ title }]">
            <template v-if="can(`${type.permission} Create`)" #actions>
                <BaseButton :href="routes.create()">
                    <Plus class="size-5" />
                    {{ t('New :document', { document: type.label }) }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="payments" :active-filters="activeCount">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by number, reference or description...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.method" class="sm:w-40">
                    <option value="">{{ t('All methods') }}</option>
                    <option
                        v-for="method in methods"
                        :key="method.value"
                        :value="method.value"
                    >
                        {{ method.label }}
                    </option>
                </BaseSelect>
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

            <MobileRecordList v-if="payments.data.length">
                <MobileRecordCard
                    v-for="payment in payments.data"
                    :key="payment.id"
                    :href="routes.show({ payment: payment.id })"
                    :title="payment.number"
                    mono
                    :subtitle="payment.contact"
                    :meta="`${formatDate(payment.date, locale)} · ${payment.method_label}`"
                    :amount="money(payment, payment.amount)"
                    :currency="payment.currency?.code"
                >
                    <BaseBadge :color="payment.status_color">
                        {{ payment.status_label }}
                    </BaseBadge>
                </MobileRecordCard>
            </MobileRecordList>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table
                    v-if="payments.data.length"
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
                                    type.value === 'receipt'
                                        ? t('Customer')
                                        : t('Vendor')
                                }}
                            </th>
                            <th class="th-cell">{{ t('Method') }}</th>
                            <th class="th-cell text-end">{{ t('Amount') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Unallocated') }}
                            </th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="payment in payments.data"
                            :key="payment.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell whitespace-nowrap">
                                <Link
                                    :href="routes.show({ payment: payment.id })"
                                    class="font-mono font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ payment.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(payment.date, locale) }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ payment.contact ?? '—' }}
                            </td>
                            <td class="td-cell">
                                <span>{{ payment.method_label }}</span>
                                <span
                                    v-if="payment.cheque"
                                    class="mt-1 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                                        dir="ltr"
                                    >
                                        № {{ payment.cheque.number }}
                                    </span>
                                    <BaseBadge
                                        :color="payment.cheque.status_color"
                                        size="sm"
                                    >
                                        {{ payment.cheque.status_label }}
                                    </BaseBadge>
                                </span>
                            </td>
                            <td
                                class="td-cell text-end font-mono whitespace-nowrap"
                                dir="ltr"
                            >
                                {{ money(payment, payment.amount) }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ payment.currency?.code }}
                                </span>
                            </td>
                            <td
                                :class="[
                                    'td-cell text-end font-mono whitespace-nowrap',
                                    Number(payment.unallocated) > 0 &&
                                    payment.status === 'posted'
                                        ? 'text-warning-600 dark:text-warning-500'
                                        : '',
                                ]"
                                dir="ltr"
                            >
                                {{ money(payment, payment.unallocated) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="payment.status_color">
                                    {{ payment.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No payments found.')">
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
