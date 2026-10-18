<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import Play from '~icons/solar/play-linear';
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal, formatNumber } from '@/lib/format';
import { show, store } from '@/routes/admin/payroll/runs';
import type { Paginated } from '@/types';
import type {
    PayrollCurrency,
    PayrollPage,
    PayrollRunSummary,
} from '@/types/payroll';

const props = defineProps<{
    runs: PayrollPage<PayrollRunSummary>;
    nextMonth: string;
    baseCurrency: PayrollCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

// Simple mode uses the plain wording of the sidebar.
const title = computed(() =>
    simpleMode.value ? t('Monthly salaries') : t('Payroll Runs'),
);

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const periodLabel = (period: string): string =>
    formatDate(`${period}-01`, locale.value, {
        month: 'long',
        year: 'numeric',
    });

const prepareForm = useForm({ month: props.nextMonth });

function prepare(): void {
    prepareForm.submit(store(), { preserveScroll: true });
}

/**
 * The list arrives as a plain paginator: shape it for the pagination bar.
 */
const runsPage = computed<Paginated<PayrollRunSummary>>(() => ({
    data: props.runs.data,
    links: {
        first: props.runs.first_page_url,
        last: props.runs.last_page_url,
        prev: props.runs.prev_page_url,
        next: props.runs.next_page_url,
    },
    meta: {
        current_page: props.runs.current_page,
        from: props.runs.from,
        last_page: props.runs.last_page,
        links: props.runs.links,
        path: props.runs.path,
        per_page: props.runs.per_page,
        to: props.runs.to,
        total: props.runs.total,
    },
}));
</script>

<template>
    <div>
        <PageHeader :title="title" :breadcrumbs="[{ title }]" />

        <BaseCard
            v-if="can('Payroll Run Create')"
            :title="t('New payroll')"
            :description="
                t(
                    'Prepare the payslips of the active employees for a month, review them, then post the payroll.',
                )
            "
            class="mb-6"
        >
            <form
                class="flex flex-wrap items-end gap-4"
                @submit.prevent="prepare"
            >
                <BaseField
                    :label="t('Month')"
                    for="month"
                    required
                    :error="prepareForm.errors.month"
                    class="w-full sm:w-56"
                >
                    <BaseInput
                        id="month"
                        v-model="prepareForm.month"
                        type="month"
                        :invalid="Boolean(prepareForm.errors.month)"
                    />
                </BaseField>
                <BaseButton
                    type="submit"
                    :loading="prepareForm.processing"
                    :disabled="!prepareForm.month"
                >
                    <Play class="size-5" />
                    {{ t('Prepare') }}
                </BaseButton>
            </form>
        </BaseCard>

        <DataCard :pagination="runsPage">
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="runs.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Period') }}</th>
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Employees') }}
                            </th>
                            <th class="th-cell text-end">{{ t('Gross') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Deductions') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Employer cost') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Net pay') }}
                                <span class="text-theme-xs font-normal"
                                    >({{ baseCurrency.code }})</span
                                >
                            </th>
                            <th class="th-cell">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="run in runs.data"
                            :key="run.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell whitespace-nowrap">
                                <Link
                                    :href="show({ run: run.id })"
                                    class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90"
                                >
                                    {{ periodLabel(run.period) }}
                                </Link>
                            </td>
                            <td class="td-cell font-mono" dir="ltr">
                                {{ run.number ?? '—' }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ formatNumber(run.employees, locale) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(run.gross_total) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(run.deductions_total) }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(run.employer_total) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(run.net_total) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge :color="run.status_color">
                                    {{ run.status_label }}
                                </BaseBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No payroll runs yet.')" />
            </div>
        </DataCard>
    </div>
</template>
