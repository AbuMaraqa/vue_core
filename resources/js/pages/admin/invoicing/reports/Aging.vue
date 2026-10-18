<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ReportShell from '@/components/accounting/ReportShell.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useReportFilters } from '@/composables/useReportFilters';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { show as showContact } from '@/routes/admin/contacts';
import { aging } from '@/routes/admin/invoicing/reports';
import type { AgingBucket, AgingReport } from '@/types';

const props = defineProps<{
    report: AgingReport;
    filters: { side: 'receivables' | 'payables'; as_of: string };
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters, apply } = useReportFilters(aging, { ...props.filters });

const isReceivables = computed(() => props.filters.side === 'receivables');

const amount = (value: string): string =>
    Number(value)
        ? formatDecimal(value, locale.value, props.baseCurrency.decimal_places)
        : '—';

const buckets = computed(
    (): Array<{ key: AgingBucket; label: string; late: boolean }> => [
        { key: 'current', label: t('Not yet due'), late: false },
        { key: '1_30', label: t('1-30 days'), late: false },
        { key: '31_60', label: t('31-60 days'), late: false },
        { key: '61_90', label: t('61-90 days'), late: true },
        { key: 'over_90', label: t('Over 90 days'), late: true },
    ],
);
</script>

<template>
    <ReportShell
        :title="isReceivables ? t('Aged Receivables') : t('Aged Payables')"
        :description="
            isReceivables
                ? t('What customers still owe, by how late it is.')
                : t('What we still owe vendors, by how late it is.')
        "
        :route="aging"
        export-permission="Invoicing Report Export"
        :section="isReceivables ? t('Sales') : t('Purchases')"
        @apply="apply"
    >
        <template #filters>
            <BaseField :label="t('Report')" for="side">
                <BaseSelect id="side" v-model="filters.side" class="w-52">
                    <option value="receivables">
                        {{ t('Aged Receivables') }}
                    </option>
                    <option value="payables">{{ t('Aged Payables') }}</option>
                </BaseSelect>
            </BaseField>
            <BaseField :label="t('As of')" for="as_of">
                <BaseInput id="as_of" v-model="filters.as_of" type="date" />
            </BaseField>
        </template>

        <DataCard>
            <div class="px-5 py-3 sm:px-6">
                <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{
                        t('Amounts in :currency', {
                            currency: baseCurrency.code,
                        })
                    }}
                </span>
            </div>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="report.contacts.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                {{
                                    isReceivables ? t('Customer') : t('Vendor')
                                }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('Documents') }}
                            </th>
                            <th
                                v-for="bucket in buckets"
                                :key="bucket.key"
                                class="th-cell text-end"
                            >
                                {{ bucket.label }}
                            </th>
                            <th class="th-cell text-end">{{ t('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="row in report.contacts"
                            :key="row.contact_id"
                        >
                            <td class="td-cell">
                                <Link
                                    v-if="can('Contact View')"
                                    :href="
                                        showContact({ contact: row.contact_id })
                                    "
                                    class="text-gray-800 hover:text-brand-500 dark:text-white/90"
                                >
                                    {{ row.contact }}
                                </Link>
                                <span
                                    v-else
                                    class="text-gray-800 dark:text-white/90"
                                >
                                    {{ row.contact }}
                                </span>
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{ row.invoices }}
                            </td>
                            <td
                                v-for="bucket in buckets"
                                :key="bucket.key"
                                :class="[
                                    'td-cell text-end font-mono',
                                    bucket.late && Number(row[bucket.key])
                                        ? 'bg-error-50/60 font-medium text-error-600 dark:bg-error-500/10 dark:text-error-500'
                                        : '',
                                ]"
                                dir="ltr"
                            >
                                {{ amount(row[bucket.key]) }}
                            </td>
                            <td
                                class="td-cell text-end font-mono font-medium text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount(row.total) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                        >
                            <td class="px-5 py-3" colspan="2">
                                {{ t('Total') }}
                            </td>
                            <td
                                v-for="bucket in buckets"
                                :key="bucket.key"
                                :class="[
                                    'px-5 py-3 text-end font-mono',
                                    bucket.late &&
                                    Number(report.totals[bucket.key])
                                        ? 'text-error-600 dark:text-error-500'
                                        : '',
                                ]"
                                dir="ltr"
                            >
                                {{ amount(report.totals[bucket.key]) }}
                            </td>
                            <td class="px-5 py-3 text-end font-mono" dir="ltr">
                                {{ amount(report.totals.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <EmptyState
                    v-else
                    :message="
                        isReceivables
                            ? t('No customer owes anything on this date.')
                            : t('Nothing is owed to vendors on this date.')
                    "
                />
            </div>
        </DataCard>
    </ReportShell>
</template>
