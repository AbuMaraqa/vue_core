<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import UndoLeft from '~icons/solar/undo-left-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useContactSearch } from '@/composables/useContactSearch';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import { index, pdf, perform, undo } from '@/routes/admin/banking/cheques';
import type {
    BankAccountOption,
    Cheque,
    ChequeActionOption,
    Resource,
} from '@/types';

const props = defineProps<{
    cheque: Resource<Cheque>;
    bankAccounts: BankAccountOption[];
    canUndo: boolean;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const cheque = computed(() => props.cheque.data);

const searchVendors = useContactSearch('vendors');

const isForeign = computed(() => Number(cheque.value.exchange_rate) !== 1);

const amount = computed(() =>
    formatDecimal(
        cheque.value.amount,
        locale.value,
        cheque.value.currency?.decimal_places ?? 2,
    ),
);

/**
 * Today in the user's time zone, as YYYY-MM-DD.
 */
function today(): string {
    return new Date().toLocaleDateString('en-CA');
}

const details = computed(() =>
    [
        {
            label: t('Contact'),
            value: cheque.value.contact,
        },
        {
            label: t('Cheque date'),
            value: formatDate(cheque.value.issue_date, locale.value),
        },
        {
            label: t('Drawee bank'),
            value: cheque.value.drawee_bank,
        },
        {
            label: t('Bank account'),
            value: cheque.value.bank_account,
        },
        {
            label: t('Exchange rate'),
            value: isForeign.value ? cheque.value.exchange_rate : null,
            ltr: true,
        },
        {
            label: t('Counter account'),
            value: cheque.value.counter_account,
        },
        {
            label: t('Endorsed to'),
            value: cheque.value.endorsed_to,
        },
    ].filter((detail) => detail.value),
);

const chosenAction = ref<ChequeActionOption | null>(null);
const isActionOpen = ref(false);

const actionForm = useForm({
    action: '',
    date: today(),
    bank_account_id: null as number | string | null,
    endorse_to_contact_id: null as number | string | null,
    note: '',
});

function openAction(action: ChequeActionOption): void {
    chosenAction.value = action;
    actionForm.clearErrors();
    actionForm.action = action.value;
    actionForm.date = today();
    actionForm.bank_account_id =
        props.bankAccounts.length === 1 ? props.bankAccounts[0].value : null;
    actionForm.endorse_to_contact_id = null;
    actionForm.note = '';
    isActionOpen.value = true;
}

function submitAction(): void {
    const action = chosenAction.value;

    actionForm
        .transform((data) => ({
            ...data,
            bank_account_id: action?.needs_bank_account
                ? data.bank_account_id
                : null,
            endorse_to_contact_id: action?.needs_contact
                ? data.endorse_to_contact_id
                : null,
            note: data.note || null,
        }))
        .submit(perform({ cheque: cheque.value.id }), {
            preserveScroll: true,
            onSuccess: () => (isActionOpen.value = false),
        });
}

const confirmingUndo = ref(false);
const undoing = ref(false);

function undoLastStep(): void {
    router.visit(undo({ cheque: cheque.value.id }), {
        preserveScroll: true,
        onStart: () => (undoing.value = true),
        onFinish: () => {
            undoing.value = false;
            confirmingUndo.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Cheque :number', { number: cheque.number })"
            :breadcrumbs="[
                { title: t('Cheques'), href: index() },
                { title: cheque.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ cheque: cheque.id })" />
                <BaseButton
                    v-if="canUndo"
                    variant="outline"
                    @click="confirmingUndo = true"
                >
                    <UndoLeft class="size-5" />
                    {{ t('Undo last step') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <BaseCard>
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <BaseBadge
                                    :color="
                                        cheque.direction === 'incoming'
                                            ? 'success'
                                            : 'warning'
                                    "
                                >
                                    {{ cheque.direction_label }}
                                </BaseBadge>
                                <BaseBadge :color="cheque.status_color">
                                    {{ cheque.status_label }}
                                </BaseBadge>
                                <BaseBadge
                                    v-if="cheque.is_overdue"
                                    color="error"
                                >
                                    {{ t('Overdue') }}
                                </BaseBadge>
                            </div>
                            <p
                                class="mt-3 font-mono text-sm text-gray-500 dark:text-gray-400"
                                dir="ltr"
                            >
                                № {{ cheque.number }}
                            </p>
                        </div>
                        <div class="text-end">
                            <p
                                class="font-mono text-2xl font-semibold text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ amount }}
                                <span class="text-sm font-normal text-gray-400">
                                    {{ cheque.currency?.code }}
                                </span>
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-sm',
                                    cheque.is_overdue
                                        ? 'font-medium text-error-600 dark:text-error-500'
                                        : 'text-gray-500 dark:text-gray-400',
                                ]"
                            >
                                {{ t('Due date') }}:
                                {{ formatDate(cheque.due_date, locale) }}
                            </p>
                        </div>
                    </div>

                    <dl
                        class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-2 dark:border-gray-800"
                    >
                        <div v-for="detail in details" :key="detail.label">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ detail.label }}
                            </dt>
                            <dd
                                :class="[
                                    'mt-1 text-gray-800 dark:text-white/90',
                                    { 'font-mono': detail.ltr },
                                ]"
                                :dir="detail.ltr ? 'ltr' : undefined"
                            >
                                {{ detail.value }}
                            </dd>
                        </div>
                        <div v-if="cheque.description" class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Description') }}
                            </dt>
                            <dd class="mt-1 text-gray-800 dark:text-white/90">
                                {{ cheque.description }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard :title="t('History')">
                    <ol
                        v-if="cheque.events?.length"
                        class="relative space-y-6 border-s border-gray-200 ps-6 dark:border-gray-800"
                    >
                        <li
                            v-for="event in cheque.events"
                            :key="event.id"
                            :class="[
                                'relative',
                                { 'opacity-60': event.is_undone },
                            ]"
                        >
                            <span
                                :class="[
                                    'absolute -start-[1.85rem] top-1 size-3 rounded-full ring-4 ring-white dark:ring-gray-900',
                                    event.is_undone
                                        ? 'bg-gray-300 dark:bg-gray-700'
                                        : 'bg-brand-500',
                                ]"
                            />
                            <div class="flex flex-wrap items-center gap-2">
                                <p
                                    :class="[
                                        'font-medium text-gray-800 dark:text-white/90',
                                        { 'line-through': event.is_undone },
                                    ]"
                                >
                                    {{ event.action }}
                                </p>
                                <BaseBadge v-if="event.is_undone" color="light">
                                    {{ t('Undone') }}
                                </BaseBadge>
                            </div>
                            <p
                                class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                <template v-if="event.from_status">
                                    {{ event.from_status }}
                                    <span class="rtl:hidden">→</span>
                                    <span class="ltr:hidden">←</span>
                                </template>
                                {{ event.to_status }}
                            </p>
                            <p
                                class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-theme-xs text-gray-400"
                            >
                                <span>{{
                                    formatDate(event.date, locale)
                                }}</span>
                                <span v-if="event.created_by">
                                    {{ event.created_by }}
                                </span>
                                <template v-if="event.journal_entry">
                                    <Link
                                        v-if="can('Journal Entry View List')"
                                        :href="
                                            showEntry({
                                                journalEntry:
                                                    event.journal_entry.id,
                                            })
                                        "
                                        class="text-brand-500 hover:underline"
                                        dir="ltr"
                                    >
                                        {{ event.journal_entry.number }}
                                    </Link>
                                    <span v-else dir="ltr">
                                        {{ event.journal_entry.number }}
                                    </span>
                                </template>
                            </p>
                            <p
                                v-if="event.note"
                                class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/[0.03] dark:text-gray-300"
                            >
                                {{ event.note }}
                            </p>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No history yet.') }}
                    </p>
                </BaseCard>
            </div>

            <BaseCard :title="t('Actions')">
                <div v-if="cheque.actions.length" class="flex flex-col gap-3">
                    <BaseButton
                        v-for="(action, position) in cheque.actions"
                        :key="action.value"
                        :variant="
                            action.value === 'bounce'
                                ? 'danger'
                                : position === 0
                                  ? 'primary'
                                  : 'outline'
                        "
                        class="w-full"
                        @click="openAction(action)"
                    >
                        {{ action.label }}
                    </BaseButton>
                </div>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                    {{
                        cheque.can.manage
                            ? t('No more steps for this cheque.')
                            : t('You cannot manage this cheque.')
                    }}
                </p>
            </BaseCard>
        </div>

        <BaseModal
            v-model:open="isActionOpen"
            :title="chosenAction?.label"
            max-width="md"
        >
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                {{
                    t('Cheque :number of :amount :currency', {
                        number: cheque.number,
                        amount,
                        currency: cheque.currency?.code ?? '',
                    })
                }}
            </p>
            <form
                id="cheque-action-form"
                class="space-y-5"
                @submit.prevent="submitAction"
            >
                <BaseField
                    :label="t('Date')"
                    for="action_date"
                    required
                    :error="actionForm.errors.date"
                >
                    <BaseInput
                        id="action_date"
                        v-model="actionForm.date"
                        type="date"
                        :invalid="Boolean(actionForm.errors.date)"
                    />
                </BaseField>
                <BaseField
                    v-if="chosenAction?.needs_bank_account"
                    :label="t('Bank account')"
                    required
                    :hint="
                        bankAccounts.length
                            ? undefined
                            : t(
                                  'There is no active bank account in this currency.',
                              )
                    "
                    :error="actionForm.errors.bank_account_id"
                >
                    <BaseCombobox
                        v-model="actionForm.bank_account_id"
                        :options="bankAccounts"
                        :placeholder="t('Choose an account')"
                        :invalid="Boolean(actionForm.errors.bank_account_id)"
                    />
                </BaseField>
                <BaseField
                    v-if="chosenAction?.needs_contact"
                    :label="t('Endorse to')"
                    required
                    :error="actionForm.errors.endorse_to_contact_id"
                >
                    <BaseCombobox
                        v-model="actionForm.endorse_to_contact_id"
                        :search="searchVendors"
                        :placeholder="t('Choose a vendor')"
                        :invalid="
                            Boolean(actionForm.errors.endorse_to_contact_id)
                        "
                    />
                </BaseField>
                <BaseField
                    :label="t('Note')"
                    for="action_note"
                    :error="actionForm.errors.note"
                >
                    <BaseInput id="action_note" v-model="actionForm.note" />
                </BaseField>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isActionOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="cheque-action-form"
                    size="sm"
                    :variant="
                        chosenAction?.value === 'bounce' ? 'danger' : 'primary'
                    "
                    :loading="actionForm.processing"
                >
                    {{ chosenAction?.label ?? t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            v-model:open="confirmingUndo"
            :title="t('Undo last step')"
            :message="
                t(
                    'The last step of this cheque and its journal entry will be reversed.',
                )
            "
            :confirm-label="t('Undo')"
            :processing="undoing"
            @confirm="undoLastStep"
        />
    </div>
</template>
