<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Refresh from '~icons/solar/refresh-linear';
import Send from '~icons/solar/check-circle-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import {
    destroy,
    edit,
    index,
    pdf,
    post,
    reverse,
    show,
} from '@/routes/admin/accounting/journal-entries';
import type { JournalEntry, Resource } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

const props = defineProps<{
    entry: Resource<JournalEntry>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const entry = computed(() => props.entry.data);
const decimals = computed(() => entry.value.currency?.decimal_places ?? 2);
const isForeign = computed(() => Number(entry.value.exchange_rate) !== 1);

const confirmingPost = ref(false);
const confirmingDelete = ref(false);
const reversing = ref(false);
const reversalDate = ref(new Date().toISOString().slice(0, 10));
const processing = ref(false);

function visit(
    route: RouteDefinition<'post'> | RouteDefinition<'delete'>,
    data: Record<string, string> = {},
): void {
    router.visit(route, {
        data,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            confirmingPost.value = false;
            confirmingDelete.value = false;
            reversing.value = false;
        },
    });
}

const totals = computed(() => ({
    debit: (entry.value.lines ?? []).reduce(
        (sum, line) => sum + Number(line.debit),
        0,
    ),
    credit: (entry.value.lines ?? []).reduce(
        (sum, line) => sum + Number(line.credit),
        0,
    ),
    baseDebit: (entry.value.lines ?? []).reduce(
        (sum, line) => sum + Number(line.base_debit),
        0,
    ),
    baseCredit: (entry.value.lines ?? []).reduce(
        (sum, line) => sum + Number(line.base_credit),
        0,
    ),
}));
</script>

