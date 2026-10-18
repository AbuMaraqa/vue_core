<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import CloseCircle from '~icons/solar/close-circle-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { cancel, index, pdf } from '@/routes/admin/inventory/adjustments';
import type { Resource, StockAdjustment } from '@/types';

const props = defineProps<{
    adjustment: Resource<StockAdjustment>;
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const adjustment = computed(() => props.adjustment.data);

const confirmingCancel = ref(false);
const processing = ref(false);

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);

function cancelAdjustment(): void {
    router.visit(cancel({ adjustment: adjustment.value.id }), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            confirmingCancel.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="
                t('Stock adjustment :number', { number: adjustment.number })
            "
            :breadcrumbs="[
                { title: t('Stock Adjustments'), href: index() },
                { title: adjustment.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ adjustment: adjustment.id })" />
                <BaseButton
                    v-if="adjustment.can.cancel"
                    variant="outline"
                    @click="confirmingCancel = true"
                >
                    <CloseCircle class="size-5" />
                    {{ t('Cancel adjustment') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <BaseCard>
                <dl
                    class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-4"
                >
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Status') }}
                        </dt>
                        <dd class="mt-1">
                            <BaseBadge :color="adjustment.status_color">
                                {{ adjustment.status_label }}
                            </BaseBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Date') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ formatDate(adjustment.date, locale) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Warehouse') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ adjustment.warehouse ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Adjustment account') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ adjustment.account ?? '—' }}
                        </dd>
                    </div>
                    <div class="col-span-2 sm:col-span-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Description') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ adjustment.description ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Created by') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ adjustment.created_by ?? t('System') }}
                        </dd>
                    </div>
                    <div v-if="adjustment.journal_entries?.length">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Journal entries') }}
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <template
                                v-for="entry in adjustment.journal_entries"
                                :key="entry.id"
                            >
                                <Link
                                    v-if="can('Journal Entry View List')"
                                    :href="
                                        showEntry({ journalEntry: entry.id })
                                    "
                                    class="text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ entry.number }}
                                </Link>
                                <span
                                    v-else
                                    class="text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ entry.number }}
                                </span>
                            </template>
                        </dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard :title="t('Items')">
                <div class="custom-scrollbar overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Item') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Quantity') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Unit cost') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="line in adjustment.lines" :key="line.id">
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ line.item ?? '—' }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    :class="
                                        Number(line.quantity) < 0
                                            ? 'text-error-600 dark:text-error-500'
                                            : 'text-success-600 dark:text-success-500'
                                    "
                                    dir="ltr"
                                >
                                    {{ Number(line.quantity) > 0 ? '+' : ''
                                    }}{{ quantity(line.quantity) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        formatDecimal(line.unit_cost, locale, 4)
                                    }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(line.value) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="3">
                                    {{ t('Total') }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        ({{ baseCurrency.code }})
                                    </span>
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(adjustment.total ?? '0') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </BaseCard>
        </div>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel stock adjustment')"
            :message="
                t(
                    'The stock and the journal entry of this adjustment will be reversed. This action cannot be undone.',
                )
            "
            :confirm-label="t('Cancel adjustment')"
            :processing="processing"
            @confirm="cancelAdjustment"
        />
    </div>
</template>
