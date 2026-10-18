<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ArrowDownUp from '~icons/solar/sort-vertical-linear';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import ImportDialog from '@/components/common/ImportDialog.vue';
import MobileRecordCard from '@/components/common/MobileRecordCard.vue';
import MobileRecordList from '@/components/common/MobileRecordList.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import {
    create,
    destroy,
    edit,
    exportMethod,
    importMethod,
    importTemplate,
    index,
    show,
} from '@/routes/admin/contacts';
import type { Contact, Paginated } from '@/types';

const props = defineProps<{
    contacts: Paginated<Contact>;
    filters: {
        search: string | null;
        type: string | null;
        status: string | null;
        sort: string;
    };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters, sortBy, activeCount } = useIndexFilters(
    index,
    {
        search: props.filters.search,
        type: props.filters.type,
        status: props.filters.status,
    },
    { sort: props.filters.sort },
);

const deletion = useConfirmDelete((contact: Contact) =>
    destroy({ contact: contact.id }),
);

const importColumns = [
    'code',
    'name',
    'type',
    'kind',
    'contact_person',
    'email',
    'phone',
    'mobile',
    'tax_number',
    'currency',
    'payment_terms_days',
    'credit_limit',
    'city',
    'country',
    'address',
];

function balanceClass(balance: string): string {
    const value = Number(balance);

    if (value > 0) {
        return 'text-success-600 dark:text-success-500';
    }

    return value < 0 ? 'text-error-600 dark:text-error-500' : 'text-gray-400';
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Customers & Vendors')"
            :breadcrumbs="[{ title: t('Customers & Vendors') }]"
        >
            <template #actions>
                <ImportDialog
                    v-if="can('Contact Import')"
                    :title="t('Import contacts')"
                    :import-route="importMethod"
                    :template-route="importTemplate"
                    :columns="importColumns"
                />
                <ExportMenu
                    v-if="can('Contact Export')"
                    :route="exportMethod"
                />
                <BaseButton
                    v-if="can('Contact Create')"
                    variant="outline"
                    :href="create(undefined, { query: { type: 'vendor' } })"
                >
                    <Plus class="size-5" />
                    {{ t('Add vendor') }}
                </BaseButton>
                <BaseButton v-if="can('Contact Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('Add customer') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="contacts" :active-filters="activeCount">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by name, code, phone or tax number...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.type" class="sm:w-40">
                    <option value="">{{ t('All contacts') }}</option>
                    <option value="customers">{{ t('Customers') }}</option>
                    <option value="vendors">{{ t('Vendors') }}</option>
                </BaseSelect>
                <BaseSelect v-model="filters.status" class="sm:w-36">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="active">{{ t('Active') }}</option>
                    <option value="inactive">{{ t('Inactive') }}</option>
                </BaseSelect>
            </template>

            <MobileRecordList v-if="contacts.data.length">
                <MobileRecordCard
                    v-for="contact in contacts.data"
                    :key="contact.id"
                    :href="show({ contact: contact.id })"
                    :title="contact.name"
                    :subtitle="contact.mobile ?? contact.phone"
                    :meta="contact.code"
                    :amount="
                        formatDecimal(
                            contact.balance ?? '0',
                            locale,
                            baseCurrency.decimal_places,
                        )
                    "
                    :currency="baseCurrency.code"
                    :amount-class="balanceClass(contact.balance ?? '0')"
                >
                    <BaseBadge v-if="contact.is_customer" color="primary">
                        {{ t('Customer') }}
                    </BaseBadge>
                    <BaseBadge v-if="contact.is_vendor" color="warning">
                        {{ t('Vendor') }}
                    </BaseBadge>
                    <BaseBadge v-if="!contact.is_active" color="light">
                        {{ t('Inactive') }}
                    </BaseBadge>
                </MobileRecordCard>
            </MobileRecordList>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table
                    v-if="contacts.data.length"
                    class="hidden min-w-full sm:table"
                >
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('code')"
                                >
                                    {{ t('Code') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('name')"
                                >
                                    {{ t('Name') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Phone') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Balance') }}
                                <span class="text-theme-xs font-normal"
                                    >({{ baseCurrency.code }})</span
                                >
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="contact in contacts.data"
                            :key="contact.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell font-mono" dir="ltr">
                                {{ contact.code }}
                            </td>
                            <td class="td-cell">
                                <Link
                                    :href="show({ contact: contact.id })"
                                    class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90"
                                >
                                    {{ contact.name }}
                                </Link>
                                <span v-if="!contact.is_active" class="ms-2">
                                    <BaseBadge color="light">{{
                                        t('Inactive')
                                    }}</BaseBadge>
                                </span>
                                <span
                                    v-if="contact.email"
                                    class="block text-theme-xs"
                                    dir="ltr"
                                    >{{ contact.email }}</span
                                >
                            </td>
                            <td class="td-cell">
                                <div class="flex flex-wrap gap-1">
                                    <BaseBadge
                                        v-if="contact.is_customer"
                                        color="primary"
                                        >{{ t('Customer') }}</BaseBadge
                                    >
                                    <BaseBadge
                                        v-if="contact.is_vendor"
                                        color="warning"
                                        >{{ t('Vendor') }}</BaseBadge
                                    >
                                </div>
                            </td>
                            <td class="td-cell" dir="ltr">
                                {{ contact.mobile ?? contact.phone ?? '—' }}
                            </td>
                            <td
                                class="td-cell text-end font-mono"
                                dir="ltr"
                                :class="balanceClass(contact.balance ?? '0')"
                            >
                                {{
                                    formatDecimal(
                                        contact.balance ?? '0',
                                        locale,
                                        baseCurrency.decimal_places,
                                    )
                                }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <BaseIconButton
                                        v-if="contact.can.update"
                                        :href="edit({ contact: contact.id })"
                                        :label="t('Edit')"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="contact.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(contact)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No contacts found.')">
                    <BaseButton
                        v-if="can('Contact Create')"
                        size="sm"
                        :href="create()"
                    >
                        <Plus class="size-5" />
                        {{ t('Add customer') }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete contact')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: deletion.target?.name ?? '' },
                )
            "
            :processing="deletion.processing"
            @update:open="(open) => !open && deletion.cancel()"
            @confirm="deletion.confirm"
        />
    </div>
</template>