<template>
    <div>
        <PageHeader
            :title="entry.number ?? t('Draft journal entry')"
            :breadcrumbs="[
                { title: t('Journal Entries'), href: index() },
                { title: entry.number ?? t('Draft') },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ journalEntry: entry.id })" />
                <BaseButton
                    v-if="entry.can.update"
                    variant="outline"
                    :href="edit({ journalEntry: entry.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
                <BaseButton
                    v-if="entry.can.delete"
                    variant="outline"
                    @click="confirmingDelete = true"
                >
                    <Trash2 class="size-5" />
                    {{ t('Delete') }}
                </BaseButton>
                <BaseButton
                    v-if="entry.can.reverse"
                    variant="outline"
                    @click="reversing = true"
                >
                    <Refresh class="size-5" />
                    {{ t('Reverse') }}
                </BaseButton>
                <BaseButton
                    v-if="entry.can.post"
                    @click="confirmingPost = true"
                >
                    <Send class="size-5" />
                    {{ t('Post') }}
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
                            <BaseBadge :color="entry.status_color">{{
                                entry.status_label
                            }}</BaseBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Date') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ formatDate(entry.date, locale) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Currency') }}
                        </dt>
                        <dd
                            class="mt-1 text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ entry.currency?.code
                            }}<span v-if="isForeign">
                                × {{ entry.exchange_rate }}</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Reference') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ entry.reference ?? '—' }}
                        </dd>
                    </div>
                    <div class="col-span-2 sm:col-span-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Description') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ entry.description ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="entry.source">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Source document') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            <Link
                                v-if="entry.source.document?.url"
                                :href="entry.source.document.url"
                                class="text-brand-500 hover:underline"
                            >
                                {{ entry.source.document.label }}
                                {{ entry.source.label }}
                            </Link>
                            <template v-else
                                >{{
                                    entry.source.document?.label ??
                                    t(entry.source.type)
                                }}
                                {{ entry.source.label }}</template
                            >
                        </dd>
                    </div>
                    <div v-if="entry.reversal_of">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Reverses') }}
                        </dt>
                        <dd class="mt-1">
                            <Link
                                :href="
                                    show({ journalEntry: entry.reversal_of.id })
                                "
                                class="text-brand-500 hover:underline"
                                >{{ entry.reversal_of.number }}</Link
                            >
                        </dd>
                    </div>
                    <div v-if="entry.reversed_by">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Reversed by') }}
                        </dt>
                        <dd class="mt-1">
                            <Link
                                :href="
                                    show({ journalEntry: entry.reversed_by.id })
                                "
                                class="text-brand-500 hover:underline"
                                >{{ entry.reversed_by.number }}</Link
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Created by') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ entry.created_by ?? t('System') }}
                        </dd>
                    </div>
                    <div v-if="entry.posted_by">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Posted by') }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ entry.posted_by }}
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
                                <th class="th-cell text-end">
                                    {{ t('Debit') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Credit') }}
                                </th>
                                <template v-if="isForeign">
                                    <th class="th-cell text-end">
                                        {{ t('Base debit') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Base credit') }}
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="line in entry.lines" :key="line.id">
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ line.account }}
                                </td>
                                <td class="td-cell">
                                    {{ line.description ?? '—' }}
                                </td>
                                <td class="td-cell">
                                    {{ line.cost_center ?? '—' }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        Number(line.debit)
                                            ? formatDecimal(
                                                  line.debit,
                                                  locale,
                                                  decimals,
                                              )
                                            : ''
                                    }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        Number(line.credit)
                                            ? formatDecimal(
                                                  line.credit,
                                                  locale,
                                                  decimals,
                                              )
                                            : ''
                                    }}
                                </td>
                                <template v-if="isForeign">
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{
                                            Number(line.base_debit)
                                                ? formatDecimal(
                                                      line.base_debit ?? null,
                                                      locale,
                                                  )
                                                : ''
                                        }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{
                                            Number(line.base_credit)
                                                ? formatDecimal(
                                                      line.base_credit ?? null,
                                                      locale,
                                                  )
                                                : ''
                                        }}
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="3">
                                    {{ t('Total') }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        formatDecimal(
                                            totals.debit,
                                            locale,
                                            decimals,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        formatDecimal(
                                            totals.credit,
                                            locale,
                                            decimals,
                                        )
                                    }}
                                </td>
                                <template v-if="isForeign">
                                    <td
                                        class="px-5 py-3 text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{
                                            formatDecimal(
                                                totals.baseDebit,
                                                locale,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-5 py-3 text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{
                                            formatDecimal(
                                                totals.baseCredit,
                                                locale,
                                            )
                                        }}
                                    </td>
                                </template>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </BaseCard>
        </div>

        <ConfirmDialog
            v-model:open="confirmingPost"
            :title="t('Post journal entry')"
            :message="
                t(
                    'Once posted, the entry is part of the books and can only be undone by a reversing entry.',
                )
            "
            :confirm-label="t('Post')"
            :processing="processing"
            @confirm="visit(post({ journalEntry: entry.id }))"
        />

        <ConfirmDialog
            v-model:open="confirmingDelete"
            :title="t('Delete journal entry')"
            :message="
                t(
                    'Are you sure you want to delete this draft? This action cannot be undone.',
                )
            "
            :processing="processing"
            @confirm="visit(destroy({ journalEntry: entry.id }))"
        />

        <BaseModal
            v-model:open="reversing"
            :title="t('Reverse journal entry')"
            max-width="sm"
        >
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{
                    t(
                        'A new entry with the opposite amounts will be posted on this date.',
                    )
                }}
            </p>
            <BaseField :label="t('Reversal date')" for="reversal-date" required>
                <BaseInput
                    id="reversal-date"
                    v-model="reversalDate"
                    type="date"
                />
            </BaseField>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="reversing = false"
                    >{{ t('Cancel') }}</BaseButton
                >
                <BaseButton
                    size="sm"
                    :loading="processing"
                    @click="
                        visit(reverse({ journalEntry: entry.id }), {
                            date: reversalDate,
                        })
                    "
                >
                    {{ t('Reverse') }}
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>
