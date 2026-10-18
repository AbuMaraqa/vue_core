<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Banknote from '~icons/solar/banknote-linear';
import CardSend from '~icons/solar/card-send-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import MobileRecordCard from '@/components/common/MobileRecordCard.vue';
import MobileRecordList from '@/components/common/MobileRecordList.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal, formatNumber } from '@/lib/format';
import {
    create,
    exportMethod,
    index,
    show,
} from '@/routes/admin/banking/cheques';
import type {
    BankAccountOption,
    Cheque,
    ChequeSummary,
    CurrencyFormat,
    Paginated,
    SelectOption,
} from '@/types';

const props = defineProps<{
    cheques: Paginated<Cheque>;
    summary: ChequeSummary;
    directions: SelectOption[];
    statuses: SelectOption[];
    bankAccounts: BankAccountOption[];
    filters: {
        search: string | null;
        direction: string | null;
        status: string | null;
        open: boolean;
        due_from: string | null;
        due_to: string | null;
        sort: string;
    };
    baseCurrency: CurrencyFormat;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters, activeCount } = useIndexFilters(
    index,
    {
        search: props.filters.search,
        direction: props.filters.direction,
        status: props.filters.status,
        open: props.filters.open ? '1' : null,
        due_from: props.filters.due_from,
        due_to: props.filters.due_to,
    },
    { sort: props.filters.sort },
);

const openOnly = computed({
    get: (): boolean => filters.open === '1',
    set: (checked: boolean) => {
        filters.open = checked ? '1' : '';
    },
});

const base = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const cards = computed(() => [
    { label: t('In hand'), value: base(props.summary.in_hand), money: true },
    {
        label: t('Under collection'),
        value: base(props.summary.under_collection),
        money: true,
    },
    {
        label: t('Issued (payable)'),
        value: base(props.summary.issued),
        money: true,
    },
    {
        label: t('Due this week'),
        value: base(props.summary.due_this_week),
        money: true,
    },
    {
        label: t('Overdue'),
        value: formatNumber(props.summary.overdue, locale.value),
        money: false,
        alert: props.summary.overdue > 0,
    },
]);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Cheques')"
            :description="t('Received and issued cheques and their due dates.')"
            :breadcrumbs="[{ title: t('Cheques') }]"
        >
            <template #actions>
                <ExportMenu v-if="can('Cheque Export')" :route="exportMethod" />
                <template v-if="can('Cheque Create')">
                    <BaseButton
                        variant="outline"
                        :href="
                            create(undefined, {
                                query: { direction: 'outgoing' },
                            })
                        "
                    >
                        <CardSend class="size-5" />
                        {{ t('Issue cheque') }}
                    </BaseButton>
                    <BaseButton
                        :href="
                            create(undefined, {
                                query: { direction: 'incoming' },
                            })
                        "
                    >
                        <Banknote class="size-5" />
                        {{ t('Receive cheque') }}
                    </BaseButton>
                </template>
            </template>
        </PageHeader>

        <div
            class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
        >
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

        <DataCard :pagination="cheques" :active-filters="activeCount">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by number, bank or description...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.direction" class="sm:w-40">
                    <option value="">{{ t('All cheques') }}</option>
                    <option
                        v-for="direction in directions"
                        :key="direction.value"
                        :value="direction.value"
                    >
                        {{ direction.label }}
                    </option>
                </BaseSelect>
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
                    v-model="filters.due_from"
                    type="date"
                    class="sm:w-40"
                    :title="t('Due from')"
                />
                <BaseInput
                    v-model="filters.due_to"
                    type="date"
                    class="sm:w-40"
                    :title="t('Due to')"
                />
                <BaseCheckbox v-model="openOnly">
                    {{ t('Open only') }}
                </BaseCheckbox>
            </template>

            <MobileRecordList v-if="cheques.data.length">
                <MobileRecordCard
                    v-for="cheque in cheques.data"
                    :key="cheque.id"
                    :href="show({ cheque: cheque.id })"
                    :title="cheque.number"
                    mono
                    :subtitle="cheque.contact"
                    :meta="formatDate(cheque.due_date, locale)"
                    :alert="cheque.is_overdue"
                    :amount="
                        formatDecimal(
                            cheque.amount,
                            locale,
                            cheque.currency?.decimal_places ?? 2,
                        )
                    "
                    :currency="cheque.currency?.code"
                >
                    <BaseBadge
                        :color="
                            cheque.direction === 'incoming'
                                ? 'success'
                                : 'warning'
                        "
                    >
                        {{ cheque.direction_label }}
                    </BaseBadge>
                    <BaseBadge :color="cheque.status_color">
                        {{ cheque.status_label }}
                    </BaseBadge>
                </MobileRecordCard>
            </MobileRecordList>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table
                    v-if="cheques.data.length"
                    class="hidden min-w-full sm:table"
                >
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Due date') }}</th>
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Contact') }}</th>
                            <th class="th-cell">{{ t('Bank') }}</th>
                            <th class="th-cell text-end">{{ t('Amount') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="cheque in cheques.data"
                            :key="cheque.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td
                                :class="[
                                    'td-cell whitespace-nowrap',
                                    cheque.is_overdue
                                        ? 'font-medium text-error-600 dark:text-error-500'
                                        : '',
                                ]"
                            >
                                {{ formatDate(cheque.due_date, locale) }}
                            </td>
                            <td class="td-cell">
                                <Link
                                    :href="show({ cheque: cheque.id })"
                                    class="font-mono font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ cheque.number }}
                                </Link>
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        cheque.direction === 'incoming'
                                            ? 'success'
                                            : 'warning'
                                    "
                                >
                                    {{ cheque.direction_label }}
                                </BaseBadge>
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ cheque.contact ?? '—' }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{
                                    cheque.drawee_bank ??
                                    cheque.bank_account ??
                                    '—'
                                }}
                            </td>
                            <td
                                class="td-cell text-end font-mono whitespace-nowrap"
                                dir="ltr"
                            >
                                {{
                                    formatDecimal(
                                        cheque.amount,
                                        locale,
                                        cheque.currency?.decimal_places ?? 2,
                                    )
                                }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ cheque.currency?.code }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="cheque.status_color">
                                    {{ cheque.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No cheques found.')">
                    <BaseButton
                        v-if="can('Cheque Create')"
                        size="sm"
                        :href="
                            create(undefined, {
                                query: { direction: 'incoming' },
                            })
                        "
                    >
                        <Banknote class="size-5" />
                        {{ t('Receive cheque') }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>
    </div>
</template>
