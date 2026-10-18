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
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal, formatNumber } from '@/lib/format';
import { create, index, show } from '@/routes/admin/sales/quotations';
import type {
    Paginated,
    Quotation,
    QuotationSummary,
    SelectOption,
} from '@/types';

const props = defineProps<{
    quotations: Paginated<Quotation>;
    summary: QuotationSummary;
    statuses: SelectOption[];
    filters: {
        search: string | null;
        status: string | null;
        contact: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    status: props.filters.status,
    contact: props.filters.contact,
    from: props.filters.from,
    to: props.filters.to,
});

const money = (quotation: Quotation, value: string): string =>
    formatDecimal(value, locale.value, quotation.currency?.decimal_places ?? 2);

const cards = computed(() => [
    {
        label: t('Waiting for the customer'),
        value: props.summary.waiting,
        alert: false,
    },
    {
        label: t('Accepted, not invoiced yet'),
        value: props.summary.accepted,
        alert: false,
    },
    {
        label: t('Expired'),
        value: props.summary.expired,
        alert: props.summary.expired > 0,
    },
]);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Sales Quotations')"
            :breadcrumbs="[{ title: t('Sales Quotations') }]"
        >
            <template v-if="can('Sales Quotation Create')" #actions>
                <BaseButton :href="create()">
                    <Plus class="size-5" />
                    {{ t('New quotation') }}
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
                            ? 'text-warning-600 dark:text-warning-500'
                            : 'text-gray-800 dark:text-white/90',
                    ]"
                    dir="ltr"
                >
                    {{ formatNumber(card.value, locale) }}
                </p>
            </div>
        </div>

        <DataCard :pagination="quotations">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by number or reference...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.status" class="sm:w-40">
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

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="quotations.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Customer') }}</th>
                            <th class="th-cell">{{ t('Valid until') }}</th>
                            <th class="th-cell text-end">{{ t('Total') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="quotation in quotations.data"
                            :key="quotation.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell whitespace-nowrap">
                                <Link
                                    :href="show({ quotation: quotation.id })"
                                    class="font-mono font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ quotation.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(quotation.date, locale) }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ quotation.contact?.name ?? '—' }}
                            </td>
                            <td
                                :class="[
                                    'td-cell whitespace-nowrap',
                                    quotation.is_expired
                                        ? 'font-medium text-warning-600 dark:text-warning-500'
                                        : '',
                                ]"
                            >
                                {{ formatDate(quotation.valid_until, locale) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono whitespace-nowrap"
                                dir="ltr"
                            >
                                {{ money(quotation, quotation.total) }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ quotation.currency?.code }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="quotation.status_color">
                                    {{ quotation.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No quotations found.')">
                    <BaseButton
                        v-if="can('Sales Quotation Create')"
                        size="sm"
                        :href="create()"
                    >
                        <Plus class="size-5" />
                        {{ t('New quotation') }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>
    </div>
</template>
