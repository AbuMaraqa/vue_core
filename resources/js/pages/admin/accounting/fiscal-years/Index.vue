<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import LockKeyhole from '~icons/solar/lock-keyhole-linear';
import LockUnlocked from '~icons/solar/lock-keyhole-unlocked-linear';
import Plus from '~icons/solar/add-linear';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import { close, reopen, store } from '@/routes/admin/accounting/fiscal-years';
import { show } from '@/routes/admin/accounting/journal-entries';

type FiscalYearRow = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: 'open' | 'closed';
    status_label: string;
    closed_at: string | null;
    closing_entry: { id: number; number: string | null } | null;
};

defineProps<{
    years: FiscalYearRow[];
    can: { create: boolean; close: boolean };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const isFormOpen = ref(false);
const nextYear = new Date().getFullYear() + 1;
const form = useForm({
    name: String(nextYear),
    start_date: `${nextYear}-01-01`,
    end_date: `${nextYear}-12-31`,
});

function submit(): void {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => (isFormOpen.value = false),
    });
}

const action = ref<{ year: FiscalYearRow; type: 'close' | 'reopen' } | null>(
    null,
);
const processing = ref(false);

function confirmAction(): void {
    if (!action.value) {
        return;
    }

    const route =
        action.value.type === 'close'
            ? close({ fiscalYear: action.value.year.id })
            : reopen({ fiscalYear: action.value.year.id });

    router.visit(route, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            action.value = null;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Fiscal Years')"
            :description="
                t(
                    'Closing a year moves its profit or loss into retained earnings and locks it.',
                )
            "
            :breadcrumbs="[{ title: t('Fiscal Years') }]"
        >
            <template #actions>
                <BaseButton v-if="can.create" @click="isFormOpen = true">
                    <Plus class="size-5" />
                    {{ t('Add fiscal year') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="years.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('From') }}</th>
                            <th class="th-cell">{{ t('To') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                            <th class="th-cell">{{ t('Closing entry') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="year in years" :key="year.id">
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ year.name }}
                            </td>
                            <td class="td-cell">
                                {{ formatDate(year.start_date, locale) }}
                            </td>
                            <td class="td-cell">
                                {{ formatDate(year.end_date, locale) }}
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        year.status === 'open'
                                            ? 'success'
                                            : 'light'
                                    "
                                    >{{ year.status_label }}</BaseBadge
                                >
                            </td>
                            <td class="td-cell">
                                <Link
                                    v-if="year.closing_entry"
                                    :href="
                                        show({
                                            journalEntry: year.closing_entry.id,
                                        })
                                    "
                                    class="text-brand-500 hover:underline"
                                    dir="ltr"
                                >
                                    {{ year.closing_entry.number }}
                                </Link>
                                <span v-else>—</span>
                            </td>
                            <td class="td-cell">
                                <div v-if="can.close" class="flex justify-end">
                                    <BaseButton
                                        v-if="year.status === 'open'"
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            action = { year, type: 'close' }
                                        "
                                    >
                                        <LockKeyhole class="size-4" />
                                        {{ t('Close year') }}
                                    </BaseButton>
                                    <BaseButton
                                        v-else
                                        variant="ghost"
                                        size="sm"
                                        @click="
                                            action = { year, type: 'reopen' }
                                        "
                                    >
                                        <LockUnlocked class="size-4" />
                                        {{ t('Reopen') }}
                                    </BaseButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No fiscal years yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="t('Add fiscal year')"
            max-width="md"
        >
            <form id="year-form" class="space-y-5" @submit.prevent="submit">
                <BaseField
                    :label="t('Name')"
                    for="name"
                    required
                    :error="form.errors.name"
                >
                    <BaseInput id="name" v-model="form.name" />
                </BaseField>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        :label="t('From')"
                        for="start_date"
                        required
                        :error="form.errors.start_date"
                    >
                        <BaseInput
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('To')"
                        for="end_date"
                        required
                        :error="form.errors.end_date"
                    >
                        <BaseInput
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                        />
                    </BaseField>
                </div>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isFormOpen = false"
                    >{{ t('Cancel') }}</BaseButton
                >
                <BaseButton
                    type="submit"
                    form="year-form"
                    size="sm"
                    :loading="form.processing"
                    >{{ t('Save') }}</BaseButton
                >
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="action !== null"
            :title="
                action?.type === 'close'
                    ? t('Close fiscal year')
                    : t('Reopen fiscal year')
            "
            :message="
                action?.type === 'close'
                    ? t(
                          'A closing entry will move the revenue and expenses of :name into retained earnings, and nothing more can be posted in it.',
                          { name: action?.year.name ?? '' },
                      )
                    : t(
                          'The closing entry of :name will be reversed so corrections can be posted.',
                          { name: action?.year.name ?? '' },
                      )
            "
            :confirm-label="
                action?.type === 'close' ? t('Close year') : t('Reopen')
            "
            :processing="processing"
            @update:open="(open) => !open && (action = null)"
            @confirm="confirmAction"
        />
    </div>
</template>
