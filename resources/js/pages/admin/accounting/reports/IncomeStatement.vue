<script setup lang="ts">
import { computed } from 'vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import StatementTable from '@/components/accounting/StatementTable.vue';
import type { StatementLine } from '@/components/accounting/StatementTable.vue';
import DataCard from '@/components/common/DataCard.vue';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { incomeStatement } from '@/routes/admin/accounting/reports';
import type { ComboboxOption } from '@/types';

type Section = {
    key: string;
    title: string;
    accounts: Array<{
        account_id: number;
        code: string;
        name: string;
        amount: string;
        previous: string | null;
    }>;
    total: string;
    previous_total: string | null;
};

type Results = {
    gross_profit: string;
    operating_profit: string;
    net_profit: string;
};

const props = defineProps<{
    report: { sections: Section[]; previous: Results | null } & Results;
    filters: {
        from: string;
        to: string;
        cost_center_id: number | null;
        compare: boolean;
    };
    costCenters: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();

const { filters, apply } = useReportFilters(incomeStatement, {
    ...props.filters,
});

/**
 * Each result follows the section that completes it.
 */
const resultsAfter: Record<string, keyof Results> = {
    cost_of_sales: 'gross_profit',
    operating_expenses: 'operating_profit',
    other_expenses: 'net_profit',
};

const lines = computed<StatementLine[]>(() =>
    props.report.sections.flatMap((section): StatementLine[] => {
        const result = resultsAfter[section.key];

        return [
            { label: section.title, kind: 'heading' },
            ...section.accounts.map((account): StatementLine => ({
                label: `${account.code} ${account.name}`,
                kind: 'line',
                amount: account.amount,
                previous: account.previous,
            })),
            {
                label: `${t('Total')} ${section.title}`,
                kind: 'subtotal',
                amount: section.total,
                previous: section.previous_total,
            },
            ...(result
                ? [
                      {
                          label: t(
                              result === 'gross_profit'
                                  ? 'Gross profit'
                                  : result === 'operating_profit'
                                    ? 'Operating profit'
                                    : 'Net profit',
                          ),
                          kind: 'total' as const,
                          amount: props.report[result],
                          previous: props.report.previous?.[result] ?? null,
                      },
                  ]
                : []),
        ];
    }),
);
</script>

<template>
    <ReportShell
        :title="t('Income Statement')"
        :route="incomeStatement"
        @apply="apply"
    >
        <template #filters>
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
            <BaseCheckbox v-model="filters.compare" class="mb-3">{{
                t('Compare with the previous period')
            }}</BaseCheckbox>
        </template>

        <DataCard>
            <StatementTable
                :lines="lines"
                :decimals="baseCurrency.decimal_places"
                :currency="baseCurrency.code"
                :compare="Boolean(report.previous)"
            />
        </DataCard>
    </ReportShell>
</template>
