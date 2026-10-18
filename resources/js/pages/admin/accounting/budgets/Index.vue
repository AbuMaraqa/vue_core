<script setup lang="ts">
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { create, destroy, edit } from '@/routes/admin/accounting/budgets';

type BudgetRow = {
    id: number;
    name: string;
    fiscal_year: string;
    cost_center: string | null;
    total: string;
};

defineProps<{
    budgets: BudgetRow[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const deletion = useConfirmDelete((budget: BudgetRow) =>
    destroy({ budget: budget.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Budgets')"
            :breadcrumbs="[{ title: t('Budgets') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Budget Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('New budget') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="budgets.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Fiscal year') }}</th>
                            <th class="th-cell">{{ t('Cost center') }}</th>
                            <th class="th-cell text-end">{{ t('Total') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="budget in budgets" :key="budget.id">
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ budget.name }}
                            </td>
                            <td class="td-cell">{{ budget.fiscal_year }}</td>
                            <td class="td-cell">
                                {{ budget.cost_center ?? t('Whole company') }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ formatDecimal(budget.total, locale) }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="can('Budget Update')"
                                        :href="edit({ budget: budget.id })"
                                        :label="t('Edit')"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Budget Delete')"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(budget)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No budgets yet.')" />
            </div>
        </DataCard>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete budget')"
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
