<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import CheckCircle from '~icons/solar/check-circle-linear';
import CloseCircle from '~icons/solar/close-circle-linear';
import Pencil from '~icons/solar/pen-linear';
import Logout from '~icons/solar/logout-2-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import {
    cancel,
    dispose,
    index,
    pdf,
    update,
} from '@/routes/admin/assets/assets';
import { show as showRun } from '@/routes/admin/assets/depreciation';
import type { ComboboxOption, Resource } from '@/types';
import type { Asset, BaseCurrency, ScheduleRow } from '@/types/assets';

const props = defineProps<{
    asset: Resource<Asset>;
    schedule: ScheduleRow[];
    costCenters: ComboboxOption[];
    proceedsAccounts: ComboboxOption[];
    baseCurrency: BaseCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const asset = computed(() => props.asset.data);

const amount = (value: string | number | null): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * A schedule month (YYYY-MM) in the user's language, e.g. "Mar 2026".
 */
const monthLabel = (month: string): string =>
    formatDate(`${month}-01`, locale.value, {
        month: 'short',
        year: 'numeric',
    });

/**
 * Today in the user's time zone, as YYYY-MM-DD.
 */
function today(): string {
    return new Date().toLocaleDateString('en-CA');
}

const lifeLabel = computed((): string => {
    const months = asset.value.useful_life_months;
    const years = formatDecimal(
        months / 12,
        locale.value,
        months % 12 === 0 ? 0 : 2,
    );

    return `${t(':months months', { months })} (${t(':years years', { years })})`;
});

const details = computed(() =>
    [
        { label: t('Category'), value: asset.value.category ?? '—' },
        {
            label: t('Serial number'),
            value: asset.value.serial_number,
            ltr: true,
        },
        { label: t('Location'), value: asset.value.location },
        { label: t('Cost center'), value: asset.value.cost_center },
        {
            label: t('Acquisition date'),
            value: formatDate(asset.value.acquisition_date, locale.value),
        },
        { label: t('Depreciation method'), value: asset.value.method_label },
        { label: t('Useful life'), value: lifeLabel.value },
        {
            label: t('Yearly rate'),
            value:
                asset.value.method === 'declining_balance'
                    ? asset.value.declining_rate !== null
                        ? `${formatDecimal(asset.value.declining_rate, locale.value, 2)}%`
                        : t('2 ÷ years (double declining)')
                    : null,
        },
        {
            label: t('Depreciation start'),
            value: formatDate(
                asset.value.depreciation_start_date,
                locale.value,
            ),
        },
        {
            label: t('Salvage value'),
            value: amount(asset.value.salvage_value),
            ltr: true,
        },
        {
            label: t('Depreciation already taken'),
            value:
                Number(asset.value.opening_depreciation) > 0
                    ? amount(asset.value.opening_depreciation)
                    : null,
            ltr: true,
        },
        {
            label: t('Depreciated until'),
            value: asset.value.depreciated_until
                ? formatDate(asset.value.depreciated_until, locale.value)
                : t('Not depreciated yet'),
        },
        {
            label: t('Paid from'),
            value: asset.value.funding_account ?? t('Already in the books'),
        },
        {
            label: t('Disposal date'),
            value: asset.value.disposal_date
                ? formatDate(asset.value.disposal_date, locale.value)
                : null,
        },
        {
            label: t('Sale price'),
            value:
                asset.value.disposal_proceeds !== null
                    ? amount(asset.value.disposal_proceeds)
                    : null,
            ltr: true,
        },
    ].filter((detail) => detail.value),
);

/**
 * The month the next depreciation run posts for this asset.
 */
const nextMonth = computed((): string | null =>
    asset.value.status === 'active'
        ? (props.schedule.find((row) => !row.posted)?.month ?? null)
        : null,
);

const postedTotal = computed(() =>
    (asset.value.depreciations ?? []).reduce(
        (sum, line) => sum + Number(line.amount),
        0,
    ),
);

const isEditOpen = ref(false);
const editForm = useForm({
    name: '',
    serial_number: '',
    location: '',
    cost_center_id: null as number | string | null,
    notes: '',
});

function openEdit(): void {
    editForm.clearErrors();
    editForm.name = asset.value.name;
    editForm.serial_number = asset.value.serial_number ?? '';
    editForm.location = asset.value.location ?? '';
    editForm.cost_center_id = asset.value.cost_center_id;
    editForm.notes = asset.value.notes ?? '';
    isEditOpen.value = true;
}

function submitEdit(): void {
    editForm
        .transform((data) => ({
            ...data,
            serial_number: data.serial_number || null,
            location: data.location || null,
            notes: data.notes || null,
        }))
        .submit(update({ asset: asset.value.id }), {
            preserveScroll: true,
            onSuccess: () => (isEditOpen.value = false),
        });
}

const isDisposeOpen = ref(false);
const disposeForm = useForm({
    date: today(),
    proceeds: '',
    proceeds_account_id: null as number | string | null,
});

function openDispose(): void {
    disposeForm.clearErrors();
    disposeForm.date = today();
    disposeForm.proceeds = '';
    disposeForm.proceeds_account_id = null;
    isDisposeOpen.value = true;
}

const hasProceeds = computed(() => Number(disposeForm.proceeds) > 0);

/**
 * The sale price less the current book value.
 */
const expectedResult = computed(
    () => Number(disposeForm.proceeds || 0) - Number(asset.value.book_value),
);

function submitDispose(): void {
    disposeForm
        .transform((data) => ({
            ...data,
            proceeds: data.proceeds || null,
            proceeds_account_id: hasProceeds.value
                ? data.proceeds_account_id
                : null,
        }))
        .submit(dispose({ asset: asset.value.id }), {
            preserveScroll: true,
            onSuccess: () => (isDisposeOpen.value = false),
        });
}

const confirmingCancel = ref(false);
const cancelling = ref(false);

function cancelAsset(): void {
    router.visit(cancel({ asset: asset.value.id }), {
        preserveScroll: true,
        onStart: () => (cancelling.value = true),
        onFinish: () => {
            cancelling.value = false;
            confirmingCancel.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="`${asset.number} · ${asset.name}`"
            :breadcrumbs="[
                { title: t('Fixed Assets'), href: index() },
                { title: asset.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ asset: asset.id })" />
                <BaseButton
                    v-if="asset.can.update"
                    variant="outline"
                    @click="openEdit"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
                <BaseButton
                    v-if="asset.can.dispose"
                    variant="outline"
                    @click="openDispose"
                >
                    <Logout class="size-5 rtl:rotate-180" />
                    {{ t('Dispose') }}
                </BaseButton>
                <BaseButton
                    v-if="asset.can.cancel"
                    variant="danger"
                    @click="confirmingCancel = true"
                >
                    <CloseCircle class="size-5" />
                    {{ t('Cancel asset') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Cost') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(asset.cost) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Accumulated depreciation') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ amount(asset.accumulated_depreciation) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Book value') }}
                </p>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-brand-600 dark:text-brand-400"
                    dir="ltr"
                >
                    {{ amount(asset.book_value) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t('Status') }}
                </p>
                <div class="mt-2">
                    <BaseBadge :color="asset.status_color" size="md">
                        {{ asset.status_label }}
                    </BaseBadge>
                </div>
                <p
                    v-if="nextMonth"
                    class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ t('Next depreciation') }}: {{ monthLabel(nextMonth) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-1">
                <BaseCard :title="t('Details')">
                    <dl class="space-y-3 text-sm">
                        <div
                            v-for="detail in details"
                            :key="detail.label"
                            class="flex justify-between gap-4"
                        >
                            <dt
                                class="shrink-0 text-gray-500 dark:text-gray-400"
                            >
                                {{ detail.label }}
                            </dt>
                            <dd
                                :class="[
                                    'text-end text-gray-800 dark:text-white/90',
                                    { 'font-mono': detail.ltr },
                                ]"
                                :dir="detail.ltr ? 'ltr' : undefined"
                            >
                                {{ detail.value }}
                            </dd>
                        </div>
                        <div
                            v-if="asset.notes"
                            class="border-t border-gray-100 pt-3 dark:border-gray-800"
                        >
                            <dt class="mb-1 text-gray-500 dark:text-gray-400">
                                {{ t('Notes') }}
                            </dt>
                            <dd
                                class="whitespace-pre-line text-gray-800 dark:text-white/90"
                            >
                                {{ asset.notes }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard
                    v-if="asset.journal_entries?.length"
                    :title="t('Journal entries')"
                >
                    <ul class="space-y-3 text-sm">
                        <li
                            v-for="entry in asset.journal_entries"
                            :key="entry.id"
                        >
                            <Link
                                v-if="can('Journal Entry View List')"
                                :href="showEntry({ journalEntry: entry.id })"
                                class="font-mono text-brand-500 hover:underline"
                                dir="ltr"
                            >
                                {{ entry.number ?? '—' }}
                            </Link>
                            <span
                                v-else
                                class="font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ entry.number ?? '—' }}
                            </span>
                            <span
                                v-if="entry.description"
                                class="block text-theme-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ entry.description }}
                            </span>
                        </li>
                    </ul>
                </BaseCard>
            </div>

            <div class="space-y-6 xl:col-span-2">
                <DataCard>
                    <div class="px-5 py-4 sm:px-6">
                        <h3
                            class="font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ t('Depreciation schedule') }}
                        </h3>
                        <p
                            class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t('Amounts in :currency', {
                                    currency: baseCurrency.code,
                                })
                            }}
                        </p>
                    </div>
                    <div
                        class="custom-scrollbar max-h-[32rem] max-w-full overflow-auto"
                    >
                        <table v-if="schedule.length" class="min-w-full">
                            <thead
                                class="sticky top-0 bg-white dark:bg-gray-900"
                            >
                                <tr
                                    class="border-y border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">{{ t('Month') }}</th>
                                    <th class="th-cell text-end">
                                        {{ t('Depreciation') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Accumulated') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Book value') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr
                                    v-for="row in schedule"
                                    :key="row.month"
                                    :class="{
                                        'text-gray-400 dark:text-gray-500':
                                            row.posted,
                                        'bg-brand-50 dark:bg-brand-500/10':
                                            row.month === nextMonth,
                                    }"
                                >
                                    <td class="td-cell whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center gap-2"
                                        >
                                            <CheckCircle
                                                v-if="row.posted"
                                                class="size-4 text-success-500"
                                                :aria-label="t('Posted')"
                                            />
                                            {{ monthLabel(row.month) }}
                                            <BaseBadge
                                                v-if="row.month === nextMonth"
                                                color="primary"
                                            >
                                                {{ t('Next') }}
                                            </BaseBadge>
                                        </span>
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(row.amount) }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(row.accumulated) }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(row.book_value) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <EmptyState
                            v-else
                            :message="t('Nothing left to depreciate.')"
                        />
                    </div>
                </DataCard>

                <DataCard>
                    <div class="px-5 py-4 sm:px-6">
                        <h3
                            class="font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ t('Posted depreciation') }}
                        </h3>
                    </div>
                    <div class="custom-scrollbar max-w-full overflow-x-auto">
                        <table
                            v-if="asset.depreciations?.length"
                            class="min-w-full"
                        >
                            <thead>
                                <tr
                                    class="border-y border-gray-100 dark:border-gray-800"
                                >
                                    <th class="th-cell">{{ t('Period') }}</th>
                                    <th class="th-cell text-end">
                                        {{ t('Months') }}
                                    </th>
                                    <th class="th-cell text-end">
                                        {{ t('Amount') }}
                                    </th>
                                    <th class="th-cell">{{ t('Run') }}</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr
                                    v-for="line in asset.depreciations"
                                    :key="line.id"
                                >
                                    <td class="td-cell whitespace-nowrap">
                                        {{ formatDate(line.from_date, locale) }}
                                        –
                                        {{ formatDate(line.to_date, locale) }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ line.months }}
                                    </td>
                                    <td
                                        class="td-cell text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(line.amount) }}
                                    </td>
                                    <td class="td-cell">
                                        <Link
                                            v-if="
                                                line.run &&
                                                can(
                                                    'Depreciation Run View List',
                                                )
                                            "
                                            :href="
                                                showRun({ run: line.run.id })
                                            "
                                            class="font-mono text-brand-500 hover:underline"
                                            dir="ltr"
                                        >
                                            {{ line.run.number }}
                                        </Link>
                                        <span
                                            v-else-if="line.run"
                                            class="font-mono"
                                            dir="ltr"
                                        >
                                            {{ line.run.number }}
                                        </span>
                                        <span v-else>
                                            {{ t('On disposal') }}
                                        </span>
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
                                        class="px-5 py-3 text-end font-mono"
                                        dir="ltr"
                                    >
                                        {{ amount(postedTotal) }}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                        <EmptyState
                            v-else
                            :message="t('No depreciation posted yet.')"
                        />
                    </div>
                </DataCard>
            </div>
        </div>

        <BaseModal
            v-model:open="isEditOpen"
            :title="t('Edit asset')"
            max-width="lg"
        >
            <form
                id="asset-edit-form"
                class="grid grid-cols-1 gap-5 sm:grid-cols-2"
                @submit.prevent="submitEdit"
            >
                <BaseField
                    class="sm:col-span-2"
                    :label="t('Name')"
                    for="edit_name"
                    required
                    :error="editForm.errors.name"
                >
                    <BaseInput
                        id="edit_name"
                        v-model="editForm.name"
                        :invalid="Boolean(editForm.errors.name)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Serial number')"
                    for="edit_serial_number"
                    :error="editForm.errors.serial_number"
                >
                    <BaseInput
                        id="edit_serial_number"
                        v-model="editForm.serial_number"
                        dir="ltr"
                        class="font-mono"
                        :invalid="Boolean(editForm.errors.serial_number)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Location')"
                    for="edit_location"
                    :error="editForm.errors.location"
                >
                    <BaseInput
                        id="edit_location"
                        v-model="editForm.location"
                        :invalid="Boolean(editForm.errors.location)"
                    />
                </BaseField>
                <BaseField
                    class="sm:col-span-2"
                    :label="t('Cost center')"
                    :error="editForm.errors.cost_center_id"
                >
                    <BaseCombobox
                        v-model="editForm.cost_center_id"
                        :options="costCenters"
                        clearable
                        :placeholder="t('None')"
                        :invalid="Boolean(editForm.errors.cost_center_id)"
                    />
                </BaseField>
                <BaseField
                    class="sm:col-span-2"
                    :label="t('Notes')"
                    for="edit_notes"
                    :error="editForm.errors.notes"
                >
                    <BaseTextarea
                        id="edit_notes"
                        v-model="editForm.notes"
                        :invalid="Boolean(editForm.errors.notes)"
                    />
                </BaseField>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isEditOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="asset-edit-form"
                    size="sm"
                    :loading="editForm.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <BaseModal
            v-model:open="isDisposeOpen"
            :title="t('Dispose of asset')"
            max-width="md"
        >
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                {{
                    t(
                        'Depreciation is first brought up to the month of disposal, then the asset leaves the books.',
                    )
                }}
            </p>
            <form
                id="asset-dispose-form"
                class="space-y-5"
                @submit.prevent="submitDispose"
            >
                <BaseField
                    :label="t('Disposal date')"
                    for="disposal_date"
                    required
                    :error="disposeForm.errors.date"
                >
                    <BaseInput
                        id="disposal_date"
                        v-model="disposeForm.date"
                        type="date"
                        :min="asset.acquisition_date"
                        :invalid="Boolean(disposeForm.errors.date)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Sale price')"
                    for="proceeds"
                    :hint="t('Leave empty if the asset is scrapped.')"
                    :error="disposeForm.errors.proceeds"
                >
                    <BaseInput
                        id="proceeds"
                        v-model="disposeForm.proceeds"
                        type="number"
                        step="any"
                        min="0"
                        dir="ltr"
                        class="font-mono"
                        placeholder="0"
                        :invalid="Boolean(disposeForm.errors.proceeds)"
                    />
                </BaseField>
                <BaseField
                    v-if="hasProceeds"
                    :label="t('Proceeds account')"
                    required
                    :hint="
                        t(
                            'Where the sale price goes: a bank account, or the buyer\'s receivable.',
                        )
                    "
                    :error="disposeForm.errors.proceeds_account_id"
                >
                    <BaseCombobox
                        v-model="disposeForm.proceeds_account_id"
                        :options="proceedsAccounts"
                        :placeholder="t('Choose an account')"
                        :invalid="
                            Boolean(disposeForm.errors.proceeds_account_id)
                        "
                    />
                </BaseField>

                <dl
                    class="space-y-2 rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-white/[0.03]"
                >
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Current book value') }}
                        </dt>
                        <dd
                            class="font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ amount(asset.book_value) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 font-medium">
                        <dt class="text-gray-700 dark:text-gray-300">
                            {{
                                expectedResult >= 0
                                    ? t('Expected gain')
                                    : t('Expected loss')
                            }}
                        </dt>
                        <dd
                            :class="[
                                'font-mono',
                                expectedResult >= 0
                                    ? 'text-success-600 dark:text-success-500'
                                    : 'text-error-600 dark:text-error-500',
                            ]"
                            dir="ltr"
                        >
                            {{ amount(Math.abs(expectedResult)) }}
                            {{ baseCurrency.code }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="asset.status === 'active'"
                    class="text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{
                        t(
                            'The final gain or loss can differ: the depreciation up to the disposal month lowers the book value first.',
                        )
                    }}
                </p>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isDisposeOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="asset-dispose-form"
                    size="sm"
                    variant="danger"
                    :loading="disposeForm.processing"
                >
                    {{ t('Dispose') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            v-model:open="confirmingCancel"
            :title="t('Cancel asset')"
            :message="
                t(
                    'The asset will be cancelled and its acquisition entry, if any, reversed. This action cannot be undone.',
                )
            "
            :confirm-label="t('Cancel asset')"
            :processing="cancelling"
            @confirm="cancelAsset"
        />
    </div>
</template>
