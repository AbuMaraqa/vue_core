<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import ArrowRight from '~icons/solar/arrow-right-linear';
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
import { cancel, index, pdf } from '@/routes/admin/banking/transfers';
import type { BankTransfer, Resource } from '@/types';

const props = defineProps<{
    transfer: Resource<BankTransfer>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const transfer = computed(() => props.transfer.data);

const confirmingCancel = ref(false);
const processing = ref(false);

const fromAmount = (value: string): string =>
    formatDecimal(
        value,
        locale.value,
        transfer.value.from_account?.decimal_places ?? 2,
    );

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
            :title="t('Transfer :number', { number: transfer.number })"
            :breadcrumbs="[
                { title: t('Bank Transfers'), href: index() },
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
            <div
                class="grid grid-cols-1 items-center gap-4 md:grid-cols-[1fr_auto_1fr]"
            >
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ t('From account') }}
                    </p>
                    <p
                        class="mt-1 font-medium text-gray-800 dark:text-white/90"
                    >
                        {{ transfer.from_account?.label ?? '—' }}
                    </p>
                    <p
                        class="mt-2 font-mono text-xl font-semibold text-error-600 dark:text-error-500"
                        dir="ltr"
                    >
                        −{{ fromAmount(transfer.amount) }}
                        <span class="text-theme-xs font-normal text-gray-400">
                            {{ transfer.from_account?.currency }}
                        </span>
                    </p>
                    <p
                        v-if="Number(transfer.fees) > 0"
                        class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t('Bank fees') }}:
                        <span class="font-mono" dir="ltr">
                            {{ fromAmount(transfer.fees) }}
                            {{ transfer.from_account?.currency }}
                        </span>
                    </p>
                </div>
                <ArrowRight
                    class="mx-auto size-6 rotate-90 text-gray-400 md:rotate-0 rtl:md:rotate-180"
                />
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ t('To account') }}
                    </p>
                    <p
                        class="mt-1 font-medium text-gray-800 dark:text-white/90"
                    >
                        {{ transfer.to_account?.label ?? '—' }}
                    </p>
                    <p
                        class="mt-2 font-mono text-xl font-semibold text-success-600 dark:text-success-500"
                        dir="ltr"
                    >
                        +{{
                            formatDecimal(
                                transfer.to_amount,
                                locale,
                                transfer.to_account?.decimal_places ?? 2,
                            )
                        }}
                        <span class="text-theme-xs font-normal text-gray-400">
                            {{ transfer.to_account?.currency }}
                        </span>
                    </p>
                </div>
            </div>

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
                            {{ t('Reference') }}
                        </dt>
                        <dd
                            class="mt-1 text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ transfer.reference ?? '—' }}
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
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Description') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ transfer.description ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="transfer.journal_entries?.length">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Journal entries') }}
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <template
                                v-for="entry in transfer.journal_entries"
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
        </div>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel transfer')"
            :message="
                t(
                    'The journal entries of this transfer will be reversed. This action cannot be undone.',
                )
            "
            :confirm-label="t('Cancel transfer')"
            :processing="processing"
            @confirm="cancelTransfer"
        />
    </div>
</template>
