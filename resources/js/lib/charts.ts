/**
 * The chart component of the admin panel.
 *
 * It is built on the bare ApexCharts core plus the bar chart and the legend,
 * the only parts the panel draws, instead of the default bundle with every
 * chart type and feature. Add a chart type here before using it.
 */
import 'apexcharts/bar';
import 'apexcharts/features/legend';

export { default } from 'vue3-apexcharts/core';
