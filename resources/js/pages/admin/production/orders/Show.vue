<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import CheckCircle from '~icons/solar/check-circle-linear';
import Pencil from '~icons/solar/pen-linear';
import Play from '~icons/solar/play-circle-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import UndoLeft from '~icons/solar/undo-left-linear';
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
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { formatQuantity } from '@/lib/production';
import { show as showEntry } from '@/routes/admin/accounting/journal-entries';
import {
    completion,
    destroy,
    edit,
    index,
    pdf,
    reverse,
    start,
} from '@/routes/admin/production/orders';
import type {
    ProductionCurrency,
    ProductionOrder,
    ProductionOrderStatus,
    Resource,
} from '@/types';

const props = defineProps<{
    order: Resource<ProductionOrder>;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const order = computed(() => props.order.data);

const amount = (value: string | number): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

/**
 * The stages of an order, to show where it stands.
 */
const steps = computed(() => {
    const reached: Record<ProductionOrderStatus, number> = {
        draft: 1,
        in_progress: 2,
        completed: 3,
        cancelled: 0,
    };

    return [
        { label: t('Draft'), date: order.value.date },
        { label: t('In progress'), date: order.value.started_on },
        { label: t('Completed'), date: order.value.completed_on },
    ].map((step, position) => ({
        ...step,
        done: position < reached[order.value.status],
    }));
});

const isCompleted = computed(() => order.value.status === 'completed');
const isStarted = computed(() =>
    ['in_progress', 'completed'].includes(order.value.status),
);

const details = computed(() =>
    [
        { label: t('Product'), value: order.value.item },
        {
            label: isCompleted.value
                ? t('Produced quantity')
                : t('Quantity to produce'),
            value: `${formatQuantity(order.value.produced_quantity ?? order.value.quantity, locale.value)} ${order.value.unit ?? ''}`,
        },
        {
            label: t('Planned quantity'),
            value: isCompleted.value
                ? `${formatQuantity(order.value.quantity, locale.value)} ${order.value.unit ?? ''}`
                : null,
        },
        { label: t('Bill of materials'), value: order.value.bill_of_materials },
        {
            label: t('Materials warehouse'),
            value: order.value.materials_warehouse,
        },
        {
            label: t('Finished goods warehouse'),
            value: order.value.product_warehouse,
        },
        { label: t('Customer'), value: order.value.contact?.label },
        { label: t('Reference'), value: order.value.reference },
        { label: t('Cost center'), value: order.value.cost_center },
        { label: t('Description'), value: order.value.description },
        { label: t('Created by'), value: order.value.created_by },
    ].filter((detail) => detail.value),
);

const startForm = useForm({ date: new Date().toLocaleDateString('en-CA') });
const starting = ref(false);

function startOrder(): void {
    startForm.submit(start({ order: order.value.id }), {
        preserveScroll: true,
        onSuccess: () => (starting.value = false),
    });
}

const confirming = ref<'reverse' | 'delete' | null>(null);
const processing = ref(false);

function confirmAction(): void {
    const definition =
        confirming.value === 'delete'
            ? destroy({ order: order.value.id })
            : reverse({ order: order.value.id });

    router.visit(definition, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            confirming.value = null;
        },
    });
}

