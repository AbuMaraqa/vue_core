<script setup lang="ts">
/**
 * A financial statement as a list of lines: section headings, account lines,
 * subtotals and the grand result. Used by the balance sheet, income
 * statement, cash flow statement and tax report.
 */
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';

export type StatementLine = {
    label: string;
    kind: 'heading' | 'line' | 'subtotal' | 'total';
    amount?: string | null;
    previous?: string | null;
    depth?: number;
};

defineProps<{
    lines: StatementLine[];
    decimals: number;
    currency: string;
    /**
     * Show the comparison column.
     */
    compare?: boolean;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

function amount(value: string | null | undefined, decimals: number): string {
    return value === null || value === undefined
        ? ''
        : formatDecimal(value, locale.value, decimals);
}
</script>

<template>
    <div class="custom-scrollbar max-w-full overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="th-cell" />
                    <th class="th-cell text-end">
                        {{ compare ? t('This period') : currency }}
                    </th>
                    <th v-if="compare" class="th-cell text-end">
                        {{ t('Previous period') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(line, position) in lines"
                    :key="position"
                    :class="{
                        'bg-gray-50 dark:bg-white/[0.02]':
                            line.kind === 'heading',
                        'border-t border-gray-100 dark:border-gray-800':
                            line.kind === 'subtotal',
                        'border-t-2 border-gray-300 dark:border-gray-600':
                            line.kind === 'total',
                    }"
                >
                    <td
                        :class="[
                            'px-5 py-2.5 text-theme-sm',
                            line.kind === 'line'
                                ? 'text-gray-600 dark:text-gray-400'
                                : 'font-semibold text-gray-800 dark:text-white/90',
                        ]"
                        :style="{
                            paddingInlineStart: `${1.25 + (line.depth ?? (line.kind === 'line' ? 1 : 0)) * 1.25}rem`,
                        }"
                    >
                        {{ line.label }}
                    </td>
                    <td
                        :class="[
                            'px-5 py-2.5 text-end font-mono text-theme-sm',
                            line.kind === 'line'
                                ? 'text-gray-700 dark:text-gray-300'
                                : 'font-semibold text-gray-800 dark:text-white/90',
                            {
                                'text-error-600':
                                    line.kind !== 'heading' &&
                                    Number(line.amount) < 0,
                            },
                        ]"
                        dir="ltr"
                    >
                        {{ amount(line.amount, decimals) }}
                    </td>
                    <td
                        v-if="compare"
                        class="px-5 py-2.5 text-end font-mono text-theme-sm text-gray-500"
                        dir="ltr"
                    >
                        {{ amount(line.previous, decimals) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
