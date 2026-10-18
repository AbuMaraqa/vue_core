<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';
import Banknote from '~icons/solar/banknote-linear';
import Bill from '~icons/solar/bill-list-linear';
import Cart from '~icons/solar/cart-large-2-linear';
import Wallet from '~icons/solar/wallet-money-linear';
import BaseCard from '@/components/base/BaseCard.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import { useLocale } from '@/composables/useLocale';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal, formatMoney } from '@/lib/format';
import {
    balanceSheet,
    cashFlow,
    incomeStatement,
    taxSummary,
    trialBalance,
} from '@/routes/admin/accounting/reports';

// The charting library is large, so it is only downloaded once the chart is rendered.
const RevenueExpensesChart = defineAsyncComponent(
    () => import('@/components/accounting/RevenueExpensesChart.vue'),
);

const props = defineProps<{
    overview: {
        cash: string;
        receivables: string;
        cheques_receivable: string;
        payables: string;
        month_revenue: string;
        month_expenses: string;
        year_profit: string;
        trend: Array<{ month: string; revenue: string; expenses: string }>;
        top_expenses: Array<{ account: string; amount: string }>;
    };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const money = (amount: string): string =>
    formatMoney(
        amount,
        props.baseCurrency.code,
        locale.value,
        props.baseCurrency.decimal_places,
    );

const simpleMode = useSimpleMode();

// Simple mode keeps the one statement an owner reads: did I make a profit.
const reports = computed(() =>
    simpleMode.value
        ? [{ title: 'Profit and loss', route: incomeStatement }]
        : [
              { title: 'Balance Sheet', route: balanceSheet },
              { title: 'Income Statement', route: incomeStatement },
              { title: 'Cash Flow Statement', route: cashFlow },
              { title: 'Trial Balance', route: trialBalance },
              { title: 'Tax Report', route: taxSummary },
          ],
);
</script>

<template>
    <div>
        <PageHeader
            :title="
                simpleMode ? t('Financial summary') : t('Financial Overview')
            "
            :breadcrumbs="[
                {
                    title: simpleMode
                        ? t('Financial summary')
                        : t('Financial Overview'),
                },
            ]"
        />

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4"
        >
            <StatCard
                :label="t('Cash and banks')"
                :value="money(overview.cash)"
                :icon="Wallet"
            />
            <StatCard
                :label="simpleMode ? t('Customers owe you') : t('Receivables')"
                :value="money(overview.receivables)"
                :icon="Bill"
            />
            <StatCard
                :label="t('Cheques to collect')"
                :value="money(overview.cheques_receivable)"
                :icon="Banknote"
            />
            <StatCard
                :label="simpleMode ? t('You owe vendors') : t('Payables')"
                :value="money(overview.payables)"
                :icon="Cart"
            />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <BaseCard
                class="xl:col-span-2"
                :title="t('Revenue and expenses')"
                :description="t('The last 12 months')"
            >
                <div class="min-h-[300px]">
                    <RevenueExpensesChart :data="overview.trend" />
                </div>
            </BaseCard>

            <div class="space-y-6">
                <BaseCard :title="t('This month')">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Revenue') }}
                            </dt>
                            <dd
                                class="font-mono font-medium text-success-600"
                                dir="ltr"
                            >
                                {{ money(overview.month_revenue) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Expenses') }}
                            </dt>
                            <dd
                                class="font-mono font-medium text-error-600"
                                dir="ltr"
                            >
                                {{ money(overview.month_expenses) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t border-gray-100 pt-3 dark:border-gray-800"
                        >
                            <dt
                                class="font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ t('Profit this fiscal year') }}
                            </dt>
                            <dd
                                :class="[
                                    'font-mono font-semibold',
                                    Number(overview.year_profit) >= 0
                                        ? 'text-success-600'
                                        : 'text-error-600',
                                ]"
                                dir="ltr"
                            >
                                {{ money(overview.year_profit) }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard :title="t('Largest expenses this year')">
                    <ul
                        v-if="overview.top_expenses.length"
                        class="space-y-3 text-sm"
                    >
                        <li
                            v-for="expense in overview.top_expenses"
                            :key="expense.account"
                            class="flex justify-between gap-3"
                        >
                            <span
                                class="truncate text-gray-700 dark:text-gray-300"
                                >{{ expense.account }}</span
                            >
                            <span class="font-mono" dir="ltr">{{
                                formatDecimal(
                                    expense.amount,
                                    locale,
                                    baseCurrency.decimal_places,
                                )
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">
                        {{ t('No expenses yet.') }}
                    </p>
                </BaseCard>
            </div>
        </div>

        <BaseCard class="mt-6" :title="t('Reports')">
            <div class="flex flex-wrap gap-3">
                <Link
                    v-for="report in reports"
                    :key="report.title"
                    :href="report.route()"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300"
                >
                    {{ t(report.title) }}
                </Link>
            </div>
        </BaseCard>
    </div>
</template>