const totalPlannedCosts = computed(() =>
    (order.value.costs ?? []).reduce(
        (total, cost) => total + Number(cost.amount),
        0,
    ),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Production order :number', { number: order.number })"
            :breadcrumbs="[
                { title: t('Production Orders'), href: index() },
                { title: order.number },
            ]"
        >
            <template #actions>
                <PrintButton :url="pdf.url({ order: order.id })" />
                <BaseButton
                    v-if="order.can.update"
                    variant="outline"
                    :href="edit({ order: order.id })"
                >
                    <Pencil class="size-5" />
                    {{ t('Edit') }}
                </BaseButton>
                <BaseButton
                    v-if="order.can.delete"
                    variant="outline"
                    @click="confirming = 'delete'"
                >
                    <Trash2 class="size-5" />
                    {{ t('Delete') }}
                </BaseButton>
                <BaseButton
                    v-if="order.can.reverse"
                    variant="outline"
                    @click="confirming = 'reverse'"
                >
                    <UndoLeft class="size-5" />
                    {{ isCompleted ? t('Reopen order') : t('Return to draft') }}
                </BaseButton>
                <BaseButton v-if="order.can.start" @click="starting = true">
                    <Play class="size-5" />
                    {{ t('Start production') }}
                </BaseButton>
                <BaseButton
                    v-if="order.can.complete"
                    :href="completion({ order: order.id })"
                >
                    <CheckCircle class="size-5" />
                    {{ t('Complete production') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <BaseCard>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <ol class="flex flex-1 flex-wrap items-center gap-2">
                        <li
                            v-for="(step, position) in steps"
                            :key="step.label"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-theme-xs font-semibold"
                                :class="
                                    step.done
                                        ? 'bg-brand-500 text-white'
                                        : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                "
                            >
                                {{ position + 1 }}
                            </span>
                            <span>
                                <span
                                    class="block text-theme-sm font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ step.label }}
                                </span>
                                <span
                                    v-if="step.done && step.date"
                                    class="block text-theme-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatDate(step.date, locale) }}
                                </span>
                            </span>
                            <span
                                v-if="position < steps.length - 1"
                                class="mx-2 hidden h-px w-10 bg-gray-200 sm:block dark:bg-gray-700"
                            />
                        </li>
                    </ol>
                    <BaseBadge :color="order.status_color" size="md">
                        {{ order.status_label }}
                    </BaseBadge>
                </div>
            </BaseCard>

            <div
                v-if="isCompleted"
                class="grid grid-cols-2 gap-4 lg:grid-cols-4"
            >
                <div
                    v-for="card in [
                        {
                            label: t('Materials cost'),
                            value: order.materials_cost,
                        },
                        { label: t('Other costs'), value: order.other_costs },
                        { label: t('Total cost'), value: order.total_cost },
                        {
                            label: t('Unit cost'),
                            value: order.unit_cost,
                            strong: true,
                        },
                    ]"
                    :key="card.label"
                    class="rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <span
                        class="text-theme-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ card.label }}
                    </span>
                    <span
                        class="mt-1 block font-mono text-title-sm font-bold"
                        :class="
                            card.strong
                                ? 'text-brand-500'
                                : 'text-gray-800 dark:text-white/90'
                        "
                        dir="ltr"
                    >
                        {{ amount(card.value) }}
                    </span>
                    <span class="text-theme-xs text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </div>
            </div>

            <BaseCard :title="t('Order details')">
                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div v-for="detail in details" :key="detail.label">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ detail.label }}
                        </dt>
                        <dd class="mt-1 text-gray-800 dark:text-white/90">
                            {{ detail.value }}
                        </dd>
                    </div>
                    <div v-if="order.journal_entries?.length">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Journal entries') }}
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <template
                                v-for="entry in order.journal_entries"
                                :key="entry.id"
                            >
                                <Link
                                    v-if="can('Journal Entry View List')"
                                    :href="
                                        showEntry({ journalEntry: entry.id })
                                    "
                                    class="text-brand-500 hover:underline"
                                    :title="entry.description ?? ''"
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

            <BaseCard :title="t('Materials')">
                <div class="custom-scrollbar overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Material') }}</th>
                                <th class="th-cell text-end">
                                    {{
                                        isStarted
                                            ? t('Issued quantity')
                                            : t('Quantity')
                                    }}
                                </th>
                                <th v-if="isCompleted" class="th-cell text-end">
                                    {{ t('Quantity used') }}
                                </th>
                                <th v-if="isStarted" class="th-cell text-end">
                                    {{ t('Unit cost') }}
                                </th>
                                <th v-if="isStarted" class="th-cell text-end">
                                    {{ t('Value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="material in order.materials"
                                :key="material.id"
                            >
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ material.item?.label ?? '—' }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        formatQuantity(
                                            material.quantity,
                                            locale,
                                        )
                                    }}
                                    <span class="text-theme-xs text-gray-400">
                                        {{ material.item?.unit ?? '' }}
                                    </span>
                                </td>
                                <td
                                    v-if="isCompleted"
                                    class="td-cell text-end font-mono"
                                    :class="
                                        Number(material.consumed_quantity) >
                                        Number(material.quantity)
                                            ? 'text-error-600 dark:text-error-500'
                                            : Number(
                                                    material.consumed_quantity,
                                                ) < Number(material.quantity)
                                              ? 'text-success-600 dark:text-success-500'
                                              : ''
                                    "
                                    dir="ltr"
                                >
                                    {{
                                        formatQuantity(
                                            material.consumed_quantity,
                                            locale,
                                        )
                                    }}
                                </td>
                                <td
                                    v-if="isStarted"
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{
                                        formatDecimal(
                                            material.unit_cost,
                                            locale,
                                            4,
                                        )
                                    }}
                                </td>
                                <td
                                    v-if="isStarted"
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(material.value) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </BaseCard>

            <BaseCard :title="t('Other costs')">
                <div class="custom-scrollbar overflow-x-auto">
                    <table v-if="order.costs?.length" class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Description') }}</th>
                                <th class="th-cell">{{ t('Account') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="cost in order.costs" :key="cost.id">
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ cost.description }}
                                </td>
                                <td class="td-cell">
                                    {{ cost.account ?? '—' }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(cost.amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="2">
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
                                    {{ amount(totalPlannedCosts) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <p
                        v-else
                        class="text-theme-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ t('No other costs.') }}
                    </p>
                </div>
            </BaseCard>
        </div>

        <BaseModal
            v-model:open="starting"
            :title="t('Start production')"
            max-width="sm"
        >
            <form
                id="start-form"
                class="space-y-4"
                @submit.prevent="startOrder"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'The materials will be issued from :warehouse to work in progress, at their average cost.',
                            { warehouse: order.materials_warehouse ?? '' },
                        )
                    }}
                </p>
                <BaseField
                    :label="t('Start date')"
                    for="start-date"
                    required
                    :error="startForm.errors.date"
                >
                    <BaseInput
                        id="start-date"
                        v-model="startForm.date"
                        type="date"
                        :invalid="Boolean(startForm.errors.date)"
                    />
                </BaseField>
            </form>
            <template #footer>
                <BaseButton variant="outline" @click="starting = false">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="start-form"
                    :loading="startForm.processing"
                >
                    {{ t('Start production') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="confirming !== null"
            :title="
                confirming === 'delete'
                    ? t('Delete production order')
                    : isCompleted
                      ? t('Reopen production order')
                      : t('Return production order to draft')
            "
            :message="
                confirming === 'delete'
                    ? t(
                          'The draft will be deleted. If it was started before, it is kept as cancelled.',
                      )
                    : isCompleted
                      ? t(
                            'The finished product will be taken out of stock and the completion entry reversed. The order goes back to in progress.',
                        )
                      : t(
                            'The materials will go back to their warehouse and the entry will be reversed. The order becomes a draft again.',
                        )
            "
            :confirm-label="
                confirming === 'delete' ? t('Delete') : t('Confirm')
            "
            :processing="processing"
            @update:open="(open) => !open && (confirming = null)"
            @confirm="confirmAction"
        />
    </div>
</template>
