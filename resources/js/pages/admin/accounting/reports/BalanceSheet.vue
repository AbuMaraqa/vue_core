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
import { balanceSheet } from '@/routes/admin/accounting/reports';

type Group = {
    key: string;
    title: string;
    accounts: Array<{ code: string; name: string; amount: string }>;
    total: string;
};
type Side = { key: string; title: string; groups: Group[]; total: string };

const props = defineProps<{
    report: {
        sides: Side[];
        total_assets: string;
        total_liabilities_and_equity: string;
        balanced: boolean;
    };
    filters: { as_of: string };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();

const { filters, apply } = useReportFilters(balanceSheet, { ...props.filters });

function sideLines(side: Side): StatementLine[] {
    return [
        ...side.groups.flatMap((group): StatementLine[] => [
            { label: group.title, kind: 'heading' },
            ...group.accounts.map((account): StatementLine => ({
                label: `${account.code} ${account.name}`.trim(),
                kind: 'line',
                amount: account.amount,
            })),
            {
                label: `${t('Total')} ${group.title}`,
                kind: 'subtotal',
                amount: group.total,
            },
        ]),
        {
            label: `${t('Total')} ${side.title}`,
            kind: 'total',
            amount: side.total,
        },
    ];
}

const assets = computed(() =>
    props.report.sides.find((side) => side.key === 'assets'),
);
const liabilitiesAndEquity = computed<StatementLine[]>(() => [
    ...props.report.sides
        .filter((side) => side.key !== 'assets')
        .flatMap(sideLines),
    {
        label: t('Total liabilities and equity'),
        kind: 'total',
        amount: props.report.total_liabilities_and_equity,
    },
]);
</script>

<template>
    <ReportShell
        :title="t('Balance Sheet')"
        :route="balanceSheet"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('As of')" for="as_of"
                ><BaseInput id="as_of" v-model="filters.as_of" type="date"
            /></BaseField>
        </template>

        <div class="mb-4 flex justify-end">
            <BaseBadge :color="report.balanced ? 'success' : 'error'">
                {{
                    report.balanced
                        ? t('Assets equal liabilities and equity')
                        : t('Not balanced')
                }}
            </BaseBadge>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <DataCard v-if="assets">
                <h3
                    class="px-5 pt-4 text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ assets.title }}
                </h3>
                <StatementTable
                    :lines="sideLines(assets)"
                    :decimals="baseCurrency.decimal_places"
                    :currency="baseCurrency.code"
                />
            </DataCard>
            <DataCard>
                <h3
                    class="px-5 pt-4 text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('Liabilities and equity') }}
                </h3>
                <StatementTable
                    :lines="liabilitiesAndEquity"
                    :decimals="baseCurrency.decimal_places"
                    :currency="baseCurrency.code"
                />
            </DataCard>
        </div>
    </ReportShell>
</template>
