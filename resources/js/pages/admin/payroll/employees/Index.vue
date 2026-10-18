<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import {
    create,
    destroy,
    edit,
    index,
    show,
} from '@/routes/admin/payroll/employees';
import type { ComboboxOption, Paginated } from '@/types';
import type { Employee, PayrollCurrency, PayrollPage } from '@/types/payroll';

const props = defineProps<{
    employees: PayrollPage<Employee>;
    costCenters: ComboboxOption[];
    filters: {
        search: string | null;
        cost_center: string | null;
        status: string | null;
    };
    baseCurrency: PayrollCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    cost_center: props.filters.cost_center,
    status: props.filters.status,
});

/**
 * The list arrives as a plain paginator: shape it for the pagination bar.
 */
const employeesPage = computed<Paginated<Employee>>(() => ({
    data: props.employees.data,
    links: {
        first: props.employees.first_page_url,
        last: props.employees.last_page_url,
        prev: props.employees.prev_page_url,
        next: props.employees.next_page_url,
    },
    meta: {
        current_page: props.employees.current_page,
        from: props.employees.from,
        last_page: props.employees.last_page,
        links: props.employees.links,
        path: props.employees.path,
        per_page: props.employees.per_page,
        to: props.employees.to,
        total: props.employees.total,
    },
}));

const deletion = useConfirmDelete((employee: Employee) =>
    destroy({ employee: employee.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Employees')"
            :breadcrumbs="[{ title: t('Employees') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Employee Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('Add employee') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="employeesPage">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="
                        t('Search by name, code, national ID or position...')
                    "
                />
            </template>
            <template #filters>
                <BaseSelect
                    v-if="costCenters.length"
                    v-model="filters.cost_center"
                    class="sm:w-52"
                >
                    <option value="">{{ t('All departments') }}</option>
                    <option
                        v-for="center in costCenters"
                        :key="center.value"
                        :value="String(center.value)"
                    >
                        {{ center.label }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.status" class="sm:w-36">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="active">{{ t('Active') }}</option>
                    <option value="inactive">{{ t('Inactive') }}</option>
                </BaseSelect>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="employees.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Code') }}</th>
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Position') }}</th>
                            <th class="th-cell">{{ t('Department') }}</th>
                            <th class="th-cell">{{ t('Hire date') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Basic salary') }}
                                <span class="text-theme-xs font-normal"
                                    >({{ baseCurrency.code }})</span
                                >
                            </th>
                            <th class="th-cell">{{ t('Status') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell font-mono" dir="ltr">
                                {{ employee.code }}
                            </td>
                            <td class="td-cell">
                                <Link
                                    :href="show({ employee: employee.id })"
                                    class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90"
                                >
                                    {{ employee.name }}
                                </Link>
                            </td>
                            <td class="td-cell">
                                {{ employee.position ?? '—' }}
                            </td>
                            <td class="td-cell">
                                {{ employee.cost_center ?? '—' }}
                            </td>
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(employee.hire_date, locale) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatDecimal(
                                        employee.basic_salary,
                                        locale,
                                        baseCurrency.decimal_places,
                                    )
                                }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        employee.is_active ? 'success' : 'light'
                                    "
                                >
                                    {{
                                        employee.is_active
                                            ? t('Active')
                                            : t('Inactive')
                                    }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <BaseIconButton
                                        v-if="employee.can.update"
                                        :href="edit({ employee: employee.id })"
                                        :label="t('Edit')"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="employee.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(employee)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No employees found.')">
                    <BaseButton
                        v-if="can('Employee Create')"
                        size="sm"
                        :href="create()"
                    >
                        <Plus class="size-5" />
                        {{ t('Add employee') }}
                    </BaseButton>
                </EmptyState>
            </div>
        </DataCard>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete employee')"
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
