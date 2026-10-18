<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import CloseCircle from '~icons/solar/close-circle-linear';
import Play from '~icons/solar/play-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal, formatNumber } from '@/lib/format';
import { show as showAsset } from '@/routes/admin/assets/assets';
import { cancel, index, show, store } from '@/routes/admin/assets/depreciation';
import type { Paginated } from '@/types';
import type {
    BaseCurrency,
    DepreciationPreviewRow,
    DepreciationRunRow,
    LengthAwarePage,
} from '@/types/assets';

const props = defineProps<{
    month: string;
    preview: DepreciationPreviewRow[];
    previewTotal: string;
    runs: LengthAwarePage<DepreciationRunRow>;
    baseCurrency: BaseCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const amount = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const monthLabel = computed(() =>
    formatDate(`${props.month}-01`, locale.value, {
        month: 'long',
        year: 'numeric',
    }),
);

const selectedMonth = ref(props.month);

function changeMonth(): void {
    if (!selectedMonth.value) {
        return;
    }

    router.get(
        index.url(),
        { month: selectedMonth.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const runForm = useForm({ month: props.month });

function postDepreciation(): void {
    runForm.month = props.month;
    runForm.submit(store(), { preserveScroll: true });
}

/**
 * The history arrives as a plain paginator: shape it for the pagination bar.
 */
const runsPage = computed<Paginated<DepreciationRunRow>>(() => ({
    data: props.runs.data,
    links: {
        first: props.runs.first_page_url,
        last: props.runs.last_page_url,
        prev: props.runs.prev_page_url,
        next: props.runs.next_page_url,
    },
    meta: {
        current_page: props.runs.current_page,
        from: props.runs.from,
        last_page: props.runs.last_page,
        links: props.runs.links.map((link) => ({
            url: link.url,
            label: link.label,
            active: link.active,
            page: link.page ?? null,
        })),
        path: props.runs.path,
        per_page: props.runs.per_page,
        to: props.runs.to,
        total: props.runs.total,
    },
}));

const cancelling = ref<DepreciationRunRow | null>(null);
const isCancelProcessing = ref(false);

function cancelRun(): void {
    if (cancelling.value === null) {
        return;
    }

    router.visit(cancel({ run: cancelling.value.id }), {
        preserveScroll: true,
        onStart: () => (isCancelProcessing.value = true),
        onFinish: () => {
            isCancelProcessing.value = false;
            cancelling.value = null;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Depreciation')"
            :breadcrumbs="[
                { title: t('Fixed Assets') },
                { title: t('Depreciation') },
            ]"
        />

        <div class="space-y-6">
            <BaseCard
                :title="t('Run depreciation')"
                :description="
                    t(
                        'Posts the depreciation of every asset in use up to the end of the month, in one journal entry.',
                    )
                "
            >
                <div class="mb-6 flex flex-wrap items-end gap-4">
                    <BaseField :label="t('Up to month')" for="month">
                        <BaseInput
                            id="month"
                            v-model="selectedMonth"
                            type="month"
                            dir="ltr"
                            class="w-48"
                            @change="changeMonth"
                        />
                    </BaseField>
                    <BaseButton
                        v-if="can('Depreciation Run Create') && preview.length"
                        :loading="runForm.processing"
                        @click="postDepreciation"
                    >
                        <Play class="size-5 rtl:rotate-180" />
                        {{ t('Post depreciation') }}
                    </BaseButton>
                </div>
                <p
                    v-if="runForm.errors.month"
                    class="-mt-4 mb-4 text-theme-sm text-error-500"
                >
                    {{ runForm.errors.month }}
                </p>

                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table v-if="preview.length" class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Number') }}</th>
                                <th class="th-cell">{{ t('Asset') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Months') }}
                                </th>
                                <th class="th-cell">{{ t('Period') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="row in preview" :key="row.id">
                                <td class="td-cell">
                                    <Link
                                        :href="showAsset({ asset: row.id })"
                                        class="font-mono text-brand-500 hover:underline"
                                        dir="ltr"
                                    >
                                        {{ row.number }}
                                    </Link>
                                </td>
                                <td
                                    class="td-cell text-gray-800 dark:text-white/90"
                                >
                                    {{ row.name }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ row.months }}
                                </td>
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(row.from, locale) }}
                                    –
                                    {{ formatDate(row.to, locale) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(row.amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t-2 border-gray-200 font-semibold text-gray-800 dark:border-gray-700 dark:text-white/90"
                            >
                                <td class="px-5 py-3" colspan="4">
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
                                    {{ amount(previewTotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <EmptyState
                        v-else
                        :message="
                            t('No depreciation is due up to :month.', {
                                month: monthLabel,
                            })
                        "
                    />
                </div>
            </BaseCard>

            <DataCard :pagination="runsPage">
                <div class="px-5 py-4 sm:px-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white/90">
                        {{ t('Past runs') }}
                    </h3>
                </div>
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <table v-if="runs.data.length" class="min-w-full">
                        <thead>
                            <tr
                                class="border-y border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Number') }}</th>
                                <th class="th-cell">{{ t('Period end') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Assets') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Total') }}
                                    <span class="text-theme-xs font-normal">
                                        ({{ baseCurrency.code }})
                                    </span>
                                </th>
                                <th class="th-cell">{{ t('Status') }}</th>
                                <th class="th-cell">{{ t('Created by') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="run in runs.data"
                                :key="run.id"
                                class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                            >
                                <td class="td-cell">
                                    <Link
                                        :href="show({ run: run.id })"
                                        class="font-mono text-brand-500 hover:underline"
                                        dir="ltr"
                                    >
                                        {{ run.number }}
                                    </Link>
                                </td>
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(run.period_end, locale) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ formatNumber(run.assets, locale) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ amount(run.total) }}
                                </td>
                                <td class="td-cell">
                                    <BaseBadge :color="run.status_color">
                                        {{ run.status_label }}
                                    </BaseBadge>
                                </td>
                                <td class="td-cell">
                                    {{ run.created_by ?? t('System') }}
                                </td>
                                <td class="td-cell">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <BaseIconButton
                                            v-if="run.can_cancel"
                                            color="danger"
                                            :label="t('Cancel run')"
                                            @click="cancelling = run"
                                        >
                                            <CloseCircle />
                                        </BaseIconButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState
                        v-else
                        :message="t('No depreciation has been posted yet.')"
                    />
                </div>
            </DataCard>
        </div>

        <ConfirmDialog
            :open="cancelling !== null"
            :title="t('Cancel depreciation run')"
            :message="
                t(
                    'The depreciation of :number and its journal entry will be reversed. This action cannot be undone.',
                    { number: cancelling?.number ?? '' },
                )
            "
            :confirm-label="t('Cancel run')"
            :processing="isCancelProcessing"
            @update:open="(open) => !open && (cancelling = null)"
            @confirm="cancelRun"
        />
    </div>
</template>
