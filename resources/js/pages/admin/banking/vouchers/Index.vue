<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ArrowLeftDown from '~icons/solar/arrow-left-down-linear';
import ArrowRightUp from '~icons/solar/arrow-right-up-linear';
import Wallet from '~icons/solar/wallet-money-linear';
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
import { create, index, show } from '@/routes/admin/banking/vouchers';
import type {
    BankAccountOption,
    BankVoucher,
    Paginated,
    SelectOption,
} from '@/types';

const props = defineProps<{
    vouchers: Paginated<BankVoucher>;
    bankAccounts: BankAccountOption[];
    directions: SelectOption[];
    statuses: SelectOption[];
    filters: {
        search: string | null;
        direction: string | null;
        account: string | null;
        status: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

// The one-line expense form, the everyday task of someone who does not know accounting.
const expenseForm = create(undefined, {
    query: { direction: 'out', kind: 'expense' },
});

const { filters, activeCount } = useIndexFilters(index, {
    search: props.filters.search,
    direction: props.filters.direction,
    account: props.filters.account,
    status: props.filters.status,
    from: props.filters.from,
    to: props.filters.to,
});
</script>

<template>
    <div>
        <PageHeader
            :title="
                simpleMode ? t('Expenses and other payments') : t('Vouchers')
            "
            :description="
                t('Money paid out of or received into a bank or cash account.')
            "
            :breadcrumbs="[
                {
                    title: simpleMode
                        ? t('Expenses and other payments')
                        : t('Vouchers'),
                },
            ]"
        >
            <template #actions>
                <template v-if="can('Bank Voucher Create')">
                    <BaseButton v-if="simpleMode" :href="expenseForm">
                        <Wallet class="size-5" />
                        {{ t('Record an expense') }}
                    </BaseButton>
                    <BaseButton
                        variant="outline"
                        :href="
                            create(undefined, { query: { direction: 'in' } })
                        "
                    >
                        <ArrowLeftDown class="size-5" />
                        {{ t('Receipt voucher') }}
                    </BaseButton>
                    <BaseButton
                        :variant="simpleMode ? 'outline' : 'primary'"
                        :href="
                            create(undefined, { query: { direction: 'out' } })
                        "
                    >
                        <ArrowRightUp class="size-5" />
                        {{ t('Payment voucher') }}
                    </BaseButton>
                </template>
            </template>
        </PageHeader>

        <DataCard :pagination="vouchers" :active-filters="activeCount">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by number, payee or description...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.direction" class="sm:w-40">
                    <option value="">{{ t('All vouchers') }}</option>
                    <option
                        v-for="direction in directions"
                        :key="direction.value"
                        :value="direction.value"
                    >
                        {{ direction.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.account" class="sm:w-48">
                    <option value="">{{ t('All accounts') }}</option>
                    <option
                        v-for="bankAccount in bankAccounts"
                        :key="bankAccount.value"
                        :value="String(bankAccount.value)"
                    >
                        {{ bankAccount.label }}
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

            <MobileRecordList v-if="vouchers.data.length">
                <MobileRecordCard
                    v-for="voucher in vouchers.data"
                    :key="voucher.id"
                    :href="show({ voucher: voucher.id })"
                    :title="voucher.number"
                    mono
                    :subtitle="voucher.contact ?? voucher.payee"
                    :meta="formatDate(voucher.date, locale)"
                    :amount="
                        formatDecimal(
                            voucher.total,
                            locale,
                            voucher.currency?.decimal_places ?? 2,
                        )
                    "
                    :currency="voucher.currency?.code"
                >
                    <BaseBadge
                        :color="
                            voucher.direction === 'in' ? 'success' : 'warning'
                        "
                    >
                        {{ voucher.direction_label }}
                    </BaseBadge>
                    <BaseBadge :color="voucher.status_color">
                        {{ voucher.status_label }}
                    </BaseBadge>
                </MobileRecordCard>
            </MobileRecordList>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table
                    v-if="vouchers.data.length"
                    class="hidden min-w-full sm:table"
                >
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Account') }}</th>
                            <th class="th-cell">{{ t('Payee') }}</th>
                            <th class="th-cell text-end">{{ t('Total') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="voucher in vouchers.data"
                            :key="voucher.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ voucher: voucher.id })"
                                    class="font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ voucher.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(voucher.date, locale) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        voucher.direction === 'in'
                                            ? 'success'
                                            : 'warning'
                                    "
                                >
                                    {{ voucher.direction_label }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                {{ voucher.bank_account ?? '—' }}
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ voucher.contact ?? voucher.payee ?? '—' }}
                            </td>
                            <td
                                class="td-cell text-end font-mono whitespace-nowrap"
                                dir="ltr"
                            >
                                {{
                                    formatDecimal(
                                        voucher.total,
                                        locale,
                                        voucher.currency?.decimal_places ?? 2,
                                    )
                                }}
                                <span class="text-theme-xs text-gray-400">
                                    {{ voucher.currency?.code }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="voucher.status_color">
                                    {{ voucher.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No vouchers found.')">
                    <BaseButton
                        v-if="can('Bank Voucher Create')"
                        size="sm"
                        :href="expenseForm"
                    >
                        <Wallet class="size-5" />
                        {{ t('Record an expense') }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>
    </div>
</template>
