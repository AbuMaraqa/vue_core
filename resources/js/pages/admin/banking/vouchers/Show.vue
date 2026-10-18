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
import ShareButton from '@/components/common/ShareButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { cancel, index, pdf } from '@/routes/admin/banking/vouchers';
import type { BankVoucher, Resource } from '@/types';

const props = defineProps<{
    voucher: Resource<BankVoucher>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const voucher = computed(() => props.voucher.data);

const confirmingCancel = ref(false);
const processing = ref(false);

const money = (value: string): string =>
    formatDecimal(
        value,
        locale.value,
        voucher.value.currency?.decimal_places ?? 2,
    );

const isForeign = computed(() => Number(voucher.value.exchange_rate) !== 1);

function cancelVoucher(): void {
    router.visit(cancel({ voucher: voucher.value.id }), {
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
            :title="`${voucher.direction_label} ${voucher.number}`"
            :breadcrumbs="[
                { title: t('Vouchers'), href: index() },
                { title: voucher.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ voucher: voucher.id })" />
                <ShareButton
                    :url="pdf.url({ voucher: voucher.id })"
                    :filename="voucher.number"
                    :text="
                        t(':document :number — :amount', {
                            document: voucher.direction_label,
                            number: voucher.number,
                            amount: `${money(voucher.total)} ${voucher.currency?.code ?? ''}`.trim(),
                        })
                    "
                    :phone="voucher.contact_phone"
                />
                <BaseButton
                    v-if="voucher.can.cancel"
                    variant="outline"
                    @click="confirmingCancel = true"
                >
                    <CloseCircle class="size-5" />
                    {{ t('Cancel voucher') }}
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
                        <dd class="mt-1 flex flex-wrap gap-1">
                            <BaseBadge
                                :color="
                                    voucher.direction === 'in'
                                        ? 'success'
                                        : 'warning'
                                "
                            >
                                {{ voucher.direction_label }}
                            </BaseBadge>
                            <BaseBadge :color="voucher.status_color">
                                {{ voucher.status_label }}
                            </BaseBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Date') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ formatDate(voucher.date, locale) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{
                                voucher.direction === 'out'
                                    ? t('Paid from')
                                    : t('Received into')
                            }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ voucher.bank_account ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{
                                voucher.direction === 'out'
                                    ? t('Paid to')
                                    : t('Received from')
                            }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ voucher.contact ?? voucher.payee ?? '—' }}
                            <span
                                v-if="voucher.contact && voucher.payee"
                                class="block text-theme-xs text-gray-400"
                            >
                                {{ voucher.payee }}
                            </span>
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
                            {{ voucher.reference ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="isForeign">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Exchange rate') }}
                        </dt>
                        <dd
                            class="mt-1 font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ voucher.exchange_rate }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Created by') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ voucher.created_by ?? t('System') }}
                        </dd>
                    </div>
                    <div v-if="voucher.journal_entries?.length">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Journal entries') }}
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <template
                                v-for="entry in voucher.journal_entries"
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
                    <div class="col-span-2 sm:col-span-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Description') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ voucher.description ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard :title="t('Lines')">
                <div class="custom-scrollbar overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Account') }}</th>
                                <th class="th-cell">{{ t('Description') }}</th>
                                <th class="th-cell">{{ t('Cost center') }}</th>
                                <th class="th-cell">{{ t('Tax') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Amount') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Tax amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="line in voucher.lines" :key="line.id">
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ line.account ?? '—' }}
                                </td>
                                <td class="td-cell">
                                    {{ line.description ?? '—' }}
                                </td>
                                <td class="td-cell">
                                    {{ line.cost_center ?? '—' }}
                                </td>
                                <td class="td-cell">{{ line.tax ?? '—' }}</td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(line.amount) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        Number(line.tax_amount)
                                            ? money(line.tax_amount)
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot
                            class="border-t border-gray-100 text-gray-800 dark:border-gray-800 dark:text-white/90"
                        >
                            <tr>
                                <td
                                    class="px-5 pt-3 text-end text-gray-500 dark:text-gray-400"
                                    colspan="5"
                                >
                                    {{ t('Subtotal') }}
                                </td>
                                <td
                                    class="px-5 pt-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(voucher.subtotal) }}
                                </td>
                            </tr>
                            <tr>
                                <td
                                    class="px-5 py-1 text-end text-gray-500 dark:text-gray-400"
                                    colspan="5"
                                >
                                    {{ t('Tax') }}
                                </td>
                                <td
                                    class="px-5 py-1 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(voucher.tax_total) }}
                                </td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-5 pb-3 text-end" colspan="5">
                                    {{ t('Total') }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        ({{ voucher.currency?.code }})
                                    </span>
                                </td>
                                <td
                                    class="px-5 pb-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(voucher.total) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </BaseCard>
        </div>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel voucher')"
            :message="
                t(
                    'The journal entry of this voucher will be reversed. This action cannot be undone.',
                )
            "
            :confirm-label="t('Cancel voucher')"
            :processing="processing"
            @confirm="cancelVoucher"
        />
    </div>
</template>
