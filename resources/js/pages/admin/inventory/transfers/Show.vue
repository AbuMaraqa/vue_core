<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import CloseCircle from '~icons/solar/close-circle-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { cancel, index, pdf } from '@/routes/admin/inventory/transfers';
import type { Resource, StockTransfer } from '@/types';

const props = defineProps<{
    transfer: Resource<StockTransfer>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const transfer = computed(() => props.transfer.data);

const confirmingCancel = ref(false);
const processing = ref(false);

/**
 * Quantities show two decimals, or four when they need them.
 */
const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);

function cancelTransfer(): void {
    router.visit(cancel({ transfer: transfer.value.id }), {
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
            :title="t('Stock transfer :number', { number: transfer.number })"
            :breadcrumbs="[
                { title: t('Stock Transfers'), href: index() },
                { title: transfer.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ transfer: transfer.id })" />
                <BaseButton
                    v-if="transfer.can.cancel"
                    variant="outline"
                    @click="confirmingCancel = true"
                >
                    <CloseCircle class="size-5" />
                    {{ t('Cancel transfer') }}
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
                            <BaseBadge :color="transfer.status_color">
                                {{ transfer.status_label }}
                            </BaseBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Date') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ formatDate(transfer.date, locale) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('From warehouse') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ transfer.from_warehouse ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('To warehouse') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ transfer.to_warehouse ?? '—' }}
                        </dd>
                    </div>
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Description') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ transfer.description ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Created by') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ transfer.created_by ?? t('System') }}
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
                                <th class="th-cell">{{ t('Unit') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Quantity') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="line in transfer.lines" :key="line.id">
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ line.item ?? '—' }}
                                </td>
                                <td class="td-cell">{{ line.unit ?? '—' }}</td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ quantity(line.quantity) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </BaseCard>
        </div>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel stock transfer')"
            :message="
                t(
                    'The stock will be moved back to the source warehouse. This action cannot be undone.',
                )
            "
            :confirm-label="t('Cancel transfer')"
            :processing="processing"
            @confirm="cancelTransfer"
        />
    </div>
</template>
