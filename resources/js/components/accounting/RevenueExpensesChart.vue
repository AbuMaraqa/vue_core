<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import VueApexCharts from '@/lib/charts';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    data: Array<{ month: string; revenue: string; expenses: string }>;
}>();

const page = usePage();
const { locale } = useLocale();
const { t } = useTranslations();

const series = computed(() => [
    {
        name: t('Revenue'),
        data: props.data.map((point) => Number(point.revenue)),
    },
    {
        name: t('Expenses'),
        data: props.data.map((point) => Number(point.expenses)),
    },
]);

const options = computed<ApexOptions>(() => ({
    colors: [page.props.theme.primaryColor, '#f04438'],
    chart: { fontFamily: 'inherit', type: 'bar', toolbar: { show: false } },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '45%',
            borderRadius: 4,
            borderRadiusApplication: 'end',
        },
    },
    dataLabels: { enabled: false },
    stroke: { show: true, width: 3, colors: ['transparent'] },
    xaxis: {
        categories: props.data.map((point) =>
            new Intl.DateTimeFormat(locale.value, { month: 'short' }).format(
                new Date(`${point.month}-01`),
            ),
        ),
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            formatter: (value: number) =>
                new Intl.NumberFormat(`${locale.value}-u-nu-latn`, {
                    notation: 'compact',
                }).format(value),
        },
    },
    legend: { position: 'top', horizontalAlign: 'left' },
    grid: { yaxis: { lines: { show: true } } },
    fill: { opacity: 1 },
}));
</script>

<template>
    <VueApexCharts
        type="bar"
        height="300"
        :options="options"
        :series="series"
    />
</template>
