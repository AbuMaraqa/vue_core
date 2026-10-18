<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import VueApexCharts from '@/lib/charts';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    data: Array<{ month: string; total: number }>;
}>();

const page = usePage();
const { locale } = useLocale();
const { t } = useTranslations();

const series = computed(() => [
    { name: t('New users'), data: props.data.map((point) => point.total) },
]);

const options = computed<ApexOptions>(() => ({
    colors: [page.props.theme.primaryColor],
    chart: {
        fontFamily: 'inherit',
        type: 'bar',
        toolbar: { show: false },
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '39%',
            borderRadius: 5,
            borderRadiusApplication: 'end',
        },
    },
    dataLabels: { enabled: false },
    stroke: { show: true, width: 4, colors: ['transparent'] },
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
            formatter: (value: number) => String(Math.round(value)),
        },
    },
    legend: { show: false },
    grid: { yaxis: { lines: { show: true } } },
    fill: { opacity: 1 },
    tooltip: { x: { show: false } },
}));
</script>

<template>
    <div class="custom-scrollbar max-w-full overflow-x-auto">
        <div class="-ms-5 min-w-[650px] ps-2 xl:min-w-full">
            <VueApexCharts
                type="bar"
                height="220"
                :options="options"
                :series="series"
            />
        </div>
    </div>
</template>
