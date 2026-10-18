<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
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
import { formatDate, formatDecimal } from '@/lib/format';
import { create, index, show } from '@/routes/admin/banking/transfers';
import type {
    BankAccountOption,
    BankTransfer,
    Paginated,
    SelectOption,
} from '@/types';

const props = defineProps<{
    transfers: Paginated<BankTransfer>;
    bankAccounts: BankAccountOption[];
    statuses: SelectOption[];
    filters: {
        search: string | null;
        account: string | null;
        status: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    account: props.filters.account,
    status: props.filters.status,
    from: props.filters.from,
    to: props.filters.to,
});
</script>

<template>
    <div>
        <PageHeader
            :title="t('Bank Transfers')"
            :breadcrumbs="[{ title: t('Bank Transfers') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Bank Transfer Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('New transfer') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="transfers">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by number or description...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.account" class="sm:w-52">
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

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="transfers.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('From account') }}</th>
                            <th class="th-cell">{{ t('To account') }}</th>
                            <th class="th-cell text-end">{{ t('Amount') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="transfer in transfers.data"
                            :key="transfer.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="show({ transfer: transfer.id })"
                                    class="font-medium text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ transfer.number }}
                                </Link>
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(transfer.date, locale) }}
                            </td>
                            <td class="td-cell">
                                {{ transfer.from_account?.label ?? '—' }}
                            </td>
                            <td class="td-cell">
                                {{ transfer.to_account?.label ?? '—' }}
                            </td>
                            <td class="td-cell text-end whitespace-nowrap">
                                <span
                                    class="block font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{
                                        formatDecimal(
                                            transfer.amount,
                                            locale,
                                            transfer.from_account
                                                ?.decimal_places ?? 2,
                                        )
                                    }}
                                    {{ transfer.from_account?.currency }}
                                </span>
                                <span
                                    v-if="
                                        transfer.to_account &&
                                        transfer.from_account &&
                                        transfer.to_account.currency !==
                                            transfer.from_account.currency
                                    "
                                    class="block font-mono text-theme-xs text-gray-400"
                                    dir="ltr"
                                >
                                    →
                                    {{
                                        formatDecimal(
                                            transfer.to_amount,
                                            locale,
                                            transfer.to_account.decimal_places,
                                        )
                                    }}
                                    {{ transfer.to_account.currency }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="transfer.status_color">
                                    {{ transfer.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No transfers found.')" />
            </div>
        </DataCard>
    </div>
</template>
