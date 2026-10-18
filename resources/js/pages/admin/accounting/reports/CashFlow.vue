<script setup lang="ts">
import { computed } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import StatementTable from '@/components/accounting/StatementTable.vue';
import type { StatementLine } from '@/components/accounting/StatementTable.vue';
import DataCard from '@/components/common/DataCard.vue';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { cashFlow } from '@/routes/admin/accounting/reports';

type Section = {
    key: string;
    title: string;
    items: Array<{ label: string; amount: string }>;
    total: string;
};

const props = defineProps<{
    report: {
        sections: Section[];
        net_change: string;
        opening_cash: string;
        closing_cash: string;
        reconciled: boolean;
    };
    filters: { from: string; to: string };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();

const { filters, apply } = useReportFilters(cashFlow, { ...props.filters });

const lines = computed<StatementLine[]>(() => [
    ...props.report.sections.flatMap((section): StatementLine[] => [
        { label: section.title, kind: 'heading' },
        ...section.items.map((item): StatementLine => ({
            label: item.label,
            kind: 'line',
            amount: item.amount,
        })),
        {
            label: `${t('Net cash from')} ${section.title}`,
            kind: 'subtotal',
            amount: section.total,
        },
    ]),
    {
        label: t('Net change in cash'),
        kind: 'total',
        amount: props.report.net_change,
    },
    {
        label: t('Cash at the beginning of the period'),
        kind: 'subtotal',
        amount: props.report.opening_cash,
    },
    {
        label: t('Cash at the end of the period'),
        kind: 'total',
        amount: props.report.closing_cash,
    },
]);
</script>

<template>
    <ReportShell
        :title="t('Cash Flow Statement')"
        :description="
            t(
                'Indirect method: the profit of the period adjusted to the cash it produced.',
            )
        "
        :route="cashFlow"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('From')" for="from"
                ><BaseInput id="from" v-model="filters.from" type="date"
            /></BaseField>
            <BaseField :label="t('To')" for="to"
                ><BaseInput id="to" v-model="filters.to" type="date"
            /></BaseField>
        </template>

        <div class="mb-4 flex justify-end">
            <BaseBadge :color="report.reconciled ? 'success' : 'error'">
                {{
                    report.reconciled
                        ? t('Matches the cash and bank balances')
                        : t('Does not match the cash balances')
                }}
            </BaseBadge>
        </div>

        <DataCard>
            <StatementTable
                :lines="lines"
                :decimals="baseCurrency.decimal_places"
                :currency="baseCurrency.code"
            />
        </DataCard>
    </ReportShell>
</template>
