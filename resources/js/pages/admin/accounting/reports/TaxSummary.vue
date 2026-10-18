<script setup lang="ts">
import { computed } from 'vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import StatementTable from '@/components/accounting/StatementTable.vue';
import type { StatementLine } from '@/components/accounting/StatementTable.vue';
import DataCard from '@/components/common/DataCard.vue';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { taxSummary } from '@/routes/admin/accounting/reports';

type Row = { account: string; amount: string };

const props = defineProps<{
    report: {
        output: Row[];
        input: Row[];
        total_output: string;
        total_input: string;
        net: string;
    };
    filters: { from: string; to: string };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();

const { filters, apply } = useReportFilters(taxSummary, { ...props.filters });

const lines = computed<StatementLine[]>(() => [
    { label: t('Output tax (sales)'), kind: 'heading' },
    ...props.report.output.map((row): StatementLine => ({
        label: row.account,
        kind: 'line',
        amount: row.amount,
    })),
    {
        label: t('Total output tax'),
        kind: 'subtotal',
        amount: props.report.total_output,
    },
    { label: t('Input tax (purchases)'), kind: 'heading' },
    ...props.report.input.map((row): StatementLine => ({
        label: row.account,
        kind: 'line',
        amount: row.amount,
    })),
    {
        label: t('Total input tax'),
        kind: 'subtotal',
        amount: props.report.total_input,
    },
    {
        label:
            Number(props.report.net) >= 0
                ? t('Net tax payable')
                : t('Net tax recoverable'),
        kind: 'total',
        amount: props.report.net,
    },
]);
</script>

<template>
    <ReportShell
        :title="t('Tax Report')"
        :description="t('Tax collected on sales minus tax paid on purchases.')"
        :route="taxSummary"
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

        <DataCard>
            <StatementTable
                :lines="lines"
                :decimals="baseCurrency.decimal_places"
                :currency="baseCurrency.code"
            />
        </DataCard>
    </ReportShell>
</template>
