<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show } from '@/routes/admin/accounting/journal-entries';
import { generalLedger } from '@/routes/admin/accounting/reports';
import type { ComboboxOption } from '@/types';

type Line = {
    entry_id: number;
    date: string;
    number: string | null;
    description: string | null;
    reference: string | null;
    partner: string | null;
    cost_center: string | null;
    debit: string;
    credit: string;
    balance: string;
};

const props = defineProps<{
    report: {
        account: { id: number; label: string; type: string };
        opening: string;
        closing: string;
        total_debit: string;
        total_credit: string;
        lines: Line[];
        truncated: boolean;
    } | null;
    filters: {
        from: string;
        to: string;
        account_id: number | null;
        cost_center_id: number | null;
    };
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const { filters, apply } = useReportFilters(generalLedger, {
    ...props.filters,
});

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);
</script>

<template>
    <ReportShell
        :title="t('General Ledger Report')"
        :route="report ? generalLedger : undefined"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('Account')" class="w-full sm:w-80">
                <BaseCombobox
                    v-model="filters.account_id"
                    :options="accounts"
                    :placeholder="t('Choose an account')"
                />
            </BaseField>
            <BaseField :label="t('From')" for="from"
                ><BaseInput id="from" v-model="filters.from" type="date"
            /></BaseField>
            <BaseField :label="t('To')" for="to"
                ><BaseInput id="to" v-model="filters.to" type="date"
            /></BaseField>
            <BaseField :label="t('Cost center')" class="w-56">
                <BaseCombobox
                    v-model="filters.cost_center_id"
                    :options="costCenters"
                    clearable
                    :placeholder="t('All')"
                />
            </BaseField>
        </template>

        <DataCard v-if="report">
            <div class="px-5 py-3 sm:px-6">
                <h3 class="font-semibold text-gray-800 dark:text-white/90">
                    {{ report.account.label }}
                </h3>
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{
                        t('Amounts in :currency', {
                            currency: baseCurrency.code,
                        })
                    }}
                </p>
            </div>
            <BaseAlert
                v-if="report.truncated"
                variant="warning"
                class="mx-5 mb-3"
                :title="
                    t(
                        'Only the first lines are shown. Narrow the period or export the report.',
                    )
                "
            />
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell">{{ t('Number') }}</th>
                            <th class="th-cell">{{ t('Description') }}</th>
                            <th class="th-cell">{{ t('Partner') }}</th>
                            <th class="th-cell text-end">{{ t('Debit') }}</th>
                            <th class="th-cell text-end">{{ t('Credit') }}</th>
                            <th class="th-cell text-end">{{ t('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr class="bg-gray-50 font-medium dark:bg-white/[0.02]">
                            <td class="td-cell" colspan="6">
                                {{ t('Opening balance') }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(report.opening) }}
                            </td>
                        </tr>
                        <tr
                            v-for="(line, position) in report.lines"
                            :key="position"
                        >
                            <td class="td-cell whitespace-nowrap">
                                {{ formatDate(line.date, locale) }}
                            </td>
                            <td class="td-cell">
                                <Link
                                    :href="
                                        show({ journalEntry: line.entry_id })
                                    "
                                    class="text-brand-500 hover:underline"
                                    dir="ltr"
                                    >{{ line.number }}</Link
                                >
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ line.description ?? '—' }}
                            </td>
                            <td class="td-cell">{{ line.partner ?? '—' }}</td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    Number(line.debit) ? amount(line.debit) : ''
                                }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    Number(line.credit)
                                        ? amount(line.credit)
                                        : ''
                                }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ amount(line.balance) }}
                            </td>
                        </tr>
                        <tr v-if="!report.lines.length">
                            <td colspan="7">
                                <EmptyState
                                    :message="t('No postings in this period.')"
                                />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="4">
                                {{ t('Closing balance') }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.total_debit) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.total_credit) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.closing) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </DataCard>
        <DataCard v-else>
            <EmptyState :message="t('Choose an account to see its ledger.')" />
        </DataCard>
    </ReportShell>
</template>
